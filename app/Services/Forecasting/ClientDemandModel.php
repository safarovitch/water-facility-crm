<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw order history into per-client ClientDemandProfile objects.
 *
 * The estimator is a recency-weighted, seasonally-adjusted Poisson rate with a
 * Gamma prior drawn from the client's own segment. Three design decisions in
 * here matter more than the rest:
 *
 * 1. RATE, NOT GAP. The previous forecaster took the median gap between a
 *    client's orders. A median gap cannot express "this client orders weekly
 *    for nine months and not at all in July" — it just reports a longer
 *    average and smears the summer across the whole year. A rate can, because
 *    the calendar multiplies it day by day.
 *
 * 2. SEASONAL EXPOSURE, NOT ELAPSED DAYS. Dividing orders by days would let
 *    the season the client happened to be observed in leak into their base
 *    rate: a client watched only over summer would look permanently thirsty.
 *    Dividing by the seasonal index summed over those days removes it, leaving
 *    a rate that means the same thing in January and July.
 *
 * 3. SHRINKAGE INSTEAD OF EXCLUSION. Clients with one or two orders are not
 *    dropped. Occasional buyers are a real and profitable part of the book,
 *    and excluding them makes the aggregate forecast structurally too low.
 *    They are pulled toward their segment's average rate by a Gamma prior and
 *    contribute a small, honest amount of expected demand.
 */
class ClientDemandModel
{
    public function __construct(private readonly SeasonalityService $seasonality) {}

    /**
     * Build a profile for every client with any usable history.
     *
     * @return Collection<int, ClientDemandProfile>
     */
    public function profiles(?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();

        $clients = User::query()
            ->whereHas('orders', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled))
            ->with([
                'userProfile:id,user_id,type,segment',
                'orders' => fn ($q) => $q
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->orderBy('created_at')
                    ->with([
                        'items:id,order_id,product_id,quantity,unit_price,is_gift',
                        'items.product:id,name,price,sale_price',
                    ]),
            ])
            ->get(['id', 'name']);

        // Pass one: raw counts and seasonal exposure per client. The segment
        // averages needed for shrinkage are themselves derived from these, so
        // they cannot be computed in the same loop that consumes them.
        $raw = [];

        foreach ($clients as $client) {
            $measured = $this->measure($client, $asOf);

            if ($measured !== null) {
                $raw[$client->id] = $measured;
            }
        }

        $segmentRates = $this->segmentRates($raw);

