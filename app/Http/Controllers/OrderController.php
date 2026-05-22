<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

  /**
   * Apply inventory changes for a set of order items. Decrements when
   * $direction is -1 (new order / new items on edit), restores when +1
   * (cancellation / replaced items on edit).
   *
   * Two layers move together so totals stay consistent:
   *   • products.quantity — the finished-goods count on the product itself
   *     (only adjusted when manage_stock = true, so non-stocked items like
   *     digital products or one-off services aren't affected).
   *   • raw_materials.current_stock — the BOM consumables that the product
   *     uses up per unit (caps, labels, water litres, etc.).
   *
   * Items can be either OrderItem models or raw arrays carrying
   * product_id + quantity. Gifts still consume inventory — a free bottle
   * is still a real bottle.
   */
  private function adjustRawMaterialStock(iterable $items, int $direction): void
  {
    $productIds = collect($items)
      ->map(fn($i) => is_array($i) ? $i['product_id'] : $i->product_id)
      ->unique()
      ->values();

    if ($productIds->isEmpty()) {
      return;
    }

    $products = Product::with('rawMaterials')
      ->whereIn('id', $productIds)
      ->get()
      ->keyBy('id');

    foreach ($items as $item) {
      $productId = is_array($item) ? $item['product_id'] : $item->product_id;
      $quantity  = (int) (is_array($item) ? $item['quantity'] : $item->quantity);
      $product   = $products[$productId] ?? null;
      if (!$product || $quantity <= 0) continue;

      // 1) Finished-goods stock on the product row.
      if ($product->manage_stock) {
        if ($direction < 0) {
          Product::where('id', $product->id)->decrement('quantity', $quantity);
        } else {
          Product::where('id', $product->id)->increment('quantity', $quantity);
        }
      }

      // 2) Raw-material consumables per the product's BOM.
      foreach ($product->rawMaterials as $material) {
        $perUnit = (float) ($material->pivot->quantity ?? 0);
        if ($perUnit <= 0) continue;

        $delta = $perUnit * $quantity;
        if ($direction < 0) {
          RawMaterial::where('id', $material->id)->decrement('current_stock', $delta);
        } else {
          RawMaterial::where('id', $material->id)->increment('current_stock', $delta);
        }
      }
    }
  }

  public function index()
  {
    $pagination = request()->has('pagination')
      ? request()->input('pagination')
      : ['limit' => 50, 'page' => 1];

    // Client-facing /orders route MUST be scoped to the signed-in user.
    // Only the admin /admin/orders path may list across users.
    $isAdminPath = request()->is('admin/*');
    $authUserId = auth()->id();

    // Clients now have a single-page home (/profile) that shows their orders
    // inline. Redirect them there if they hit /orders directly.
    if (!$isAdminPath && auth()->user()?->hasRole('Client')
        && !auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
      return redirect()->route('dashboard');
    }

    $orders = Order::with(['client', 'creator'])
      ->when(
        !$isAdminPath && $authUserId,
        fn($q) => $q->where('user_id', $authUserId)
      )
      ->when(
        request('status'),
        fn($q, $status) =>
        $q->where('status', $status)
      )
      ->when(
        $isAdminPath && request('user_id'),
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

  /**
   * Client-facing "order water" form. Pre-fills the signed-in user's
   * addresses so they can pick one; no client picker, no price overrides,
   * no gifts — those are admin-only concerns.
   */
  public function clientCreate(): Response
  {
    $user = auth()->user();
    $user->load('addresses');

    return Inertia::render('orders/ClientCreate')->with([
      'addresses' => $user->addresses,
      'products'  => Product::where('status', 'active')
        ->select(['id', 'name', 'price', 'sale_price', 'quantity', 'status'])
        ->get(),
    ]);
  }

  /**
   * Store a client-placed order. Validates the simpler client payload
   * (no custom_total, no gifts, no walk-in contact), forces the order
   * onto the signed-in user, and lets the existing inventory deduction
   * pipeline run.
   */
  public function clientStore(\Illuminate\Http\Request $request)
  {
    $data = $request->validate([
      'scheduled_delivery_at' => ['nullable', 'date', 'after_or_equal:today'],
      'delivery_address'      => ['nullable', 'string'],
      'new_address'           => ['nullable', 'string'],
      'new_address_label'     => ['nullable', 'string', 'max:50'],
      'notes'                 => ['nullable', 'string'],
      'items'                 => ['required', 'array', 'min:1'],
      'items.*.product_id'    => ['required', 'exists:products,id'],
      'items.*.quantity'      => ['required', 'integer', 'min:1'],
    ]);

    $userId = auth()->id();

    $order = DB::transaction(function () use ($data, $userId) {
      $items = collect($data['items'])->map(function ($item) {
        $product   = Product::findOrFail($item['product_id']);
        $unitPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
        $subtotal  = $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
          'is_gift'    => false,
        ];
      });

      $total = (float) $items->sum('subtotal');

      $deliveryAddress = $data['delivery_address'] ?? null;
      if (!empty($data['new_address'])) {
        $address = UserAddress::create([
          'user_id'      => $userId,
          'label'        => $data['new_address_label'] ?? 'New Address',
          'address_line' => $data['new_address'],
        ]);
        $deliveryAddress = $address->address_line;
      }

      $order = Order::create([
        'user_id'               => $userId,
        'scheduled_delivery_at' => $data['scheduled_delivery_at'] ?? null,
        'delivery_address'      => $deliveryAddress,
        'notes'                 => $data['notes'] ?? null,
        'total_amount'          => $total,
        'discount_amount'       => 0,
        'payment_status'        => $total <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid,
        'created_by'            => $userId,
      ]);

      $order->items()->createMany($items->toArray());
      $this->adjustRawMaterialStock($items, -1);

      return $order;
    });

    return redirect()->route('dashboard')
      ->with('success', "Order #{$order->order_number} placed. We'll be in touch shortly.");
  }

  /**
   * Resolve which user the order should belong to. If the request includes a
   * `new_contact` block (admin entered a walk-in client), find or create a
   * shell user (Client role, claimed_at = null) so a future self-registration
   * with the same phone/email can adopt the row.
   */
  private function resolveOrderUserId(StoreOrderRequest $request): int
  {
    if ($request->user_id) {
      return (int) $request->user_id;
    }

    $contact = $request->input('new_contact');
    $phone = $contact['phone'] ?? null;
    $email = $contact['email'] ?? null;
    $name  = $contact['name']  ?? null;

    if (!$phone && !$email) {
      // StoreOrderRequest should have caught this, but guard anyway.
      abort(422, 'A client or new contact (with phone) is required.');
    }

    // Try to match an existing user by phone first, then email.
    $user = null;
    if ($phone) {
      $existingPhone = UserPhone::where('phone', $phone)->first();
      if ($existingPhone) {
        $user = User::find($existingPhone->user_id);
      }
    }
    if (!$user && $email) {
      $user = User::where('email', $email)->first();
    }

    if (!$user) {
      $user = User::create([
        'name'     => $name ?: 'Walk-in client',
        'email'    => $email,
        'password' => Hash::make(Str::random(32)),
        'status'   => 'active',
        // claimed_at stays null → shell user, adoptable on later registration.
      ]);
      $user->assignRole('Client');

      if ($phone) {
        UserPhone::create([
          'user_id'    => $user->id,
          'phone'      => $phone,
          'label'      => 'Primary',
          'is_default' => true,
        ]);
      }
    }

    return $user->id;
  }

  public function store(StoreOrderRequest $request)
  {
    $userId = $this->resolveOrderUserId($request);
    $request->merge(['user_id' => $userId]);

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
        'payment_status'        => $finalTotal <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid,
        'created_by'            => auth()->id(),
      ]);

      $order->items()->createMany($items->toArray());

      // Deduct raw materials from inventory for the BOM of each line item.
      $this->adjustRawMaterialStock($items, -1);

      return $order;
    });

    return redirect()->route('admin.orders.show', $order)
      ->with('success', 'Order created successfully.');
  }

  public function show(Order $order)
  {
    // Non-admin path → only the order owner may view it. Staff/admin land
    // here via /admin/orders/{order} and bypass the check.
    $isAdminPath = request()->is('admin/*');
    if (!$isAdminPath && $order->user_id !== auth()->id()) {
      abort(404);
    }

    // Clients use the single-page home which opens a read-only modal for
    // order details. Send them there so they don't see the sidebar-wrapped
    // admin order page.
    if (!$isAdminPath && auth()->user()?->hasRole('Client')
        && !auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Operator', 'Currier'])) {
      return redirect()->route('dashboard');
    }

    $order->load(['client.userProfile', 'creator', 'canceller', 'items.product.rawMaterials', 'courier', 'returnedMaterials']);

    return Inertia::render('orders/Show')->with([
      'order'    => $order,
      'statuses' => OrderStatus::getValues(),
      'reusable_materials' => \App\Models\RawMaterial::where('is_reusable', true)->get(),
      // Expected vs. returned reusable containers, plus the deposit owed for
      // any shortfall. The admin sees this on delivery to know what to bill.
      'reusable_summary' => array_values($order->reusableDepositSummary()),
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

      $updates = [
        'user_id'               => $request->user_id,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $request->delivery_address,
        'notes'                 => $request->notes,
        'total_amount'          => $finalTotal,
        'discount_amount'       => $discount,
      ];

      // If editing drops the total to zero (all gifts / fully-discounted),
      // mark it paid so it doesn't sit forever as Unpaid with 0 balance.
      if ($finalTotal <= 0) {
        $updates['payment_status'] = PaymentStatus::Paid;
      }

      $order->update($updates);

      // Restore inventory for the items that are about to be replaced…
      $this->adjustRawMaterialStock($order->items()->get(), +1);

      $order->items()->delete();
      $order->items()->createMany($items->toArray());

      // …then deduct inventory for the new items.
      $this->adjustRawMaterialStock($items, -1);
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

    DB::transaction(function () use ($order, $data) {
      // Already-cancelled orders shouldn't double-restore stock. The status
      // dropdown blocks this client-side, but guard anyway in case the cancel
      // endpoint is hit directly.
      if ($order->status->value !== OrderStatus::Cancelled) {
        $this->adjustRawMaterialStock($order->items()->get(), +1);
      }

      $order->update([
        'status'              => OrderStatus::Cancelled,
        'cancellation_reason' => $data['cancellation_reason'],
        'cancelled_at'        => now(),
        'cancelled_by'        => auth()->id(),
      ]);
    });

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

    $wasAlreadyDelivered = $order->status->value === OrderStatus::Delivered;

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

        // After delivery, charge the client for any reusable containers
        // (e.g. 19L bottles) they didn't return. Recomputed every time so
        // re-editing the returned-materials list keeps the bill correct.
        if ($data['status'] === OrderStatus::Delivered) {
            $this->applyDepositCharge($order->fresh(['items.product.rawMaterials', 'returnedMaterials']));
        }
    });

    // Only fire the delivered event on the first transition into Delivered
    // so repeated saves (e.g. editing the returned-materials list) don't
    // re-spam the Telegram group.
    if (!$wasAlreadyDelivered && $data['status'] === OrderStatus::Delivered) {
        event(new \App\Events\OrderDelivered($order->fresh(['client', 'courier'])));
    }

    return back()->with('success', 'Order status updated.');
  }

  /**
   * Refresh the order's deposit_charge from the reusable-material
   * summary, and re-evaluate payment_status so an underpaid order
   * shows as Unpaid (or Paid if the running total catches up).
   */
  private function applyDepositCharge(Order $order): void
  {
    $summary = $order->reusableDepositSummary();
    $charge = (float) collect($summary)->sum('charge');

    if ((float) $order->deposit_charge === $charge) {
        return;
    }

    $order->deposit_charge = $charge;

    $newTotal = (float) $order->total_amount + $charge;
    if ((float) $order->paid_amount >= $newTotal && $newTotal > 0) {
        $order->payment_status = PaymentStatus::Paid;
    } elseif ((float) $order->paid_amount > 0) {
        $order->payment_status = PaymentStatus::Partial;
    } else {
        $order->payment_status = $newTotal <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid;
    }

    $order->save();
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

      if ($order->delivery_address === 'Self Pickup') {
          return back()->with('error', 'Self-pickup orders do not need a courier.');
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
