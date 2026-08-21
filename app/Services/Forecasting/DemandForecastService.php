<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates every client's profile into the numbers the business runs on:
 * how many orders arrive each day, how many bottles that is, and how wrong
 * those figures might be.
 *
 * Demand is assembled from three sources that must never be added twice:
 *
 *  COMMITTED   Orders already placed and not yet delivered. Certain, so they
 *              are reported separately rather than predicted.
 *  SUBSCRIBED  Active subscription schedules. Also effectively certain, so
 *              subscribed clients are removed from the statistical model
 *              entirely — a client's contract and their historical habits are
 *              two descriptions of the same demand, and counting both would
 *              inflate every figure they touch.
 *  PREDICTED   Everyone else, via the seasonally-modulated Poisson profiles.
 *
 * The uncertainty band comes from summing per-client variance, not from a
 * percentage rule of thumb. That distinction matters commercially: a day made
 * of forty small independent clients is genuinely more predictable than one
 * made of three large ones, and only a variance-based band says so. Stocking
 * to P90 rather than to the mean is what turns the forecast into protection
 * against stockouts; stocking to P50 guarantees running dry half the time.
 */
class DemandForecastService
{
    public function __construct(
        private readonly ClientDemandModel $clients,
        private readonly SeasonalityService $seasonality,
        private readonly ForecastAccuracyService $accuracy,
    ) {}

    /**
     * @param  array{include_clients?: bool, segments?: string[]}  $options
     * @return array<string, mixed>
     */
    public function forecast(Carbon $from, Carbon $to, array $options = []): array
    {
        $from = $from->copy()->startOfDay();
        $to   = $to->copy()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = (int) config('forecasting.max_horizon_days');
        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $dates = $this->dateRange($from, $to);
        $bias  = $this->accuracy->biasFactor();

        $subscribedClientIds = $this->subscribedClientIds();

        $profiles = $this->clients->profiles()
            ->reject(fn (ClientDemandProfile $p) => $subscribedClientIds->contains($p->clientId));

        if (! empty($options['segments'])) {
            $profiles = $profiles->filter(
                fn (ClientDemandProfile $p) => in_array($p->segment->value, $options['segments'], true)
            );
        }

        $grid = $this->emptyGrid($dates);

        $clientPredictions = [];
        $segmentTotals     = [];
        $productTotals     = [];

        foreach ($profiles as $profile) {
            $this->projectClient($profile, $dates, $from, $bias, $grid, $clientPredictions, $segmentTotals, $productTotals);
        }

        $this->addSubscriptions($dates, $grid, $segmentTotals, $productTotals, $clientPredictions);
        $this->addCommitted($dates, $grid);

        $days = $this->finaliseGrid($grid, $dates);

        return [
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'days'         => $days,
            'segments'     => $this->finaliseSegments($segmentTotals),
            'products'     => $this->finaliseProducts($productTotals),
            'clients'      => ($options['include_clients'] ?? true) ? $clientPredictions : [],
            'totals'       => $this->totals($grid),
            'seasonality'  => $this->seasonality->status(),
            'bias_factor'  => round($bias, 4),
            'model'        => [
                'clients_modelled'   => $profiles->count(),
                'clients_churned'    => $profiles->filter(fn ($p) => $p->churned)->count(),
                'clients_subscribed' => $subscribedClientIds->count(),
            ],
        ];
    }