        // Pass two: apply the segment prior and assemble the profiles.
        return collect($raw)
            ->map(fn (array $m) => $this->assemble($m, $segmentRates, $asOf))
            ->filter()
            ->values();
    }

    /**
     * Collect the sufficient statistics for one client.
     *
     * @return array<string, mixed>|null
     */
    private function measure(User $client, Carbon $asOf): ?array
    {
        /** @var Collection<int, Order> $orders */
        $orders = $client->orders->filter(fn (Order $o) => $o->created_at !== null)->values();

        if ($orders->isEmpty()) {
            return null;
        }

        $segment  = $client->userProfile?->segment ?? ClientSegment::Unknown;
        $halfLife = max(1.0, (float) config('forecasting.recency_half_life_days'));

        $first = $orders->first()->created_at->copy()->startOfDay();
        $last  = $orders->last()->created_at->copy()->startOfDay();

        // Exposure runs from the day after the first order, because the wait
        // that produced that first order was never observed. Counting it would
        // bias every client's rate downward, most severely the newest ones.
        $exposureStart = $first->copy()->addDay();
        $exposure      = $this->seasonality->frequencyExposure($segment, $exposureStart, $asOf, $asOf, $halfLife);

        // Matching numerator: every order except the first, recency-weighted.
        $weightedOrders = 0.0;
        foreach ($orders->slice(1) as $order) {
            $weightedOrders += $this->recencyWeight($order->created_at, $asOf, $halfLife);
        }

        // A second rate measured only over the span the client was actually
        // ordering in, ignoring any silence since. Churn detection needs this
        // rather than the blended rate: the trailing silence depresses the
        // blended rate, so comparing the silence against it is circular and a
        // long-dormant client can never trip the test.
        $activeExposure = $this->seasonality->frequencyExposure($segment, $exposureStart, $last, $asOf, $halfLife);
        $activeRate     = $activeExposure > 0 ? $weightedOrders / $activeExposure : 0.0;

        [$unitsPerOrder, $unitsStdDev, $basket, $orderValue] = $this->basketStatistics($orders, $segment, $asOf, $halfLife);

        return [
            'client_id'       => $client->id,
            'client_name'     => $client->name,
            'segment'         => $segment,
            'weighted_orders' => $weightedOrders,
            'exposure'        => $exposure,
            'order_count'     => $orders->count(),
            'first_order_at'  => $first,
            'last_order_at'   => $last,
            'units_per_order' => $unitsPerOrder,
            'units_std_dev'   => $unitsStdDev,
            'basket'          => $basket,
            'order_value'     => $orderValue,
            'intervals'       => $this->intervals($orders),
            'active_rate'     => $activeRate,
        ];
    }

    /**
     * Segment-level average rate, used as the prior mean for sparse clients.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<string, float>
     */
    private function segmentRates(array $raw): array
    {
        $totals = [];

        foreach ($raw as $m) {
            $key = $m['segment']->value;
            $totals[$key]['orders']   = ($totals[$key]['orders'] ?? 0.0) + $m['weighted_orders'];
            $totals[$key]['exposure'] = ($totals[$key]['exposure'] ?? 0.0) + $m['exposure'];
        }

        $rates = [];
        $globalOrders   = array_sum(array_column($totals, 'orders'));
        $globalExposure = array_sum(array_column($totals, 'exposure'));
        $globalRate     = $globalExposure > 0 ? $globalOrders / $globalExposure : 0.02;

        foreach ($totals as $segment => $t) {
            $rates[$segment] = $t['exposure'] > 0 ? $t['orders'] / $t['exposure'] : $globalRate;
        }

        // A segment nobody has ordered in yet still needs a prior mean; the
        // business-wide rate is a better guess than zero.
        foreach (ClientSegment::cases() as $segment) {
            $rates[$segment->value] ??= $globalRate;
            if ($rates[$segment->value] <= 0) {
                $rates[$segment->value] = max($globalRate, 1e-4);
            }
        }

        return $rates;
    }

    /**
     * @param  array<string, mixed>  $m
     * @param  array<string, float>  $segmentRates
     */
    private function assemble(array $m, array $segmentRates, Carbon $asOf): ?ClientDemandProfile
    {
        /** @var ClientSegment $segment */
        $segment = $m['segment'];

        $segmentRate  = $segmentRates[$segment->value] ?? 0.02;
        $priorOrders  = (float) config('forecasting.sparse_client_prior_orders');

        // Gamma-Poisson posterior mean. beta is the prior's exposure in days,
        // chosen so the prior carries exactly `priorOrders` observations of
        // weight; with plenty of real orders the prior washes out entirely.
        $beta = $segmentRate > 0 ? $priorOrders / $segmentRate : 0.0;

        $denominator = $m['exposure'] + $beta;
        $baseRate    = $denominator > 0 ? ($m['weighted_orders'] + $priorOrders) / $denominator : 0.0;

        if ($baseRate <= 0) {
            return null;
        }

        // Compared against the rate the client kept while active, not the
        // blended one, for the reason given in measure().
        $churned = $this->isChurned($segment, $m['last_order_at'], $asOf, max($m['active_rate'], $baseRate));

        return new ClientDemandProfile(
            clientId:      $m['client_id'],
            clientName:    $m['client_name'],
            segment:       $segment,
            baseRate:      $baseRate,
            unitsPerOrder: $m['units_per_order'],
            unitsStdDev:   $m['units_std_dev'],
            basket:        $m['basket'],
            orderValue:    $m['order_value'],
            lastOrderAt:   $m['last_order_at'],
            orderCount:    $m['order_count'],
            confidence:    $this->confidence($m),
            trend:         $this->trend($m['intervals']),
            churned:       $churned,
            dataWeight:    $m['exposure'] > 0 ? min(1.0, $m['exposure'] / ($m['exposure'] + $beta)) : 0.0,
        );
    }

    /**
     * Has this client gone quiet, allowing for the season?
     *
     * Elapsed days are the wrong yardstick. A school silent since 20 May has
     * not churned on 1 August — it is on holiday, and its expected order count
     * over that stretch is near zero. Measuring the silence in *expected
     * orders* rather than days makes the test automatically lenient during a
     * segment's off-season and strict during its peak.
     */
    private function isChurned(ClientSegment $segment, Carbon $lastOrderAt, Carbon $asOf, float $activeRate): bool
    {
        if ($lastOrderAt->gte($asOf)) {
            return false;
        }

        // No recency decay here: this measures elapsed opportunity, not the
        // weight of evidence, so every missed day must count in full.
        $missed = $this->seasonality->frequencyExposure($segment, $lastOrderAt->copy()->addDay(), $asOf);

        return ($missed * $activeRate) > (float) config('forecasting.churn_cadence_multiple');
    }

    /**
     * Exponential recency weight: an order one half-life old counts half.
     */
    private function recencyWeight(Carbon $date, Carbon $asOf, float $halfLife): float
    {
        $ageDays = max(0.0, (float) $date->copy()->startOfDay()->diffInDays($asOf, absolute: false));

        return 2 ** (-$ageDays / $halfLife);
    }

    /**
     * De-seasonalised order size, its dispersion, and the typical basket.
     *
     * Sizes are divided by the size half of the seasonal index before being
     * averaged, for the same reason rates are: an order placed in July is
     * expected to be larger, and that expectation belongs in the calendar, not
     * in the client's baseline.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{0: float, 1: float, 2: array, 3: float}
     */
    private function basketStatistics(Collection $orders, ClientSegment $segment, Carbon $asOf, float $halfLife): array
    {
        $sizes   = [];
        $values  = [];
        $weights = [];
        $byProduct = [];

        foreach ($orders as $order) {
            $sizeFactor = $this->seasonality->sizeIndex($segment, $order->created_at) ?: 1.0;
            $weight     = $this->recencyWeight($order->created_at, $asOf, $halfLife);

            $units = 0.0;
            $value = 0.0;

            foreach ($order->items as $item) {
                if ($item->is_gift) {
                    continue;
                }

                $quantity = (float) $item->quantity;
                $units   += $quantity;
                $value   += $quantity * (float) $item->unit_price;

                if ($item->product) {
                    $key = $item->product_id;
                    $byProduct[$key]['product_id'] ??= $item->product_id;
                    $byProduct[$key]['name']       ??= $item->product->name;
                    $byProduct[$key]['unit_price'] ??= (float) ($item->product->sale_price > 0 ? $item->product->sale_price : $item->product->price);
                    $byProduct[$key]['weighted_qty'] = ($byProduct[$key]['weighted_qty'] ?? 0.0) + ($quantity / $sizeFactor) * $weight;
                    $byProduct[$key]['weight']       = ($byProduct[$key]['weight'] ?? 0.0) + $weight;
                }
            }

            if ($units > 0) {
                $sizes[]   = $units / $sizeFactor;
                $values[]  = $value / $sizeFactor;
                $weights[] = $weight;
            }
        }

        if (empty($sizes)) {
            return [0.0, 0.0, [], 0.0];
        }

        $unitsPerOrder = $this->weightedMean($sizes, $weights);
        $orderValue    = $this->weightedMean($values, $weights);

        // Dispersion of order size feeds the P10/P90 band. A client who
        // sometimes takes 2 bottles and sometimes 20 deserves a wider band
        // than one who always takes 6, even at identical averages.
        $variance = 0.0;
        $weightSum = array_sum($weights);
        foreach ($sizes as $i => $size) {
            $variance += $weights[$i] * ($size - $unitsPerOrder) ** 2;
        }
        $stdDev = $weightSum > 0 ? sqrt($variance / $weightSum) : 0.0;

        $totalWeight = array_sum(array_column($byProduct, 'weight')) ?: 1.0;

        $basket = [];
        foreach ($byProduct as $data) {
            // Average units of this product per order placed, so the basket
            // sums back to unitsPerOrder rather than to a per-order total that
            // ignores the orders the product was absent from.
            $qty = $data['weighted_qty'] / max(1e-9, array_sum($weights));

            if ($qty <= 0) {
                continue;
            }

            $basket[] = [
                'product_id' => $data['product_id'],
                'name'       => $data['name'],
                'qty'        => round($qty, 3),
                'unit_price' => $data['unit_price'],
            ];
        }

        return [$unitsPerOrder, $stdDev, $basket, $orderValue];
    }

    /**
     * @param  float[]  $values
     * @param  float[]  $weights
     */
    private function weightedMean(array $values, array $weights): float
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            return count($values) ? array_sum($values) / count($values) : 0.0;
        }

        $total = 0.0;
        foreach ($values as $i => $value) {
            $total += $value * $weights[$i];
        }

        return $total / $weightSum;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return int[]
     */
    private function intervals(Collection $orders): array
    {
        $intervals = [];
        $previous  = null;

        foreach ($orders as $order) {
            $current = $order->created_at->copy()->startOfDay();

            if ($previous !== null) {
                $gap = (int) $previous->diffInDays($current);
                if ($gap > 0) {
                    $intervals[] = $gap;
                }
            }

            $previous = $current;
        }

        return $intervals;
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function confidence(array $m): string
    {
        $intervals = $m['intervals'];
        $count     = count($intervals);

        if ($count < 2 || $m['order_count'] < (int) config('forecasting.min_orders_for_client_model')) {
            return 'low';
        }

        $mean = array_sum($intervals) / $count;

        if ($mean <= 0) {
            return 'low';
        }

        $variance = 0.0;
        foreach ($intervals as $value) {
            $variance += ($value - $mean) ** 2;
        }

        $cv = sqrt($variance / $count) / $mean;

        if ($m['order_count'] >= 6 && $cv < 0.35) {
            return 'high';
        }

        return $cv < 0.6 ? 'medium' : 'low';
    }

    /**
     * @param  int[]  $intervals
     */
    private function trend(array $intervals): string
    {
        if (count($intervals) < 4) {
            return 'stable';
        }

        $mid    = (int) ceil(count($intervals) / 2);
        $first  = array_slice($intervals, 0, $mid);
        $second = array_slice($intervals, $mid);

        $firstMean  = array_sum($first) / count($first);
        $secondMean = array_sum($second) / count($second);

        if ($firstMean <= 0) {
            return 'stable';
        }

        $ratio = $secondMean / $firstMean;

        return match (true) {
            $ratio < 0.85 => 'up',
            $ratio > 1.15 => 'down',
            default       => 'stable',
        };
    }
}
