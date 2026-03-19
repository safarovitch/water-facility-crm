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

    $unassignedOrders = Order::whereNull('courier_id')
      ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
      ->with(['client', 'items.product'])
      ->latest()
      ->get();

    $assignedOrders = Order::whereNotNull('courier_id')
      ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
      ->with(['client', 'courier', 'items.product'])
      ->latest()
      ->get();

    return Inertia::render('orders/Assignments', [
      'couriers'         => $couriers,
      'unassignedOrders' => $unassignedOrders,
      'assignedOrders'   => $assignedOrders,
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
        $subtotal  = $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
        ];
      });

      $total = $items->sum('subtotal');

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
        'total_amount'          => $total,
        'created_by'            => auth()->id(),
      ]);

      $order->items()->createMany($items->toArray());

      return $order;
    });

    return redirect()->route('orders.show', $order)
      ->with('success', 'Order created successfully.');
  }

  public function show(Order $order): Response
  {
    $order->load(['client.userProfile', 'creator', 'items.product']);

    return Inertia::render('orders/Show')->with([
      'order'    => $order,
      'statuses' => OrderStatus::getValues(),
      'couriers' => User::role('Currier')->get(['id', 'name']),
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
        $subtotal  = $unitPrice * $item['quantity'];

        return [
          'product_id' => $item['product_id'],
          'quantity'   => $item['quantity'],
          'unit_price' => $unitPrice,
          'subtotal'   => $subtotal,
        ];
      });

      $order->update([
        'user_id'               => $request->user_id,
        'scheduled_delivery_at' => $request->scheduled_delivery_at,
        'delivery_address'      => $request->delivery_address,
        'notes'                 => $request->notes,
        'total_amount'          => $items->sum('subtotal'),
      ]);

      $order->items()->delete();
      $order->items()->createMany($items->toArray());
    });

    return redirect()->route('orders.show', $order)
      ->with('success', 'Order updated successfully.');
  }

  public function cancel(Order $order)
  {
    if ($order->status->value === OrderStatus::Delivered) {
      return back()->with('error', 'Delivered orders cannot be cancelled.');
    }

    $order->update(['status' => OrderStatus::Cancelled]);

    return back()->with('success', 'Order cancelled.');
  }

  public function updateStatus(Order $order)
  {
    $data = request()->validate([
      'status'             => ['required', 'in:' . implode(',', OrderStatus::getValues())],
      'actual_delivery_at' => ['nullable', 'date'],
    ]);

    $order->update($data);

    return back()->with('success', 'Order status updated.');
  }

  public function payWithWallet(Order $order, \App\Services\WalletService $walletService)
  {
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
      $data = request()->validate([
          'courier_id' => ['nullable', 'exists:users,id'],
      ]);

      if ($data['courier_id']) {
          $courier = User::findOrFail($data['courier_id']);
          if (!$courier->hasRole('Currier')) {
              return back()->with('error', 'The selected user is not a currier.');
          }
      }

      $order->update(['courier_id' => $data['courier_id']]);

      return back()->with('success', 'Currier assigned successfully.');
  }
}
