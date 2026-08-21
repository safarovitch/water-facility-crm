<?php

namespace App\Services\Forecasting;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionRun;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Answers one question, for one date or a range: how many bottles to fill.
 *
 * This is the operational face of the forecast. The demand dashboard is for
 * deciding things once a month; this is for the person standing at the filling
 * line at 7am, so everything here is whole bottles, no probabilities, and no
 * statistics vocabulary.
 *
 * The plan is deliberately just-in-time. Stock carried forward is netted off
 * before anything is filled, so a day whose demand is already covered by what
 * is standing in the warehouse asks for nothing. That is what "produce only
 * the needed amount" means in practice, and it is also why the ready-stock
 * ledger has to exist: without knowing what is already filled, the only safe
 * plan is to over-produce every day.
 *
 * Demand for a day comes from three places, and the awkward one is the third:
 *   - orders already placed for that date (exact);
 *   - subscription schedules not yet turned into orders (exact);
 *   - the statistical forecast for everyone else (an estimate).
 * The first two are facts, so they are shown separately from the third and a
 * plan can be read honestly even when the forecast is still on priors.
 */
class ProductionPlanService
{
    public function __construct(private readonly DemandForecastService $forecast) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // A production plan is a working document, not a study; a quarter is
        // already far past the point where filling to it makes sense.
        if ($from->diffInDays($to) > 92) {
            $to = $from->copy()->addDays(92);
        }

        $dates = $this->dateRange($from, $to);

        $confirmed  = $this->confirmedByDay($from, $to);
        $predicted  = $this->predictedByDay($from, $to);
        $recorded   = $this->recordedByDay($from, $to);

        $products = Product::with('rawMaterials')
            ->whereIn('id', $this->relevantProductIds($confirmed, $predicted))
            ->get();

        $rows = [];

        foreach ($products as $product) {
            $rows[] = $this->planForProduct($product, $dates, $from, $confirmed, $predicted, $recorded);
        }

        usort($rows, fn ($a, $b) => $b['to_fill'] <=> $a['to_fill']);

