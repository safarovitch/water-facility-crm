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
        $history = $request->boolean('history');

        $query = Order::where('courier_id', $request->user()->id)
            ->with(['client', 'items.product']);

        if ($status) {
            $query->where('status', $status);
        } elseif ($history) {
            $query->whereIn('status', ['delivered', 'cancelled']);
        } else {
            $query->whereNotIn('status', ['delivered', 'cancelled']);
        }

        $orders = $query->latest()->get();

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
            'returned_materials' => ['nullable', 'array'],
            'returned_materials.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
            'returned_materials.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = Order::where('courier_id', $request->user()->id)
            ->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
            $order->update([
                'status' => $request->status,
                'actual_delivery_at' => $request->status === 'delivered' ? now() : $order->actual_delivery_at,
            ]);

            if ($request->has('returned_materials') && $request->status === OrderStatus::Delivered->value) {
                $syncData = [];
                foreach ($request->input('returned_materials') as $rm) {
                    $syncData[$rm['raw_material_id']] = ['quantity' => $rm['quantity']];
                    
                    \App\Models\RawMaterial::where('id', $rm['raw_material_id'])
                        ->increment('current_stock', $rm['quantity']);
                }
                $order->returnedMaterials()->sync($syncData);
            }
        });

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $order->load('returnedMaterials'),
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
