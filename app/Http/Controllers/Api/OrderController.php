<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        
        $orders = Order::where('courier_id', $request->user()->id)
            ->when($status, function($q) use ($status) {
                $q->where('status', $status);
            }, function($q) {
                // By default, only show active (not delivered/cancelled) orders
                $q->whereNotIn('status', ['delivered', 'cancelled']);
            })
            ->with(['client', 'items.product'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = Order::where('courier_id', $request->user()->id)
            ->with(['client', 'items.product', 'client.addresses', 'client.phones'])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $order = Order::where('courier_id', $request->user()->id)
            ->findOrFail($id);

        $order->update([
            'status' => $request->status,
            'actual_delivery_at' => $request->status === 'delivered' ? now() : $order->actual_delivery_at,
        ]);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $order,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $order = Order::where('courier_id', $request->user()->id)
            ->findOrFail($id);

        $order->update([
            'courier_id' => null,
            'status' => OrderStatus::Pending,
        ]);

        return response()->json([
            'message' => 'Order rejected successfully.',
            'order' => $order,
        ]);
    }
}
