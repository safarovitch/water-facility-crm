<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\DemandSeasonality;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the per-segment monthly demand curve.
 *
 * The whole point of this class is that it never claims to know more than it
 * does. With little history it serves the hard-coded priors from
 * ClientSegment; as history accumulates it measures the real curve and shrinks
 * the prior out of the way in proportion to how much evidence there is. There
 * is no flag day and no manual migration between the two regimes — the same
 * blend formula covers both ends, because with zero observations it reduces
 * exactly to the prior.
 *
 * Two measurement traps are handled explicitly:
 *
 *  1. GROWTH LOOKS LIKE SEASONALITY. A company that doubled its client base
 *     over the year makes December look "seasonally strong" when it is merely
 *     bigger. Demand is therefore measured per active client and then divided
 *     by a centred 12-month moving average (classical ratio-to-moving-average
 *     decomposition), which removes level and trend and leaves only the
 *     repeating shape.
 *
 *  2. ONE FREAK MONTH BECOMES PERMANENT. A single bulk order in a thin
 *     segment could otherwise set a 6x index forever. Cells below
 *     `min_orders_per_cell` are ignored outright, every index is clamped, and
 *     shrinkage keeps a lightly-observed month close to its prior.
 *
 * Indices are multiplicative around 1.0 and always renormalised so a curve
 * averages exactly 1.0 across the year. That invariant is what lets the rest
 * of the forecaster multiply a base rate by an index without inflating or
 * deflating anyone's annual total.
 */
class SeasonalityService
{
    private const CACHE_KEY = 'forecasting.seasonality.curves';
    private const CACHE_TTL = 3600;

    /**
     * Seasonal index for a segment in a calendar month (1-12).
     */
    public function indexFor(ClientSegment $segment, int $month): float
    {
        $curves = $this->curves();
        $month  = max(1, min(12, $month));

        return $curves[$segment->value][$month] ?? $segment->priorIndexFor($month);
    }

    public function indexForDate(ClientSegment $segment, Carbon $date): float
    {
        return $this->indexFor($segment, (int) $date->month);
    }

    /**
     * Full 12-month curve for a segment, keyed 1-12.
     *
     * @return array<int, float>
     */
    public function curveFor(ClientSegment $segment): array
    {
        $curves = $this->curves();

        if (isset($curves[$segment->value])) {
            return $curves[$segment->value];
        }

        $curve = [];
        foreach (range(1, 12) as $month) {
            $curve[$month] = $segment->priorIndexFor($month);
        }

        return $curve;
    }

