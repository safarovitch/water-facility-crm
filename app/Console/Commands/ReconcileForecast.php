<?php

namespace App\Console\Commands;

use App\Services\Forecasting\ForecastAccuracyService;
use Illuminate\Console\Command;

class ReconcileForecast extends Command
{
    protected $signature = 'forecast:reconcile {--report : print the accuracy report afterwards}';

    protected $description = 'Score past forecasts against what was actually ordered';

    public function handle(ForecastAccuracyService $accuracy): int
    {
        $updated = $accuracy->reconcile();

        $this->info("Reconciled {$updated} snapshot row(s).");

        if (! $this->option('report')) {
            return self::SUCCESS;
        }

        $metrics = $accuracy->metrics();
        $total   = $metrics['total'];

        if (($total['observations'] ?? 0) === 0) {
            $this->line('  No scored days yet — run forecast:snapshot daily and come back tomorrow.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Days scored',       $total['observations']],
                ['Predicted units',   $total['predicted_units']],
                ['Actual units',      $total['actual_units']],
                ['Accuracy (100-WAPE)', $total['accuracy_pct'] . '%'],
                ['Bias (+ = ran high)', $total['bias_pct'] . '%'],
                ['P10-P90 coverage',  $total['coverage_pct'] . '%  (80% is well calibrated)'],
                ['Correction applied', 'x' . $metrics['bias_factor']],
            ],
        );

        if (! empty($metrics['by_lead_time'])) {
            $rows = [];
            foreach ($metrics['by_lead_time'] as $bucket => $m) {
                $rows[] = [$bucket, $m['observations'], $m['accuracy_pct'] . '%', $m['bias_pct'] . '%'];
            }
            $this->newLine();
            $this->table(['Lead time', 'Days', 'Accuracy', 'Bias'], $rows);
        }

        return self::SUCCESS;
    }
}
