<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
  /**
   * Given the calculated item total and an optional admin-supplied custom
   * total, return [final_total, discount]. If custom is unset or equal to
   * the calculated total, discount is 0. If custom is lower, the gap is the
   * discount. If custom is higher, treat it as a surcharge (negative
   * discount) so the math still balances.
   */
  private function resolveTotals(float $calculatedTotal, $customTotal): array
  {
    if ($customTotal === null || $customTotal === '') {
      return [round($calculatedTotal, 2), 0.0];
    }
    $custom = round((float) $customTotal, 2);
    $discount = round($calculatedTotal - $custom, 2);
    return [$custom, $discount];
  }

  public function index(): Response
  {
    $pagination = request()->has('pagination')
      ? request()->input('pagination')
      : ['limit' => 50, 'page' => 1];

    $orders = Order::with(['client', 'creator'])
      ->when(
        request('status'),
        fn($q, $status) =>
        $q->where('status', $status)
      )
      ->when(
        request('user_id'),
        fn($q, $userId) =>
        $q->where('user_id', $userId)
      )
      ->when(
        request('from'),
        fn($q, $from) =>
        $q->whereDate('created_at', '>=', $from)
      )
      ->when(
        request('to'),
        fn($q, $to) =>
        $q->whereDate('created_at', '<=', $to)
      )
      ->latest()
      ->paginate($pagination['limit'], ['*'], 'page', $pagination['page']);

    return Inertia::render('orders/Index')->with([
      'orders'   => $orders,
      'statuses' => OrderStatus::getValues(),
    ]);
  }

  public function assignments(): Response
  {
    $couriers = User::role('Currier')->withCount(['orders' => function ($q) {
      $q->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]);
    }])->get();

    $orders = Order::whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
      ->with(['client', 'courier', 'items.product'])
      ->latest()
      ->get();

    $statuses = OrderStatus::asArray();
    // Exclude delivered/cancelled from the list of selectable filters
    unset($statuses['Delivered'], $statuses['Cancelled']);

    return Inertia::render('orders/Assignments', [
      'couriers' => $couriers,
      'orders'   => $orders,
      'statuses' => $statuses,
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('orders/Create')->with([
      'clients'  => User::role('Client')->with(['userProfile', 'addresses'])->get(['id', 'name', 'email']),
      'products' => Product::select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])->get(),
    ]);
  }

  public function store(StoreOrderRequest $request)
  {
    $order = DB::transaction(function () use ($request) {
      $items = collect($request->items)->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
        $isGift    = (bool) ($item['is_gift'] ?? false);
        $subtotal  = $isGift ? 0 : $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => $isGift,
        ];
      });

      $calculatedTotal = (float) $items->sum('subtotal');
      [$finalTotal, $discount] = $this->resolveTotals($calculatedTotal, $request->input('custom_total'));

      $deliveryAddress = $request->delivery_address;

      if ($request->filled('new_address')) {
        $address = UserAddress::create([
          'user_id'      => $request->user_id,
          'label'        => $request->new_address_label ?? 'New Address',
          'address_line' => $request->new_address,
        ]);
        $deliveryAddress = $address->address_line;
      }

      $order = Order::create([
        'user_id'               => $request->user_id,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $deliveryAddress,
        'notes'                 => $request->notes,
        'total_amount'          => $finalTotal,
        'discount_amount'       => $discount,
        'created_by'            => auth()->id(),
      ]);

      $order->items()->createMany($items->toArray());

      return $order;
    });

    return redirect()->route('admin.orders.show', $order)
      ->with('success', 'Order created successfully.');
  }

  public function show(Order $order): Response
  {
    $order->load(['client.userProfile', 'creator', 'canceller', 'items.product', 'courier', 'returnedMaterials']);

    return Inertia::render('orders/Show')->with([
      'order'    => $order,
      'statuses' => OrderStatus::getValues(),
      'reusable_materials' => \App\Models\RawMaterial::where('is_reusable', true)->get(),
      'couriers' => User::role('Currier')->withCount(['orders' => function ($q) {
        $q->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]);
      }])->get(),
    ]);
  }

  public function edit(Order $order): Response
  {
    $order->load(['items.product']);

    return Inertia::render('orders/Edit')->with([
      'order'    => $order,
      'clients'  => User::role('Client')->with('userProfile')->get(['id', 'name', 'email']),
      'products' => Product::select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])->get(),
    ]);
  }

  public function update(UpdateOrderRequest $request, Order $order)
  {
    DB::transaction(function () use ($request, $order) {
      $items = collect($request->items)->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
        $isGift    = (bool) ($item['is_gift'] ?? false);
        $subtotal  = $isGift ? 0 : $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => $isGift,
        ];
      });

      $calculatedTotal = (float) $items->sum('subtotal');
      [$finalTotal, $discount] = $this->resolveTotals($calculatedTotal, $request->input('custom_total'));

      $order->update([
        'user_id'               => $request->user_id,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $request->delivery_address,
        'notes'                 => $request->notes,
        'total_amount'          => $finalTotal,
        'discount_amount'       => $discount,
      ]);

      $order->items()->delete();
      $order->items()->createMany($items->toArray());
    });

    return redirect()->route('admin.orders.show', $order)
      ->with('success', 'Order updated successfully.');
  }

  public function cancel(Order $order)
  {
    if ($order->status->value === OrderStatus::Delivered) {
      return back()->with('error', 'Delivered orders cannot be cancelled.');
    }

    $data = request()->validate([
      'cancellation_reason' => ['required', 'string', 'max:1000'],
    ]);

    $order->update([
      'status'              => OrderStatus::Cancelled,
      'cancellation_reason' => $data['cancellation_reason'],
      'cancelled_at'        => now(),
      'cancelled_by'        => auth()->id(),
    ]);

    return back()->with('success', 'Order cancelled.');
  }

  public function updateStatus(Order $order)
  {
    $data = request()->validate([
      'status'             => ['required', 'in:' . implode(',', OrderStatus::getValues())],
      'actual_delivery_at' => ['nullable', 'date'],
      'returned_materials' => ['nullable', 'array'],
      'returned_materials.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
      'returned_materials.*.quantity' => ['required', 'integer', 'min:1'],
    ]);

    DB::transaction(function () use ($order, $data) {
        $order->update([
            'status' => $data['status'],
            'actual_delivery_at' => $data['actual_delivery_at'] ?? null,
        ]);

        if (isset($data['returned_materials']) && $data['status'] === OrderStatus::Delivered) {
            $syncData = [];
            foreach ($data['returned_materials'] as $rm) {
                // Determine if we need to sync multiple items or just one per iteration
                // Here, a client can return multiple of the same or different. We'll flatten them.
                $syncData[$rm['raw_material_id']] = ['quantity' => $rm['quantity']];
                
                // Also increment current_stock on the raw material to add it back into the inventory!
                \App\Models\RawMaterial::where('id', $rm['raw_material_id'])
                  ->increment('current_stock', $rm['quantity']);
            }
            // we do syncWithoutDetaching in case they add things later, but usually it's set once per delivery
            $order->returnedMaterials()->sync($syncData);
        }
    });

    return back()->with('success', 'Order status updated.');
  }

  public function payWithWallet(Order $order, \App\Services\WalletService $walletService)
  {
    if ($order->status->value === OrderStatus::Cancelled) {
      return back()->with('error', 'Cancelled orders cannot be paid.');
    }

    if ($order->payment_status->value === PaymentStatus::Paid) {
      return back()->with('error', 'Order is already paid.');
    }

    $amountToPay = $order->balance_due;

    try {
      $walletService->pay($order->client, $amountToPay, Order::class, $order->id, [
        'order_number' => $order->order_number,
      ]);

      $order->increment('paid_amount', $amountToPay);
      $order->update(['payment_status' => PaymentStatus::Paid]);

      return back()->with('success', 'Order paid successfully using wallet.');
    } catch (\Exception $e) {
      return back()->with('error', $e->getMessage());
    }
  }

  public function assignCurrier(Order $order)
  {
      if ($order->status->value === OrderStatus::Cancelled) {
          return back()->with('error', 'Cancelled orders cannot be assigned to a courier.');
      }

      request()->validate([
          'courier_id' => ['nullable', 'exists:users,id'],
      ]);

      $courierId = request()->input('courier_id');

      if ($courierId) {
          $courier = User::findOrFail($courierId);
          if (!$courier->hasRole('Currier')) {
              return back()->with('error', 'The selected user is not a currier.');
          }
      }

      $order->update(['courier_id' => $courierId]);

      return back()->with('success', 'Currier updated successfully.');
  }

  /**
   * Live courier-location tracking for the signed-in client's active order.
   * Returns the assigned courier's last fix when an order is in
   * Accepted / Ready / InTransit; otherwise reports tracking: false.
   */
  public function activeTracking(\Illuminate\Http\Request $request)
  {
    $user = $request->user();

    $order = Order::where('user_id', $user->id)
      ->whereIn('status', [
        OrderStatus::Accepted,
        OrderStatus::Ready,
        OrderStatus::InTransit,
      ])
      ->whereNotNull('courier_id')
      ->with(['courier.lastLocation'])
      ->latest()
      ->first();

    if (! $order || ! $order->courier || ! $order->courier->lastLocation) {
      return response()
        ->json(['tracking' => false])
        ->header('Cache-Control', 'no-store, max-age=0');
    }

    $loc = $order->courier->lastLocation;

    return response()->json([
      'tracking' => true,
      'order' => [
        'id' => $order->id,
        'status' => (string) $order->status,
      ],
      'courier' => [
        'lat' => (float) $loc->lat,
        'lng' => (float) $loc->lng,
        'updated_at' => $loc->updated_at->toIso8601String(),
      ],
    ])->header('Cache-Control', 'no-store, max-age=0');
  }
}
