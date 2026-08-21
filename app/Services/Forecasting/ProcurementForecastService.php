<?php

namespace App\Services\Forecasting;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Converts a demand forecast in finished units into what actually has to be
 * bought, filled and stocked.
 *
 * Predicting "we will sell 900 bottles" is only half an answer. The question
 * that decides whether the month is profitable is how many *empty* 19-litre
 * containers, caps and labels must exist to serve it — and for reusable
 * containers that depends on how many customers hand theirs back, which this
 * business measures directly through order_returned_materials.
 *
 * The return rate is the lever most likely to be quietly costing money. A
 * fleet with a 92% return rate needs a trickle of replacement bottles; at 80%
 * it needs five times as many, and the difference shows up as margin rather
 * than as a stockout. Measuring the rate from history rather than assuming it
 * is the point of this class.
 */
class ProcurementForecastService
{
    /**
     * Raw material requirements implied by a product-level forecast.
     *
     * @param  array<int, array{product_id: int, units: float}>  $productForecast
     * @return array<string, mixed>
     */
    public function requirements(array $productForecast, ?int $lookbackDays = 180): array
    {
        $productIds = array_column($productForecast, 'product_id');

        if (empty($productIds)) {
            return ['materials' => [], 'return_rate' => null, 'coverage' => []];
        }

        $products = Product::with('rawMaterials')->findMany($productIds)->keyBy('id');
        $unitsBy  = collect($productForecast)->pluck('units', 'product_id');

        $returnRates = $this->returnRates($lookbackDays);

        $materials = [];

        foreach ($productForecast as $line) {
            $product = $products->get($line['product_id']);

            if (! $product) {
                continue;
            }

            $units = (float) ($unitsBy[$line['product_id']] ?? 0);

            foreach ($product->rawMaterials as $material) {
                $perUnit = (float) ($material->pivot->quantity ?? 0);

                if ($perUnit <= 0) {
                    continue;
                }

                $gross = $units * $perUnit;

                $bucket = &$materials[$material->id];
                $bucket['id']            = $material->id;
                $bucket['name']          = $material->name;
                $bucket['unit']          = $material->unit;
                $bucket['is_reusable']   = (bool) $material->is_reusable;
                $bucket['current_stock'] = (float) $material->current_stock;
                $bucket['cost_per_unit'] = (float) $material->cost_per_unit;
                $bucket['deposit_price'] = (float) $material->deposit_price;
                $bucket['gross_required'] = ($bucket['gross_required'] ?? 0.0) + $gross;
                unset($bucket);
            }
        }

        foreach ($materials as $id => $data) {
            $gross = $data['gross_required'];

            if ($data['is_reusable']) {
                // Reusable containers cycle. Only the ones that never come
                // back have to be replaced, so the purchase requirement is the
                // leakage, not the throughput.
                $rate = $returnRates[$id] ?? null;
                $loss = $rate !== null ? max(0.0, 1 - $rate) : 1.0;

                $materials[$id]['return_rate']    = $rate !== null ? round($rate, 4) : null;
                $materials[$id]['net_required']   = round($gross * $loss, 1);
                $materials[$id]['circulating']    = round($gross, 1);
                $materials[$id]['deposit_at_risk'] = round($gross * $loss * $data['deposit_price'], 2);
            } else {
                // Consumed on every delivery: caps, labels, the water itself.
                $materials[$id]['return_rate']     = null;
                $materials[$id]['net_required']    = round($gross, 1);
                $materials[$id]['circulating']     = null;
                $materials[$id]['deposit_at_risk'] = 0.0;
            }

            $net = $materials[$id]['net_required'];

            $materials[$id]['gross_required'] = round($gross, 1);
            $materials[$id]['shortfall']      = round(max(0.0, $net - $data['current_stock']), 1);
            $materials[$id]['purchase_cost']  = round(max(0.0, $net - $data['current_stock']) * $data['cost_per_unit'], 2);
            $materials[$id]['days_of_cover']  = null; // filled by coverage() when a horizon is known
        }

        $out = array_values($materials);
        usort($out, fn ($a, $b) => $b['shortfall'] <=> $a['shortfall']);

        return [
            'materials'        => $out,
            'total_purchase'   => round(array_sum(array_column($out, 'purchase_cost')), 2),
            'deposit_at_risk'  => round(array_sum(array_column($out, 'deposit_at_risk')), 2),
            'lookback_days'    => $lookbackDays,
        ];
    }

