<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderAccountingService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $history = $request->boolean('history');

        $query = Order::where('courier_id', $request->user()->id)
            ->with(['client', 'items.product.rawMaterials', 'returnedMaterials']);

        if ($status) {
            $query->where('status', $status);
        } elseif ($history) {
            $query->whereIn('status', ['delivered', 'cancelled']);
        } else {
            $query->whereNotIn('status', ['delivered', 'cancelled']);
        }

        $orders = $query->latest()->get()->map(fn (Order $order) => $this->withReusableSummary($order));

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = Order::where('courier_id', $request->user()->id)
            ->with(['client', 'items.product.rawMaterials', 'client.addresses', 'client.phones', 'returnedMaterials'])
            ->findOrFail($id);

        return response()->json($this->withReusableSummary($order));
    }

    /**
     * Order attributes plus the expected/returned/deferred reusable-container
     * breakdown, so the app can show deposit-relevant info without a second
     * request. See Order::reusableDepositSummary for the shape.
     */
    private function withReusableSummary(Order $order): array
    {
        return $order->toArray() + [
            'reusable_summary' => array_values($order->reusableDepositSummary()),
        ];
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string',
            'returned_materials' => ['nullable', 'array'],
            'returned_materials.*.raw_material_id' => ['required', 'exists:raw_materials,id'],
            'returned_materials.*.quantity' => ['required', 'integer', 'min:0'],
            // Empties the courier opted to collect on a later visit rather than
            // charge a deposit for.
            'returned_materials.*.deferred_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $order = Order::where('courier_id', $request->user()->id)
            ->findOrFail($id);

        $wasAlreadyDelivered = $order->status->value === OrderStatus::Delivered;

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
            $order->update([
                'status' => $request->status,
                'actual_delivery_at' => $request->status === 'delivered' ? now() : $order->actual_delivery_at,
            ]);

            if ($request->has('returned_materials') && $request->status === OrderStatus::Delivered) {
                $syncData = [];
                foreach ($request->input('returned_materials') as $rm) {
                    $collected = (int) ($rm['quantity'] ?? 0);
                    $deferred  = (int) ($rm['deferred_quantity'] ?? 0);
                    if ($collected <= 0 && $deferred <= 0) {
                        continue;
                    }
                    $syncData[$rm['raw_material_id']] = [
                        'quantity'          => $collected,
                        'deferred_quantity' => $deferred,
                    ];

                    if ($collected > 0) {
                        \App\Models\RawMaterial::where('id', $rm['raw_material_id'])
                            ->increment('current_stock', $collected);
                    }
                }
                $order->returnedMaterials()->sync($syncData);
            }

            // Bill the customer for any unreturned reusable containers
            // (e.g. 19L bottles). See OrderController::applyDepositCharge
            // for the web counterpart; the logic is duplicated here so the
            // courier mobile app stays in sync without an extra round-trip.
            if ($request->status === OrderStatus::Delivered) {
                $this->applyDepositCharge($order->fresh(['items.product.rawMaterials', 'returnedMaterials']));
            }
        });

        $order->refresh()->load('courier');
        event(new \App\Events\OrderStatusUpdated($order));

        if (!$wasAlreadyDelivered && $request->status === OrderStatus::Delivered) {
            event(new \App\Events\OrderDelivered($order->fresh(['client', 'courier'])));
        }

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => $this->withReusableSummary($order->load('returnedMaterials')),
        ]);
    }

    /**
     * Mark deferred (collect-later) bottles on the courier's own order as now
     * collected. Mirrors OrderController::collectDeferred (the admin/web
     * counterpart) but scoped to orders assigned to the requesting courier.
     */
    public function collectDeferred(Request $request, $id)
    {
        $data = $request->validate([
            'raw_material_ids' => ['required', 'array', 'min:1'],
            'raw_material_ids.*' => ['required', 'exists:raw_materials,id'],
        ]);

        $order = Order::where('courier_id', $request->user()->id)->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data) {
            foreach ($data['raw_material_ids'] as $rawMaterialId) {
                $pivot = $order->returnedMaterials()
                    ->wherePivot('raw_material_id', $rawMaterialId)
                    ->first()?->pivot;

                if (!$pivot || (int) $pivot->deferred_quantity <= 0) {
                    continue;
                }

                $qty = (int) $pivot->deferred_quantity;

                $order->returnedMaterials()->updateExistingPivot($rawMaterialId, [
                    'quantity'          => $pivot->quantity + $qty,
                    'deferred_quantity' => 0,
                ]);

                \App\Models\RawMaterial::where('id', $rawMaterialId)->increment('current_stock', $qty);
            }
        });

        return response()->json([
            'message' => 'Bottles marked as collected.',
            'order' => $this->withReusableSummary($order->fresh('returnedMaterials')),
        ]);
    }

    private function applyDepositCharge(Order $order): void
    {
        $summary = $order->reusableDepositSummary();
        $charge = (float) collect($summary)->sum('charge');

        if ((float) $order->deposit_charge === $charge) {
            return;
        }

        $order->deposit_charge = $charge;
        $order->save();

        $order->refresh()->reconcilePaymentStatus();

        app(OrderAccountingService::class)->syncPaymentRecord($order);
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
