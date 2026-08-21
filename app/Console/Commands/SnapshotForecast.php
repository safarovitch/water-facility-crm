<?php

namespace App\Console\Commands;

use App\Services\Forecasting\DemandForecastService;
use App\Services\Forecasting\ForecastAccuracyService;
use Illuminate\Console\Command;

/**
 * Records today's forecast so tomorrow can grade it.
 *
 * Run daily. Each run stores a fresh vintage rather than overwriting the last,
 * which is what makes "our 2-day forecast is good but our 3-week one is not"
 * a measurable statement instead of a hunch.
 */
class SnapshotForecast extends Command
{
    protected $signature = 'forecast:snapshot {--days= : horizon to snapshot, defaults to config}';

    protected $description = 'Store the current demand forecast for later accuracy scoring';

    public function handle(DemandForecastService $forecast, ForecastAccuracyService $accuracy): int
    {
        $days = (int) ($this->option('days') ?: config('forecasting.default_horizon_days'));

        $from = today();
        $to   = today()->addDays($days);

        $result = $forecast->forecast($from, $to, ['include_clients' => false]);

        $rows = [];

        foreach ($result['days'] as $day) {
            // Only the predicted component is scored. Committed orders are
            // already known when the snapshot is taken, so including them
            // would flatter the accuracy figures with facts, not forecasts.
            $rows[] = [
                'horizon_date'      => $day['date'],
                'scope'             => 'total',
                'scope_key'         => null,
                'predicted_orders'  => $day['predicted_orders'],
                'predicted_units'   => $day['predicted_units'],
                'predicted_revenue' => $day['revenue'],
                'units_p10'         => $day['units_p10'],
                'units_p90'         => $day['units_p90'],
            ];

            // Per-segment rows are what make "our school forecast is fine but
            // our horeca one is 30% light" visible. Without them the accuracy
            // report can only say the total is wrong, not where.
            foreach ($day['predicted_by_segment'] as $segment => $cell) {
                $rows[] = [
                    'horizon_date'      => $day['date'],
                    'scope'             => 'segment',
                    'scope_key'         => $segment,
                    'predicted_orders'  => $cell['orders'] ?? 0,
                    'predicted_units'   => $cell['units'] ?? 0,
                    'predicted_revenue' => $cell['revenue'] ?? 0,
                    'units_p10'         => 0,
                    'units_p90'         => 0,
                ];
            }
        }

        $written = $accuracy->record($rows, $from);

        $this->info("Snapshotted {$written} day(s) from {$from->toDateString()} to {$to->toDateString()}.");
        $this->line(sprintf(
            '  Horizon totals: %s orders, %s units (P10 %s / P90 %s), bias factor %s.',
            $result['totals']['orders'],
            $result['totals']['units'],
            $result['totals']['units_p10'],
            $result['totals']['units_p90'],
            $result['bias_factor'],
        ));

        return self::SUCCESS;
    }
}
