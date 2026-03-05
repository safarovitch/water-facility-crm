<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
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

  public function create(): Response
  {
    return Inertia::render('orders/Create')->with([
      'clients'  => User::role('Client')->with('userProfile')->get(['id', 'name', 'email']),
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

      $order = Order::create([
        'user_id'          => $request->user_id,
        'delivery_date'    => $request->delivery_date,
        'delivery_address' => $request->delivery_address,
        'notes'            => $request->notes,
        'total_amount'     => $total,
        'created_by'       => auth()->id(),
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
        'user_id'          => $request->user_id,
        'delivery_date'    => $request->delivery_date,
        'delivery_address' => $request->delivery_address,
        'notes'            => $request->notes,
        'total_amount'     => $items->sum('subtotal'),
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
    $status = request()->validate([
      'status' => ['required', 'in:' . implode(',', OrderStatus::getValues())],
    ])['status'];

    $order->update(['status' => $status]);

    return back()->with('success', 'Order status updated.');
  }
}