    /**
     * Walk one client across the horizon, accumulating their contribution.
     *
     * Dates are walked in order so the seasonal exposure since the client's
     * last order can be carried forward incrementally. Recomputing it per day
     * would be quadratic, and it is needed on every single day to keep the
     * renewal suppression honest.
     *
     * @param  string[]  $dates
     */
    private function projectClient(
        ClientDemandProfile $profile,
        array $dates,
        Carbon $from,
        float $bias,
        array &$grid,
        array &$clientPredictions,
        array &$segmentTotals,
        array &$productTotals,
    ): void {
        if ($profile->churned || $profile->baseRate <= 0) {
            return;
        }

        // Exposure already accrued between their last order and the start of
        // the horizon. Without this a client who ordered yesterday would look
        // fully "due" on day one of a horizon that starts next month.
        $exposure = $profile->lastOrderAt
            ? $this->seasonality->frequencyExposure(
                $profile->segment,
                $profile->lastOrderAt->copy()->addDay(),
                $from->copy()->subDay(),
            )
            : 0.0;

        $segmentKey = $profile->segment->value;

        foreach ($dates as $dateString) {
            $date = Carbon::parse($dateString);

            $probability = $profile->probabilityOn($date, $this->seasonality, $exposure);

            // Advance exposure past this day before the next iteration. Each
            // day that passes without an order is another day of opportunity.
            $exposure += $this->seasonality->frequencyIndex($profile->segment, $date);

            if ($probability <= 0.0005) {
                continue;
            }

            $size     = $profile->orderSizeOn($date, $this->seasonality);
            $units    = $probability * $size * $bias;
            $revenue  = $units * $profile->pricePerUnit();
            $orders   = $probability * $bias;
            // Computed inline rather than via unitVarianceOn() so it reuses the
            // renewal-damped probability already in hand instead of recomputing
            // an undamped one.
            $variance = (($probability * (1 - $probability) * $size ** 2) + ($probability * $profile->unitsStdDev ** 2)) * ($bias ** 2);

            $grid[$dateString]['predicted_orders']  += $orders;
            $grid[$dateString]['predicted_units']   += $units;
            $grid[$dateString]['predicted_revenue'] += $revenue;
            $grid[$dateString]['variance']          += $variance;

            $segmentTotals[$segmentKey]['orders']  = ($segmentTotals[$segmentKey]['orders'] ?? 0.0) + $orders;
            $segmentTotals[$segmentKey]['units']   = ($segmentTotals[$segmentKey]['units'] ?? 0.0) + $units;
            $segmentTotals[$segmentKey]['revenue'] = ($segmentTotals[$segmentKey]['revenue'] ?? 0.0) + $revenue;
            $segmentTotals[$segmentKey]['clients'][$profile->clientId] = true;
            $grid[$dateString]['segments'][$segmentKey] = ($grid[$dateString]['segments'][$segmentKey] ?? 0.0) + $units;

            $cell = &$grid[$dateString]['predicted_by_segment'][$segmentKey];
            $cell['orders']  = ($cell['orders'] ?? 0.0) + $orders;
            $cell['units']   = ($cell['units'] ?? 0.0) + $units;
            $cell['revenue'] = ($cell['revenue'] ?? 0.0) + $revenue;
            unset($cell);

            foreach ($profile->basket as $line) {
                $productUnits = $probability * (float) $line['qty']
                    * $this->seasonality->sizeIndex($profile->segment, $date) * $bias;

                if ($productUnits <= 0) {
                    continue;
                }

                $pid = (int) $line['product_id'];
                $productTotals[$pid]['product_id'] = $pid;
                $productTotals[$pid]['name']     ??= $line['name'];
                $productTotals[$pid]['units']      = ($productTotals[$pid]['units'] ?? 0.0) + $productUnits;
                $productTotals[$pid]['revenue']    = ($productTotals[$pid]['revenue'] ?? 0.0) + $productUnits * (float) $line['unit_price'];

                $grid[$dateString]['predicted_by_product'][$pid] =
                    ($grid[$dateString]['predicted_by_product'][$pid] ?? 0.0) + $productUnits;
            }

            // Only days where an order is more likely than not are worth
            // putting in front of staff as a "call this client" item; the long
            // tail of 5% days is real demand in aggregate but noise per row.
            if ($probability >= 0.35) {
                $clientPredictions[] = [
                    'client_id'    => $profile->clientId,
                    'client_name'  => $profile->clientName,
                    'segment'      => $segmentKey,
                    'date'         => $dateString,
                    'probability'  => round($probability, 3),
                    'units'        => round($size, 1),
                    'expected_value' => round($size * $profile->pricePerUnit(), 2),
                    'confidence'   => $profile->confidence,
                    'trend'        => $profile->trend,
                    'cadence_days' => $profile->cadenceDaysOn($date, $this->seasonality),
                    'last_order'   => $profile->lastOrderAt?->toDateString(),
                    'order_count'  => $profile->orderCount,
                    'source'       => 'model',
                    'basket'       => $profile->basket,
                ];
            }
        }
    }

