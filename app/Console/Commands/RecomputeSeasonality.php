<?php

namespace App\Console\Commands;

use App\Enums\ClientSegment;
use App\Services\Forecasting\SeasonalityService;
use Illuminate\Console\Command;

class RecomputeSeasonality extends Command
{
    protected $signature = 'forecast:recompute-seasonality {--show : print the resulting curves}';

    protected $description = 'Re-measure per-segment monthly demand indices from order history';

    public function handle(SeasonalityService $seasonality): int
    {
        $status = $seasonality->status();

        $this->info("History available: {$status['months_of_history']} month(s); {$status['months_required']} required to learn from data.");

        if (! $status['learning']) {
            $this->line("  Running on segment priors. {$status['months_remaining']} more month(s) of history and the curves start measuring themselves.");
        }

        $report = $seasonality->recompute();

        $rows = [];
        foreach ($report['segments'] as $segment => $data) {
            $rows[] = [$segment, $data['updated'], $data['observed_cells']];
        }

        $this->table(['Segment', 'Months written', 'Months measured'], $rows);

        if ($this->option('show')) {
            $this->newLine();
            $this->showCurves($seasonality);
        }

        return self::SUCCESS;
    }

    private function showCurves(SeasonalityService $seasonality): void
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $rows   = [];

        foreach (ClientSegment::cases() as $segment) {
            $curve = $seasonality->curveFor($segment);
            $rows[] = array_merge(
                [$segment->value],
                array_map(fn (int $m) => number_format($curve[$m], 2), range(1, 12)),
            );
        }

        $this->table(array_merge(['Segment'], $months), $rows);
        $this->line('  1.00 = that segment\'s own yearly average. Every row averages 1.00 by construction.');
    }
}