    /**
     * All curves, keyed [segment][month]. Cached; recompute() flushes.
     *
     * @return array<string, array<int, float>>
     */
    public function curves(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = DemandSeasonality::all();

            $curves = [];

            foreach (ClientSegment::cases() as $segment) {
                foreach (range(1, 12) as $month) {
                    $curves[$segment->value][$month] = $segment->priorIndexFor($month);
                }
            }

            foreach ($rows as $row) {
                if (! $row->segment instanceof ClientSegment) {
                    continue;
                }
                $curves[$row->segment->value][$row->month] = (float) $row->index;
            }

            return $curves;
        });
    }

    /**
     * The frequency half of a month's seasonal index.
     *
     * A seasonal swing is split between ordering more often and ordering more
     * per visit (see forecasting.seasonal_frequency_share); this returns the
     * "more often" half, which is the factor that scales a Poisson rate.
     */
    public function frequencyIndex(ClientSegment $segment, Carbon $date): float
    {
        $share = (float) config('forecasting.seasonal_frequency_share');

        return $this->indexForDate($segment, $date) ** $share;
    }

    /**
     * The size half of a month's seasonal index.
     */
    public function sizeIndex(ClientSegment $segment, Carbon $date): float
    {
        $share = (float) config('forecasting.seasonal_frequency_share');

        return $this->indexForDate($segment, $date) ** (1 - $share);
    }

    /**
     * Sum of the frequency index over an inclusive date range.
     *
     * This is the denominator that converts a raw order count into a rate that
     * means the same thing in any month, and the numerator-free measure of
     * "how much ordering opportunity has passed" used for churn. Whole months
     * are accumulated in one step because the index is constant within a
     * month, so a multi-year range costs a few dozen iterations rather than
     * thousands.
     *
     * @param  Carbon|null  $asOf       reference point for recency decay
     * @param  float|null   $halfLife   decay half-life in days; null disables decay
     */
    public function frequencyExposure(
        ClientSegment $segment,
        Carbon $from,
        Carbon $to,
        ?Carbon $asOf = null,
        ?float $halfLife = null,
    ): float {
        if ($from->gt($to)) {
            return 0.0;
        }

        $decay = $asOf !== null && $halfLife !== null && $halfLife > 0;

        $total  = 0.0;
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $chunkEnd = $monthEnd->lte($end) ? $monthEnd : $end->copy();
            $days     = (int) $cursor->diffInDays($chunkEnd) + 1;

            $index = $this->frequencyIndex($segment, $cursor);

            if ($decay) {
                // One weight for the chunk, taken at its midpoint. Exact
                // per-day decay would change the result by a fraction of a
                // percent over a month and cost 30x the iterations.
                $midpoint = $cursor->copy()->addDays(intdiv($days, 2));
                $age      = max(0.0, (float) $midpoint->diffInDays($asOf, absolute: false));
                $index   *= 2 ** (-$age / $halfLife);
            }

            $total += $days * $index;

            $cursor = $chunkEnd->copy()->addDay()->startOfDay();
        }

        return $total;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * How much usable history exists, in whole months.
     */
    public function historyMonths(): int
    {
        $first = DB::table('orders')->where('status', '!=', OrderStatus::Cancelled)->min('created_at');

        if (! $first) {
            return 0;
        }

        return (int) floor(Carbon::parse($first)->startOfMonth()->diffInMonths(now()->startOfMonth())) + 1;
    }

    public function learningEnabled(): bool
    {
        return $this->historyMonths() >= (int) config('forecasting.min_months_for_learning');
    }

    /**
     * Human-readable state of the seasonality layer, for the dashboard.
     *
     * @return array{months_of_history: int, months_required: int, learning: bool, sources: array<string, int>}
     */
    public function status(): array
    {
        $required = (int) config('forecasting.min_months_for_learning');
        $months   = $this->historyMonths();

        return [
            'months_of_history' => $months,
            'months_required'   => $required,
            'months_remaining'  => max(0, $required - $months),
            'learning'          => $months >= $required,
            'sources'           => DemandSeasonality::query()
                ->selectRaw('source, count(*) as c')
                ->groupBy('source')
                ->pluck('c', 'source')
                ->map(fn ($c) => (int) $c)
                ->all(),
        ];
    }

    /**
     * Recompute every non-manual index from history and persist.
     *
     * Safe to run at any time: below the learning threshold it simply writes
     * the priors back, so a nightly schedule needs no guard of its own.
     *
     * @return array{learning: bool, segments: array<string, array{updated: int, observed_cells: int}>}
     */
    public function recompute(): array
    {
        $learning = $this->learningEnabled();
        $observed = $learning ? $this->observedIndices() : [];

        $report = [];

        foreach (ClientSegment::cases() as $segment) {
            $report[$segment->value] = $this->persistSegment($segment, $observed[$segment->value] ?? []);
        }

        $this->flush();

        return ['learning' => $learning, 'segments' => $report];
    }

    /**
     * Blend prior and observation for one segment, renormalise, and store.
     *
     * @param  array<int, array{index: float, orders: int}>  $observed  keyed by month
     * @return array{updated: int, observed_cells: int}
     */
    private function persistSegment(ClientSegment $segment, array $observed): array
    {
        $floor      = (float) config('forecasting.index_floor');
        $ceiling    = (float) config('forecasting.index_ceiling');
        $minOrders  = (int) config('forecasting.min_orders_per_cell');
        $k          = $segment->priorStrength();

        $existing = DemandSeasonality::where('segment', $segment->value)->get()->keyBy('month');

        $auto      = [];   // month => blended index awaiting normalisation
        $manual    = [];   // month => index fixed by a human, left untouched
        $meta      = [];   // month => [orders, observedIndex]

        foreach (range(1, 12) as $month) {
            $row = $existing->get($month);

            if ($row && $row->source === 'manual') {
                $manual[$month] = (float) $row->index;
                continue;
            }

            $prior  = $segment->priorIndexFor($month);
            $cell   = $observed[$month] ?? null;
            $orders = $cell['orders'] ?? 0;

            if ($cell === null || $orders < $minOrders) {
                // Not enough evidence to move off the prior at all.
                $auto[$month] = $prior;
                $meta[$month] = [0, null];
                continue;
            }

            // Shrinkage: with n observations and prior weight k, the estimate
            // sits n/(n+k) of the way from prior to observation. At n = 0 this
            // is exactly the prior, which is why one formula covers both the
            // no-history and rich-history regimes.
            $blended = (($orders * $cell['index']) + ($k * $prior)) / ($orders + $k);

            $auto[$month] = max($floor, min($ceiling, $blended));
            $meta[$month] = [$orders, $cell['index']];
        }

        // Renormalise so the year still averages 1.0. Manual values are held
        // fixed — overriding them is the entire point — and the automatic ones
        // absorb the difference.
        $sumManual = array_sum($manual);
        $sumAuto   = array_sum($auto);

        if ($sumAuto > 0) {
            $scale = (12.0 - $sumManual) / $sumAuto;

            // A pathological set of manual overrides (summing above 12) would
            // demand a negative scale; leave the curve unnormalised rather than
            // flip signs, and let the clamp keep it sane.
            if ($scale > 0) {
                foreach ($auto as $month => $value) {
                    $auto[$month] = max($floor, min($ceiling, $value * $scale));
                }
            }
        }

        $updated = 0;

        foreach ($auto as $month => $index) {
            [$orders, $observedIndex] = $meta[$month] ?? [0, null];

            $source = match (true) {
                $orders === 0                => 'prior',
                $orders >= (int) ($k * 3)    => 'learned',
                default                      => 'blended',
            };

            DemandSeasonality::updateOrCreate(
                ['segment' => $segment->value, 'month' => $month],
                [
                    'index'          => round($index, 4),
                    'source'         => $source,
                    'sample_size'    => $orders,
                    'observed_index' => $observedIndex !== null ? round($observedIndex, 4) : null,
                ],
            );

            $updated++;
        }

        return [
            'updated'        => $updated,
            'observed_cells' => count(array_filter($meta, fn ($m) => $m[0] > 0)),
        ];
    }

    /**
     * Measure the seasonal shape from history by ratio-to-moving-average.
     *
     * @return array<string, array<int, array{index: float, orders: int}>>
     */
    private function observedIndices(): array
    {
        $series = $this->monthlySeries();
        $out    = [];

        foreach ($series as $segmentValue => $months) {
            ksort($months);

            $keys = array_keys($months);           // 'YYYY-MM', ascending
            $n    = count($keys);

            if ($n < 13) {
                continue;
            }

            // Centred 12-month moving average of demand-per-active-client.
            // A 12-term average of a 12-month cycle has no seasonal content
            // left in it, so dividing by it isolates the season. The average
            // is centred by averaging two adjacent 12-month windows, which is
            // why the first and last six months produce no ratio.
            $ratios = [];

            for ($i = 6; $i < $n - 6; $i++) {
                $window = 0.0;
                for ($j = $i - 6; $j <= $i + 6; $j++) {
                    $weight = ($j === $i - 6 || $j === $i + 6) ? 0.5 : 1.0;
                    $window += $months[$keys[$j]]['rate'] * $weight;
                }
                $trend = $window / 12.0;

                if ($trend <= 0) {
                    continue;
                }

                $month = (int) substr($keys[$i], 5, 2);
                $ratios[$month][] = [
                    'ratio'  => $months[$keys[$i]]['rate'] / $trend,
                    'orders' => $months[$keys[$i]]['orders'],
                ];
            }

            if (empty($ratios)) {
                continue;
            }

            // Median over years for each calendar month: one anomalous July
            // out of three cannot drag the July index.
            $raw = [];
            foreach ($ratios as $month => $entries) {
                $values = array_column($entries, 'ratio');
                $raw[$month] = [
                    'index'  => $this->median($values),
                    'orders' => (int) array_sum(array_column($entries, 'orders')),
                ];
            }

            // Normalise the measured shape to mean 1.0 over the months we could
            // actually measure, so it is directly comparable with the prior.
            $mean = array_sum(array_column($raw, 'index')) / max(1, count($raw));

            if ($mean > 0) {
                foreach ($raw as $month => $cell) {
                    $raw[$month]['index'] = $cell['index'] / $mean;
                }
            }

            $out[$segmentValue] = $raw;
        }

        return $out;
    }

    /**
     * Units ordered per enrolled client per calendar month, per segment.
     *
     * The denominator is the number of clients in the segment who ordered at
     * least once in the trailing twelve months — the roster the business could
     * plausibly have sold to that month — NOT the number who actually ordered.
     *
     * That distinction is the whole school case. Schools do not order smaller
     * amounts in July; they stop ordering. Dividing by the clients who ordered
     * would compute "each of the two schools that ordered took its usual four
     * bottles" and report July as a perfectly normal month. Dividing by the
     * roster reports what actually happened: demand collapsed. The same logic
     * catches offices thinning out in August.
     *
     * @return array<string, array<string, array{rate: float, orders: int}>>
     */
    private function monthlySeries(): array
    {
        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'orders.user_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled)
            ->where('order_items.is_gift', '=', false)
            ->select(
                'orders.id as order_id',
                'orders.user_id',
                'orders.created_at',
                'user_profiles.segment as segment',
                'order_items.quantity as quantity',
            )
            ->orderBy('orders.created_at')
            ->cursor();

        /** @var array<string, array<string, array{units: float, orders: array<int, true>, clients: array<int, true>}>> $raw */
        $raw = [];

        foreach ($rows as $row) {
            $segment = $row->segment ?: ClientSegment::Unknown->value;
            $ym      = Carbon::parse($row->created_at)->format('Y-m');

            $cell = &$raw[$segment][$ym];
            $cell['units'] = ($cell['units'] ?? 0.0) + (float) $row->quantity;

            // The order_items join fans out one row per line, so orders are
            // counted through a set rather than by counting rows.
            $cell['orders'][$row->order_id]   = true;
            $cell['clients'][$row->user_id]   = true;
            unset($cell);
        }

        $out = [];

        foreach ($raw as $segment => $months) {
            $keys = array_keys($months);
            sort($keys);

            $cursor = Carbon::createFromFormat('Y-m-d', $keys[0] . '-01')->startOfMonth();
            $last   = Carbon::createFromFormat('Y-m-d', end($keys) . '-01')->startOfMonth();

            // Walk the calendar rather than the observed keys, so a month in
            // which the segment ordered nothing still produces a zero row.
            $timeline = [];
            while ($cursor->lte($last)) {
                $timeline[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            foreach ($timeline as $i => $ym) {
                $cell = $months[$ym] ?? null;

                // Roster: distinct clients seen in this month and the eleven
                // before it. Early months have a short window, which is fine —
                // ratio-to-moving-average discards the first six anyway.
                $roster = [];
                for ($j = max(0, $i - 11); $j <= $i; $j++) {
                    foreach (array_keys($months[$timeline[$j]]['clients'] ?? []) as $clientId) {
                        $roster[$clientId] = true;
                    }
                }

                $enrolled = count($roster);

                $out[$segment][$ym] = [
                    'rate'   => $enrolled > 0 ? ($cell['units'] ?? 0.0) / $enrolled : 0.0,
                    'orders' => count($cell['orders'] ?? []),
                ];
            }
        }

        return $out;
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);

        if ($n === 0) {
            return 0.0;
        }

        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ((float) $values[$mid - 1] + (float) $values[$mid]) / 2;
    }
}