    /**
     * Add days-of-cover to each material given the horizon the forecast spans.
     *
     * Shortfall alone does not say how urgent a purchase is; a material that
     * runs out on day 3 of a 30-day forecast is a different problem from one
     * that runs out on day 28.
     *
     * @param  array<string, mixed>  $requirements
     * @return array<string, mixed>
     */
    public function withCoverage(array $requirements, int $horizonDays): array
    {
        $horizonDays = max(1, $horizonDays);

        foreach ($requirements['materials'] as $i => $material) {
            $perDay = $material['net_required'] / $horizonDays;

            $requirements['materials'][$i]['daily_burn']    = round($perDay, 2);
            $requirements['materials'][$i]['days_of_cover'] = $perDay > 0
                ? (int) floor($material['current_stock'] / $perDay)
                : null;
        }

        return $requirements;
    }

    /**
     * Observed return rate per reusable material: containers handed back as a
     * fraction of containers issued, over the lookback window.
     *
     * Deferred returns (the client promised to hand them back next visit)
     * count as returned. They are a timing difference, not a loss, and
     * treating them as lost would inflate the purchase plan every month.
     *
     * @return array<int, float>
     */
    public function returnRates(?int $lookbackDays = 180): array
    {
        $since = $lookbackDays ? Carbon::now()->subDays($lookbackDays) : null;

        // Issued: reusable material handed over on delivered orders, via BOM.
        $issued = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('product_raw_material', 'product_raw_material.product_id', '=', 'order_items.product_id')
            ->join('raw_materials', 'raw_materials.id', '=', 'product_raw_material.raw_material_id')
            ->where('raw_materials.is_reusable', '=', true)
            ->where('orders.status', '=', OrderStatus::Delivered)
            ->where('order_items.is_gift', '=', false)
            ->when($since, fn ($q) => $q->where('orders.created_at', '>=', $since))
            ->selectRaw('raw_materials.id as material_id, SUM(COALESCE(order_items.delivered_quantity, order_items.quantity) * product_raw_material.quantity) as issued')
            ->groupBy('raw_materials.id')
            ->pluck('issued', 'material_id');

        $returned = DB::table('order_returned_materials')
            ->join('orders', 'orders.id', '=', 'order_returned_materials.order_id')
            ->where('orders.status', '=', OrderStatus::Delivered)
            ->when($since, fn ($q) => $q->where('orders.created_at', '>=', $since))
            ->selectRaw('order_returned_materials.raw_material_id as material_id, SUM(order_returned_materials.quantity + COALESCE(order_returned_materials.deferred_quantity, 0)) as returned')
            ->groupBy('order_returned_materials.raw_material_id')
            ->pluck('returned', 'material_id');

        $rates = [];

        foreach ($issued as $materialId => $issuedQty) {
            $issuedQty = (float) $issuedQty;

            if ($issuedQty <= 0) {
                continue;
            }

            // Cap at 1: a client returning bottles from before the window can
            // otherwise push the ratio above 100% and produce a negative
            // purchase requirement.
            $rates[(int) $materialId] = min(1.0, (float) ($returned[$materialId] ?? 0) / $issuedQty);
        }

        return $rates;
    }

    /**
     * Reusable materials with no measurable return history yet, so the UI can
     * say the replacement figure is a worst case rather than a measurement.
     *
     * @return array<int, string>
     */
    public function unmeasuredReusables(?int $lookbackDays = 180): array
    {
        $measured = array_keys($this->returnRates($lookbackDays));

        return RawMaterial::query()
            ->where('is_reusable', true)
            ->whereNotIn('id', $measured ?: [0])
            ->pluck('name', 'id')
            ->all();
    }
}