        return [
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'is_range'    => $from->ne($to),
            'day_count'   => count($dates),
            'products'    => $rows,
            'totals'      => [
                'needed'   => array_sum(array_column($rows, 'needed')),
                'to_fill'  => array_sum(array_column($rows, 'to_fill')),
                'recorded' => array_sum(array_column($rows, 'recorded')),
                'ready'    => array_sum(array_column($rows, 'ready_now')),
            ],
            // True when nobody has ever counted stock, so the page can ask for
            // one instead of quietly assuming the warehouse is empty.
            'needs_stock_count' => collect($rows)->contains(fn ($r) => ! $r['has_count']),
        ];
    }

    /**
     * @param  string[]  $dates
     * @return array<string, mixed>
     */
    private function planForProduct(
        Product $product,
        array $dates,
        Carbon $from,
        array $confirmed,
        array $predicted,
        array $recorded,
    ): array {
        $stock = $this->readyStock($product->id, $from->copy()->subDay());

        $opening = (float) $stock['units'];
        $days    = [];

        $totalNeeded   = 0.0;
        $totalToFill   = 0.0;
        $totalRecorded = 0.0;

        foreach ($dates as $date) {
            $confirmedUnits = (float) ($confirmed[$date][$product->id] ?? 0);
            $predictedUnits = (float) ($predicted[$date][$product->id] ?? 0);

            // Nobody fills 6.4 bottles, so the figure is rounded to whole
            // ones. Nearest, not up: rounding up would turn a tail of 0.2
            // predicted bottles into a whole bottle every single day, telling
            // staff to "fill 1" six days running and quietly over-producing
            // the very thing this page exists to prevent. Confirmed orders are
            // already whole, so this only ever moves the forecast tail, and
            // the deficit carries forward in stock until it is a real bottle.
            $needed = (float) round($confirmedUnits + $predictedUnits);

            $recordedUnits = (float) ($recorded[$date][$product->id] ?? 0);

            $available = $opening + $recordedUnits;
            $toFill    = max(0.0, $needed - $available);
            $closing   = $available + $toFill - $needed;

            $days[] = [
                'date'      => $date,
                'weekday'   => Carbon::parse($date)->dayOfWeekIso,
                'confirmed' => (int) round($confirmedUnits),
                'predicted' => (int) round($predictedUnits),
                'needed'    => (int) $needed,
                'opening'   => (int) round($opening),
                'recorded'  => (int) $recordedUnits,
                'to_fill'   => (int) $toFill,
                'closing'   => (int) round($closing),
            ];

            $totalNeeded   += $needed;
            $totalToFill   += $toFill;
            $totalRecorded += $recordedUnits;

            $opening = $closing;
        }

        return [
            'product_id'     => $product->id,
            'name'           => $product->name,
            'ready_now'      => (int) round($stock['units']),
            'has_count'      => $stock['has_count'],
            'counted_on'     => $stock['counted_on'],
            'needed'         => (int) $totalNeeded,
            'to_fill'        => (int) $totalToFill,
            'recorded'       => (int) $totalRecorded,
            'confirmed'      => (int) round(array_sum(array_column($days, 'confirmed'))),
            'predicted'      => (int) round(array_sum(array_column($days, 'predicted'))),
            'days'           => $days,
            'materials'      => $this->materials($product),
        ];
    }

    /**
     * Bottles ready to ship for a product, as at the end of the given day.
     *
     * Derived rather than stored: a physical count sets the balance, then
     * production adds and deliveries subtract. Everything before the most
     * recent count is ignored on purpose — a fresh count is the staff saying
     * "never mind the arithmetic, this is what is actually on the floor".
     *
     * @return array{units: float, has_count: bool, counted_on: ?string}
     */
    public function readyStock(int $productId, Carbon $asOf): array
    {
        $asOf = $asOf->copy()->endOfDay();

        $count = ProductionRun::query()
            ->counts()
            ->where('product_id', $productId)
            ->where('production_date', '<=', $asOf->toDateString())
            ->orderByDesc('production_date')
            ->first();

        // With no count there is no anchor, and guessing would be worse than
        // saying so: the caller surfaces this as "tell us what is ready".
        if (! $count) {
            return ['units' => 0.0, 'has_count' => false, 'counted_on' => null];
        }

        $since = $count->production_date->copy()->startOfDay();

        $produced = (float) ProductionRun::query()
            ->production()
            ->where('product_id', $productId)
            ->whereBetween('production_date', [$since->toDateString(), $asOf->toDateString()])
            ->sum('units');

        $shipped = $this->shippedUnits($productId, $since, $asOf);

        return [
            'units'      => max(0.0, $count->units + $produced - $shipped),
            'has_count'  => true,
            'counted_on' => $count->production_date->toDateString(),
        ];
    }

    /**
     * Bottles that physically left the warehouse in a window.
     *
     * Counted on delivered orders only, and by delivered_quantity where the
     * courier recorded a short delivery — bottles that came back on the van
     * never left stock. Gifts count: a free bottle is still a real bottle.
     */
    private function shippedUnits(int $productId, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', '=', OrderStatus::Delivered)
            ->where('order_items.product_id', '=', $productId)
            ->whereBetween(
                DB::raw('COALESCE(orders.actual_delivery_at, orders.scheduled_delivery_at, orders.updated_at)'),
                [$from->toDateTimeString(), $to->toDateTimeString()],
            )
            ->sum(DB::raw('COALESCE(order_items.delivered_quantity, order_items.quantity)'));
    }

    /**
     * Orders already on the books, by the date they are due out.
     *
     * Gifts are included and revenue is ignored: this is a count of bottles
     * that must physically exist, not of money.
     *
     * @return array<string, array<int, float>> [date][product_id] => units
     */
    private function confirmedByDay(Carbon $from, Carbon $to): array
    {
        $out = [];

        $orders = Order::query()
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->with(['items:id,order_id,product_id,quantity,is_gift'])
            ->get(['id', 'subscription_id', 'scheduled_delivery_at']);

        foreach ($orders as $order) {
            // An open order with no delivery date still has to be filled for;
            // it belongs on the first day rather than nowhere.
            $date = $order->scheduled_delivery_at
                ? $order->scheduled_delivery_at->copy()->startOfDay()
                : $from->copy();

            if ($date->lt($from)) {
                $date = $from->copy();
            }

            if ($date->gt($to)) {
                continue;
            }

            $key = $date->toDateString();

            foreach ($order->items as $item) {
                $out[$key][$item->product_id] = ($out[$key][$item->product_id] ?? 0) + (float) $item->quantity;
            }
        }

        // Subscription deliveries that have not been turned into orders yet.
        // The generator advances next_delivery_at past whatever it created, so
        // an order and a schedule date never describe the same delivery — but
        // the guard below makes that explicit rather than assumed.
        $alreadyOrdered = Order::query()
            ->whereNotNull('subscription_id')
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereNotNull('scheduled_delivery_at')
            ->whereBetween('scheduled_delivery_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['subscription_id', 'scheduled_delivery_at'])
            ->map(fn (Order $o) => $o->subscription_id . '|' . $o->scheduled_delivery_at->toDateString())
            ->flip();

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->with('items:id,subscription_id,product_id,quantity')
            ->get();

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->occurrencesBetween($from, $to) as $date) {
                $key = $date->toDateString();

                if ($alreadyOrdered->has($subscription->id . '|' . $key)) {
                    continue;
                }

                foreach ($subscription->items as $item) {
                    $out[$key][$item->product_id] = ($out[$key][$item->product_id] ?? 0) + (float) $item->quantity;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<int, float>> [date][product_id] => units
     */
    private function predictedByDay(Carbon $from, Carbon $to): array
    {
        $result = $this->forecast->forecast($from, $to, ['include_clients' => false]);

        $out = [];

        foreach ($result['days'] as $day) {
            foreach ($day['predicted_by_product'] ?? [] as $productId => $units) {
                $out[$day['date']][(int) $productId] = (float) $units;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<int, float>> [date][product_id] => units
     */
    private function recordedByDay(Carbon $from, Carbon $to): array
    {
        return ProductionRun::query()
            ->production()
            ->whereBetween('production_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->reduce(function (array $carry, ProductionRun $run) {
                $carry[$run->production_date->toDateString()][$run->product_id] = (float) $run->units;

                return $carry;
            }, []);
    }

    /**
     * Materials on hand, expressed as "enough for this many more bottles".
     *
     * One number per material rather than a shortfall table, because the
     * operational question is simply whether the line can keep running.
     *
     * Note these stock figures are decremented when an order is *placed*
     * (OrderController::adjustRawMaterialStock), not when a bottle is filled,
     * so they already exclude everything reserved by open orders. The figure
     * therefore means "spare capacity beyond what is already committed".
     *
     * @return array<int, array<string, mixed>>
     */
    private function materials(Product $product): array
    {
        $out = [];

        foreach ($product->rawMaterials as $material) {
            $perUnit = (float) ($material->pivot->quantity ?? 0);

            if ($perUnit <= 0) {
                continue;
            }

            $out[] = [
                'id'            => $material->id,
                'name'          => $material->name,
                'unit'          => $material->unit,
                'per_bottle'    => $perUnit,
                'current_stock' => (float) $material->current_stock,
                'covers'        => (int) floor(((float) $material->current_stock) / $perUnit),
                'is_reusable'   => (bool) $material->is_reusable,
            ];
        }

        usort($out, fn ($a, $b) => $a['covers'] <=> $b['covers']);

        return $out;
    }

    /**
     * Record what was actually filled on a day. Re-recording replaces the
     * day's figure rather than adding to it, so a typo is fixed by typing the
     * right number again.
     */
    public function recordProduction(Carbon $date, int $productId, int $units, ?int $userId = null, ?string $notes = null): ProductionRun
    {
        return ProductionRun::updateOrCreate(
            [
                'production_date' => $date->toDateString(),
                'product_id'      => $productId,
                'type'            => ProductionRun::TYPE_PRODUCTION,
            ],
            ['units' => max(0, $units), 'recorded_by' => $userId, 'notes' => $notes],
        );
    }

    /**
     * Record a physical stock count, which becomes the new anchor.
     */
    public function recordCount(Carbon $date, int $productId, int $units, ?int $userId = null): ProductionRun
    {
        return ProductionRun::updateOrCreate(
            [
                'production_date' => $date->toDateString(),
                'product_id'      => $productId,
                'type'            => ProductionRun::TYPE_COUNT,
            ],
            ['units' => max(0, $units), 'recorded_by' => $userId],
        );
    }

    /**
     * Products worth planning for: anything with demand, plus anything that
     * has ever been produced, so a product does not vanish from the page on a
     * quiet day.
     *
     * @return int[]
     */
    private function relevantProductIds(array $confirmed, array $predicted): array
    {
        $ids = [];

        foreach ([$confirmed, $predicted] as $source) {
            foreach ($source as $byProduct) {
                foreach (array_keys($byProduct) as $productId) {
                    $ids[(int) $productId] = true;
                }
            }
        }

        foreach (ProductionRun::query()->distinct()->pluck('product_id') as $productId) {
            $ids[(int) $productId] = true;
        }

        // Still nothing (a brand-new install): fall back to stocked products so
        // the page has something to show rather than looking broken.
        if (empty($ids)) {
            foreach (Product::query()->where('status', 'active')->limit(10)->pluck('id') as $productId) {
                $ids[(int) $productId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @return string[]
     */
    private function dateRange(Carbon $from, Carbon $to): array
    {
        $dates  = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
