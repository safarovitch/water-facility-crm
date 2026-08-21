<?php

namespace App\Services\Forecasting;

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\ForecastSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Scores past forecasts against what actually happened, and feeds the answer
 * back into the next one.
 *
 * This is the part that converts a plausible model into a precise one. No
 * amount of cleverness in the seasonal maths tells you whether the forecast is
 * running 12% light; only comparing it with reality does. Every morning
 * `forecast:snapshot` records what was predicted for the days ahead,
 * `forecast:reconcile` fills in what those days delivered, and `biasFactor()`
 * turns the accumulated difference into a multiplier the forecaster applies to
 * itself.
 *
 * Deliberate constraints on that feedback loop:
 *  - it needs `bias_min_observations` reconciled days before it acts at all,
 *    so a quiet fortnight cannot swing the model;
 *  - it is clamped to `bias_max_adjustment` in either direction, because a
 *    runaway self-correction is far more damaging than a stale bias;
 *  - it corrects *level* only. A model that is wrong about shape needs its
 *    seasonality or segments fixed, and inflating every day equally would only
 *    hide that.
 *
 * Note this class never calls the forecaster. Snapshots are handed to it, so
 * the forecaster can depend on the accuracy measurements without the two
 * forming a cycle.
 */
class ForecastAccuracyService
{
    private const BIAS_CACHE_KEY = 'forecasting.bias';
    private const BIAS_CACHE_TTL = 900;

    /**
     * Multiplier to apply to raw predictions, e.g. 1.08 to lift a forecast
     * that has been running 8% light. Returns 1.0 when disabled or unproven.
     */
    public function biasFactor(string $scope = 'total', ?string $scopeKey = null): float
    {
        if (! config('forecasting.bias_correction_enabled')) {
            return 1.0;
        }

        $cacheKey = self::BIAS_CACHE_KEY . ":{$scope}:" . ($scopeKey ?? '-');

        return Cache::remember($cacheKey, self::BIAS_CACHE_TTL, function () use ($scope, $scopeKey): float {
            $rows = ForecastSnapshot::query()
                ->reconciled()
                ->scope($scope, $scopeKey)
                ->where('horizon_date', '>=', now()->subDays(120)->toDateString())
                ->selectRaw('COUNT(*) as n, SUM(predicted_units) as p, SUM(actual_units) as a')
                ->first();

            $n = (int) ($rows->n ?? 0);

            if ($n < (int) config('forecasting.bias_min_observations')) {
                return 1.0;
            }

            $predicted = (float) ($rows->p ?? 0);
            $actual    = (float) ($rows->a ?? 0);

            if ($predicted <= 0) {
                return 1.0;
            }

            $max = (float) config('forecasting.bias_max_adjustment');

            return max(1 - $max, min(1 + $max, $actual / $predicted));
        });
    }

    public function flushBias(): void
    {
        Cache::forget(self::BIAS_CACHE_KEY . ':total:-');

        foreach (ClientSegment::cases() as $segment) {
            Cache::forget(self::BIAS_CACHE_KEY . ":segment:{$segment->value}");
        }
    }

    /**
     * Persist one vintage of a forecast.
     *
     * @param  array<int, array{horizon_date: string, scope: string, scope_key: ?string, predicted_orders: float, predicted_units: float, predicted_revenue: float, units_p10: float, units_p90: float}>  $rows
     */
    public function record(array $rows, ?Carbon $generatedOn = null): int
    {
        $generatedOn = ($generatedOn ?? now())->copy()->startOfDay();
        $written     = 0;

        foreach ($rows as $row) {
            $horizon = Carbon::parse($row['horizon_date'])->startOfDay();

            ForecastSnapshot::updateOrCreate(
                [
                    'generated_on' => $generatedOn->toDateString(),
                    'horizon_date' => $horizon->toDateString(),
                    'scope'        => $row['scope'],
                    'scope_key'    => $row['scope_key'] ?? null,
                ],
                [
                    'lead_days'         => (int) $generatedOn->diffInDays($horizon, absolute: false),
                    'predicted_orders'  => round((float) $row['predicted_orders'], 3),
                    'predicted_units'   => round((float) $row['predicted_units'], 3),
                    'predicted_revenue' => round((float) $row['predicted_revenue'], 2),
                    'units_p10'         => round((float) ($row['units_p10'] ?? 0), 3),
                    'units_p90'         => round((float) ($row['units_p90'] ?? 0), 3),
                ],
            );

            $written++;
        }

        return $written;
    }

