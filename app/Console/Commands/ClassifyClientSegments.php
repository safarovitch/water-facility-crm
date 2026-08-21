<?php

namespace App\Console\Commands;

use App\Enums\ClientSegment;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Forecasting\AiSegmentClassifier;
use App\Services\Forecasting\SegmentClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Assigns every client a demand segment.
 *
 * Two passes: cheap deterministic keyword rules first, then Gemini for the
 * leftovers if it is configured. A human's choice (segment_source = manual) is
 * never touched by either, so this is safe to schedule and safe to re-run.
 */
class ClassifyClientSegments extends Command
{
    protected $signature = 'forecast:classify-segments
                            {--ai : also ask Gemini about clients the rules could not place}
                            {--force : re-classify clients that already have a non-manual segment}
                            {--dry-run : report what would change without writing}';

    protected $description = 'Classify clients into demand segments (household, office, school, horeca, ...)';

    public function handle(SegmentClassifier $rules, AiSegmentClassifier $ai): int
    {
        $query = User::query()
            ->with(['userProfile', 'addresses'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'Client'));

        if (! $this->option('force')) {
            // Only clients nobody has classified yet.
            $query->where(fn ($q) => $q
                ->whereDoesntHave('userProfile')
                ->orWhereHas('userProfile', fn ($p) => $p
                    ->whereIn('segment', [ClientSegment::Unknown->value])
                    ->orWhere('segment_source', 'default')));
        } else {
            $query->whereDoesntHave('userProfile', fn ($p) => $p->where('segment_source', 'manual'));
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->info('No clients need classification.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$clients->count()} client(s)...");

        $changes    = [];
        $unresolved = collect();

        foreach ($clients as $client) {
            if ($client->userProfile?->segmentIsLocked()) {
                continue;
            }

            [$segment, $confidence, $needle] = $rules->classify($client);

            if ($rules->shouldAskAi($segment, $confidence)) {
                $unresolved->push($client);

                // Still record the rules verdict; the AI pass overwrites it
                // only if it produces something better.
                $changes[$client->id] = [$segment, $confidence, 'rules', $needle];
                continue;
            }

            $changes[$client->id] = [$segment, $confidence, 'rules', $needle];
        }

        if ($this->option('ai') && $unresolved->isNotEmpty()) {
            $this->resolveWithAi($ai, $unresolved, $changes);
        } elseif ($unresolved->isNotEmpty()) {
            $this->line("  {$unresolved->count()} client(s) could not be placed by keyword rules; re-run with --ai or set them by hand.");
        }

        $written = 0;

        foreach ($changes as $clientId => [$segment, $confidence, $source, $needle]) {
            $client = $clients->firstWhere('id', $clientId);
            $before = $client->userProfile?->segment ?? ClientSegment::Unknown;

            if ($this->option('dry-run')) {
                if ($before !== $segment) {
                    $this->line(sprintf('  %-30s %s -> %s (%s%s)', mb_substr($client->name, 0, 30), $before->value, $segment->value, $source, $needle ? ": {$needle}" : ''));
                }
                continue;
            }

            UserProfile::updateOrCreate(
                ['user_id' => $clientId],
                [
                    'segment'               => $segment->value,
                    'segment_source'        => $source,
                    'segment_confidence'    => $confidence,
                    'segment_classified_at' => now(),
                ],
            );

            $written++;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run; nothing written.');

            return self::SUCCESS;
        }

        $this->info("Updated {$written} profile(s).");
        $this->newLine();
        $this->table(
            ['Segment', 'Clients'],
            UserProfile::query()
                ->selectRaw('segment, COUNT(*) as c')
                ->groupBy('segment')
                ->orderByDesc('c')
                ->pluck('c', 'segment')
                ->map(fn ($count, $segment) => [$segment, $count])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $unresolved
     */
    private function resolveWithAi(AiSegmentClassifier $ai, Collection $unresolved, array &$changes): void
    {
        if (! $ai->available()) {
            $this->warn('  --ai requested but Gemini is not configured (set GEMINI_API_KEY and FORECAST_AI_ENABLED=true). Keeping rule results.');

            return;
        }

        $batchSize = (int) config('forecasting.ai.classify_batch');
        $resolved  = 0;

        foreach ($unresolved->chunk($batchSize) as $batch) {
            $results = $ai->classifyBatch($batch);

            foreach ($results as $clientId => $result) {
                // The model is allowed to say "unknown"; that is a real answer
                // and better than a confident wrong one, but there is no point
                // overwriting a rules guess with it.
                if ($result['segment'] === ClientSegment::Unknown) {
                    continue;
                }

                $changes[$clientId] = [$result['segment'], $result['confidence'], 'ai', null];
                $resolved++;
            }
        }

        $this->line("  Gemini placed {$resolved} of {$unresolved->count()} ambiguous client(s).");
    }
}