    /**
     * Active subscriptions projected onto the horizon as committed demand.
     *
     * @param  string[]  $dates
     */
    private function addSubscriptions(array $dates, array &$grid, array &$segmentTotals, array &$productTotals, array &$clientPredictions): void
    {
        if (empty($dates)) {
            return;
        }

        $from = Carbon::parse($dates[0]);
        $to   = Carbon::parse(end($dates));

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->with(['items.product:id,name,price,sale_price', 'client:id,name', 'client.userProfile:id,user_id,segment'])
            ->get();

        // Dates a subscription already has a real order for, so a schedule
        // occurrence never lands on top of one.
        $alreadyOrdered = Order::query()
            ->whereNotNull('subscription_id')
            ->whereNotIn('status', [OrderStatus::Cancelled])
            ->whereNotNull('scheduled_delivery_at')
            ->whereBetween('scheduled_delivery_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['subscription_id', 'scheduled_delivery_at'])
            ->map(fn (Order $o) => $o->subscription_id . '|' . $o->scheduled_delivery_at->toDateString())
            ->flip();

        foreach ($subscriptions as $subscription) {
            $segment = $subscription->client?->userProfile?->segment ?? ClientSegment::Unknown;
            $key     = $segment->value;

            $units = 0.0;
            $value = 0.0;
            $lines = [];

            foreach ($subscription->items as $item) {
                $price = (float) ($item->product?->sale_price > 0 ? $item->product->sale_price : $item->product?->price ?? 0);
                $units += (float) $item->quantity;
                $value += (float) $item->quantity * $price;
                $lines[] = [
                    'product_id' => $item->product_id,
                    'name'       => $item->product?->name,
                    'qty'        => (float) $item->quantity,
                    'unit_price' => $price,
                ];
            }

            if ($units <= 0) {
                continue;
            }

            foreach ($subscription->occurrencesBetween($from, $to) as $date) {
                $dateString = $date->toDateString();

                if (! isset($grid[$dateString])) {
                    continue;
                }

                if ($alreadyOrdered->has($subscription->id . '|' . $dateString)) {
                    continue;
                }

                $grid[$dateString]['committed_orders']  += 1;
                $grid[$dateString]['committed_units']   += $units;
                $grid[$dateString]['committed_revenue'] += $value;
                $grid[$dateString]['segments'][$key]     = ($grid[$dateString]['segments'][$key] ?? 0.0) + $units;

                $segmentTotals[$key]['orders']  = ($segmentTotals[$key]['orders'] ?? 0.0) + 1;
                $segmentTotals[$key]['units']   = ($segmentTotals[$key]['units'] ?? 0.0) + $units;
                $segmentTotals[$key]['revenue'] = ($segmentTotals[$key]['revenue'] ?? 0.0) + $value;
                $segmentTotals[$key]['clients'][$subscription->user_id] = true;

                foreach ($lines as $line) {
                    $pid = (int) $line['product_id'];
                    $productTotals[$pid]['product_id'] = $pid;
                    $productTotals[$pid]['name']     ??= $line['name'];
                    $productTotals[$pid]['units']      = ($productTotals[$pid]['units'] ?? 0.0) + $line['qty'];
                    $productTotals[$pid]['revenue']    = ($productTotals[$pid]['revenue'] ?? 0.0) + $line['qty'] * $line['unit_price'];
                }

                $clientPredictions[] = [
                    'client_id'      => $subscription->user_id,
                    'client_name'    => $subscription->client?->name ?? '—',
                    'segment'        => $key,
                    'date'           => $dateString,
                    'probability'    => 1.0,
                    'units'          => round($units, 1),
                    'expected_value' => round($value, 2),
                    'confidence'     => 'high',
                    'trend'          => 'stable',
                    'cadence_days'   => null,
                    'last_order'     => null,
                    'order_count'    => 0,
                    'source'         => 'subscription',
                    'basket'         => $lines,
                ];
            }
        }
    }

    /**
     * Orders already placed and awaiting delivery inside the horizon.
     *
     * @param  string[]  $dates
     */
    private function addCommitted(array $dates, array &$grid): void
    {
        if (empty($dates)) {
            return;
        }

        $from = Carbon::parse($dates[0])->startOfDay();
        $to   = Carbon::parse(end($dates))->endOfDay();

        $orders = Order::query()
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            // Subscription-generated orders are included. They must be: the
            // generator advances next_delivery_at PAST the delivery it just
            // created, so that delivery no longer appears in the schedule.
            // Excluding it here would drop it from the forecast entirely.
            // addSubscriptions() skips schedule dates that already have an
            // order, so nothing is counted twice.
            ->with(['items:id,order_id,product_id,quantity,unit_price,is_gift'])
            ->get(['id', 'user_id', 'status', 'scheduled_delivery_at', 'created_at']);

        // Segment lookup in one query rather than eager-loading two levels of
        // relation onto a column-restricted select.
        $segments = DB::table('user_profiles')
            ->whereIn('user_id', $orders->pluck('user_id')->unique())
            ->pluck('segment', 'user_id');

        foreach ($orders as $order) {
            // An open order with no delivery date is still real demand; it
            // belongs on the first day of the horizon rather than nowhere.
            $date = $order->scheduled_delivery_at
                ? Carbon::parse($order->scheduled_delivery_at)->startOfDay()
                : $from->copy();

            if ($date->lt($from)) {
                $date = $from->copy();
            }

            if ($date->gt($to)) {
                continue;
            }

            $dateString = $date->toDateString();

            if (! isset($grid[$dateString])) {
                continue;
            }

            $units   = 0.0;
            $revenue = 0.0;

            foreach ($order->items as $item) {
                if ($item->is_gift) {
                    continue;
                }
                $units   += (float) $item->quantity;
                $revenue += (float) $item->quantity * (float) $item->unit_price;
            }

            $segmentKey = $segments[$order->user_id] ?? ClientSegment::Unknown->value;

            $grid[$dateString]['committed_orders']  += 1;
            $grid[$dateString]['committed_units']   += $units;
            $grid[$dateString]['committed_revenue'] += $revenue;
            $grid[$dateString]['segments'][$segmentKey] = ($grid[$dateString]['segments'][$segmentKey] ?? 0.0) + $units;
        }
    }

    /**
     * @param  string[]  $dates
     * @return array<string, array<string, mixed>>
     */
    private function emptyGrid(array $dates): array
    {
        $grid = [];

        foreach ($dates as $date) {
            $grid[$date] = [
                'predicted_orders'  => 0.0,
                'predicted_units'   => 0.0,
                'predicted_revenue' => 0.0,
                'committed_orders'  => 0.0,
                'committed_units'   => 0.0,
                'committed_revenue' => 0.0,
                'variance'          => 0.0,
                // Predicted and committed segment demand are kept apart
                // because only the predicted half is scored for accuracy —
                // grading the forecast on orders that were already on the
                // books would flatter it with facts rather than forecasts.
                'predicted_by_segment' => [],
                // Per-product, per-day predicted units. The production plan
                // needs "how many 19L bottles on Tuesday", which the
                // window-wide product totals cannot answer.
                'predicted_by_product' => [],
                'segments'          => [],
            ];
        }

        return $grid;
    }

    /**
     * @param  string[]  $dates
     * @return array<int, array<string, mixed>>
     */
    private function finaliseGrid(array $grid, array $dates): array
    {
        $z    = (float) config('forecasting.band_z');
        $days = [];

        foreach ($dates as $date) {
            $cell = $grid[$date];

            $predictedUnits = $cell['predicted_units'];
            $stdDev         = sqrt(max(0.0, $cell['variance']));
            $margin         = $z * $stdDev;

            // The band applies to the predicted part only. Committed demand is
            // already known, so widening the interval around it would overstate
            // uncertainty exactly where the business has none.
            $totalUnits = $predictedUnits + $cell['committed_units'];

            $days[] = [
                'date'              => $date,
                'weekday'           => Carbon::parse($date)->dayOfWeekIso,
                'predicted_orders'  => round($cell['predicted_orders'], 2),
                'committed_orders'  => (int) round($cell['committed_orders']),
                'orders'            => round($cell['predicted_orders'] + $cell['committed_orders'], 2),
                'predicted_units'   => round($predictedUnits, 1),
                'committed_units'   => round($cell['committed_units'], 1),
                'units'             => round($totalUnits, 1),
                'units_p10'         => round(max(0.0, $cell['committed_units'] + $predictedUnits - $margin), 1),
                'units_p90'         => round($cell['committed_units'] + $predictedUnits + $margin, 1),
                'revenue'           => round($cell['predicted_revenue'] + $cell['committed_revenue'], 2),
                'segments'          => array_map(fn ($v) => round($v, 1), $cell['segments']),
                'predicted_by_segment' => $cell['predicted_by_segment'],
                'predicted_by_product' => array_map(fn ($v) => round($v, 2), $cell['predicted_by_product']),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finaliseSegments(array $segmentTotals): array
    {
        $out = [];

        foreach ($segmentTotals as $key => $totals) {
            $segment = ClientSegment::tryFrom($key) ?? ClientSegment::Unknown;

            $out[] = [
                'segment' => $key,
                'label'   => $segment->label(),
                'orders'  => round($totals['orders'] ?? 0, 1),
                'units'   => round($totals['units'] ?? 0, 1),
                'revenue' => round($totals['revenue'] ?? 0, 2),
                'clients' => count($totals['clients'] ?? []),
            ];
        }

        usort($out, fn ($a, $b) => $b['units'] <=> $a['units']);

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finaliseProducts(array $productTotals): array
    {
        $out = array_map(fn ($p) => [
            'product_id' => $p['product_id'],
            'name'       => $p['name'] ?? null,
            'units'      => round($p['units'] ?? 0, 1),
            'revenue'    => round($p['revenue'] ?? 0, 2),
        ], array_values($productTotals));

        usort($out, fn ($a, $b) => $b['units'] <=> $a['units']);

        return $out;
    }

    /**
     * Horizon totals, summed from the raw grid rather than from the rounded
     * day rows.
     *
     * Rounding first and summing after is quietly destructive here: a client
     * contributing 0.03 bottles a day rounds to 0.0 on every single day, so a
     * book with a long tail of occasional buyers reports its entire tail as
     * zero demand. The day rows stay rounded for display; the totals do not.
     *
     * @param  array<string, array<string, mixed>>  $grid
     * @return array<string, float>
     */
    private function totals(array $grid): array
    {
        $predictedOrders = 0.0;
        $committedOrders = 0.0;
        $units           = 0.0;
        $revenue         = 0.0;
        $variance        = 0.0;

        foreach ($grid as $cell) {
            $predictedOrders += $cell['predicted_orders'];
            $committedOrders += $cell['committed_orders'];
            $units           += $cell['predicted_units'] + $cell['committed_units'];
            $revenue         += $cell['predicted_revenue'] + $cell['committed_revenue'];
            $variance        += $cell['variance'];
        }

        // Independent daily errors partly cancel, so it is the variances that
        // add across the horizon, not the standard deviations. Summing each
        // day's P10 instead would overstate a 30-day band by roughly 5x.
        $margin = (float) config('forecasting.band_z') * sqrt(max(0.0, $variance));

        return [
            'orders'           => round($predictedOrders + $committedOrders, 1),
            'predicted_orders' => round($predictedOrders, 1),
            'committed_orders' => (int) round($committedOrders),
            'units'            => round($units, 1),
            'units_p10'        => round(max(0.0, $units - $margin), 1),
            'units_p90'        => round($units + $margin, 1),
            'revenue'          => round($revenue, 2),
        ];
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

    /**
     * @return Collection<int, int>
     */
    private function subscribedClientIds(): Collection
    {
        return Subscription::query()->where('status', 'active')->pluck('user_id')->unique()->values();
    }
}