    /**
     * Fill in actuals for every past day still unscored.
     *
     * Only days strictly before today are reconciled — scoring a day that is
     * still accepting orders would record a permanent, fictitious over-forecast.
     */
    public function reconcile(?Carbon $upTo = null): int
    {
        $upTo = ($upTo ?? now())->copy()->startOfDay();

        $pending = ForecastSnapshot::query()
            ->whereNull('reconciled_at')
            ->where('horizon_date', '<', $upTo->toDateString())
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $dates   = $pending->pluck('horizon_date')->map(fn ($d) => $d->toDateString())->unique();
        $actuals = $this->actualsFor($dates->all());

        $updated = 0;

        foreach ($pending as $snapshot) {
            $key    = $this->actualKey($snapshot->horizon_date->toDateString(), $snapshot->scope, $snapshot->scope_key);
            $actual = $actuals[$key] ?? ['orders' => 0.0, 'units' => 0.0, 'revenue' => 0.0];

            $snapshot->forceFill([
                'actual_orders'  => $actual['orders'],
                'actual_units'   => $actual['units'],
                'actual_revenue' => $actual['revenue'],
                'reconciled_at'  => now(),
            ])->save();

            $updated++;
        }

        $this->flushBias();

        return $updated;
    }

    /**
     * Accuracy report over the recent past.
     *
     * @return array<string, mixed>
     */
    public function metrics(int $days = 90): array
    {
        $since = now()->copy()->subDays($days)->startOfDay();

        $rows = ForecastSnapshot::query()
            ->reconciled()
            ->where('horizon_date', '>=', $since->toDateString())
            ->get();

        return [
            'window_days'  => $days,
            'observations' => $rows->where('scope', 'total')->count(),
            'total'        => $this->summarise($rows->where('scope', 'total')),
            'by_segment'   => $rows->where('scope', 'segment')
                ->groupBy('scope_key')
                ->map(fn ($group) => $this->summarise($group))
                ->sortByDesc('actual_units')
                ->all(),
            'by_lead_time' => $rows->where('scope', 'total')
                ->groupBy(fn (ForecastSnapshot $s) => $this->leadBucket($s->lead_days))
                ->map(fn ($group) => $this->summarise($group))
                ->all(),
            'recent_days'  => $rows->where('scope', 'total')
                ->sortBy(fn (ForecastSnapshot $s) => $s->horizon_date->toDateString())
                ->groupBy(fn (ForecastSnapshot $s) => $s->horizon_date->toDateString())
                // Several vintages exist per day; the shortest lead time is the
                // one the business actually acted on, so that is the one scored.
                ->map(fn ($group) => $group->sortBy('lead_days')->first())
                ->map(fn (ForecastSnapshot $s) => [
                    'date'      => $s->horizon_date->toDateString(),
                    'predicted' => round($s->predicted_units, 1),
                    'actual'    => round((float) $s->actual_units, 1),
                    'p10'       => round($s->units_p10, 1),
                    'p90'       => round($s->units_p90, 1),
                    'in_band'   => $s->actual_units >= $s->units_p10 && $s->actual_units <= $s->units_p90,
                ])
                ->values()
                ->all(),
            'bias_factor'  => $this->biasFactor(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ForecastSnapshot>  $rows
     * @return array<string, mixed>
     */
    private function summarise($rows): array
    {
        $rows = $rows->values();
        $n    = $rows->count();

        if ($n === 0) {
            return [
                'observations' => 0, 'predicted_units' => 0.0, 'actual_units' => 0.0,
                'wape' => null, 'bias_pct' => null, 'coverage_pct' => null, 'accuracy_pct' => null,
            ];
        }

        $predicted = (float) $rows->sum('predicted_units');
        $actual    = (float) $rows->sum('actual_units');

        // WAPE (weighted absolute percentage error) rather than MAPE: MAPE
        // divides by the actual value, so a single quiet day with 2 bottles
        // sold can post a 400% error and dominate the average. WAPE weights by
        // volume, which is what the business actually cares about being wrong on.
        $absoluteError = 0.0;
        foreach ($rows as $row) {
            $absoluteError += abs($row->predicted_units - (float) $row->actual_units);
        }

        $wape = $actual > 0 ? $absoluteError / $actual : null;

        // Only rows that actually carry a band can be scored for coverage.
        // Segment-scope snapshots record no interval, and counting them as
        // misses would report a calibrated forecast as badly overconfident.
        $banded = $rows->filter(fn (ForecastSnapshot $s) => $s->units_p90 > 0);
        $inBand = $banded->filter(fn (ForecastSnapshot $s) => $s->actual_units >= $s->units_p10 && $s->actual_units <= $s->units_p90)->count();

        return [
            'observations'    => $n,
            'predicted_units' => round($predicted, 1),
            'actual_units'    => round($actual, 1),
            'wape'            => $wape !== null ? round($wape * 100, 1) : null,
            'accuracy_pct'    => $wape !== null ? round(max(0, 1 - $wape) * 100, 1) : null,
            // Positive means the forecast ran high.
            'bias_pct'        => $actual > 0 ? round((($predicted - $actual) / $actual) * 100, 1) : null,
            // How often reality landed inside the stated P10-P90 band. A well
            // calibrated band should catch ~80%; much more means the band is
            // uselessly wide, much less means it is lying about its certainty.
            'coverage_pct'    => $banded->count() > 0 ? round(($inBand / $banded->count()) * 100, 1) : null,
        ];
    }

    private function leadBucket(int $leadDays): string
    {
        return match (true) {
            $leadDays <= 1  => '0-1 days',
            $leadDays <= 7  => '2-7 days',
            $leadDays <= 14 => '8-14 days',
            $leadDays <= 30 => '15-30 days',
            default         => '30+ days',
        };
    }

    /**
     * Real orders placed on each date, keyed to match snapshot scopes.
     *
     * @param  string[]  $dates
     * @return array<string, array{orders: float, units: float, revenue: float}>
     */
    private function actualsFor(array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $from = Carbon::parse(min($dates))->startOfDay();
        $to   = Carbon::parse(max($dates))->endOfDay();

        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'orders.user_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled)
            ->where('order_items.is_gift', '=', false)
            ->whereBetween('orders.created_at', [$from, $to])
            ->select(
                'orders.id as order_id',
                'orders.created_at',
                'user_profiles.segment as segment',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.unit_price',
            )
            ->cursor();

        $out        = [];
        $seenOrders = [];

        foreach ($rows as $row) {
            $date    = Carbon::parse($row->created_at)->toDateString();
            $segment = $row->segment ?: ClientSegment::Unknown->value;
            $units   = (float) $row->quantity;
            $revenue = $units * (float) $row->unit_price;

            foreach ([
                $this->actualKey($date, 'total', null),
                $this->actualKey($date, 'segment', $segment),
                $this->actualKey($date, 'product', (string) $row->product_id),
            ] as $key) {
                $out[$key]['units']   = ($out[$key]['units'] ?? 0.0) + $units;
                $out[$key]['revenue'] = ($out[$key]['revenue'] ?? 0.0) + $revenue;
                $out[$key]['orders'] ??= 0.0;

                // The item join repeats an order once per line, so orders are
                // counted through a seen-set per scope key.
                $orderKey = $key . '|' . $row->order_id;
                if (! isset($seenOrders[$orderKey])) {
                    $seenOrders[$orderKey] = true;
                    $out[$key]['orders']  += 1.0;
                }
            }
        }

        return $out;
    }

    private function actualKey(string $date, string $scope, ?string $scopeKey): string
    {
        return $date . '|' . $scope . '|' . ($scopeKey ?? '-');
    }
}
