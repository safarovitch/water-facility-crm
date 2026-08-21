<?php

namespace App\Services\Forecasting;

use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Cache;

/**
 * Turns a computed forecast into a few sentences a manager can act on.
 *
 * The model is given the finished numbers and asked only to explain them. It
 * is never asked to produce or adjust a figure, and the page renders correctly
 * when this returns null, so the forecast is never hostage to an API key or a
 * rate limit.
 *
 * Cached per forecast shape: the narrative for the same numbers does not
 * change, and regenerating it on every page load would be a per-refresh cost
 * for no benefit.
 */
class ForecastNarrator
{
    public function __construct(private readonly GeminiClient $gemini) {}

    public function available(): bool
    {
        return $this->gemini->enabled();
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @param  array<string, mixed>  $procurement
     */
    public function narrate(array $forecast, array $procurement, ?array $accuracy = null): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $fingerprint = md5(json_encode([
            $forecast['from'], $forecast['to'], $forecast['totals'], $forecast['segments'],
            $procurement['total_purchase'] ?? null,
        ]));

        return Cache::remember("forecasting.narrative.{$fingerprint}", 3600, function () use ($forecast, $procurement, $accuracy) {
            return $this->gemini->generate($this->prompt($forecast, $procurement, $accuracy), systemInstruction: $this->systemInstruction());
        });
    }

    private function systemInstruction(): string
    {
        return 'You brief the operations manager of a bottled-water delivery company in Tajikistan. '
            . 'Write in Russian. Be concrete and short: at most four sentences, no headings, no bullet points, no preamble. '
            . 'You are given numbers that have already been computed — never recalculate them, never invent new ones, '
            . 'and never contradict them. Say what the numbers imply for stock and delivery this period, and name the '
            . 'single most useful action. If the forecast is running on priors rather than measured history, say so plainly.';
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @param  array<string, mixed>  $procurement
     */
    private function prompt(array $forecast, array $procurement, ?array $accuracy): string
    {
        $segments = collect($forecast['segments'])
            ->take(5)
            ->map(fn ($s) => "{$s['label']}: {$s['units']} units from {$s['clients']} clients")
            ->implode('; ');

        $materials = collect($procurement['materials'] ?? [])
            ->filter(fn ($m) => $m['shortfall'] > 0)
            ->take(4)
            ->map(fn ($m) => "{$m['name']}: short {$m['shortfall']} {$m['unit']}, "
                . ($m['days_of_cover'] !== null ? "{$m['days_of_cover']} days of cover" : 'cover unknown'))
            ->implode('; ');

        $seasonality = $forecast['seasonality'];
        $state = $seasonality['learning']
            ? "measured from {$seasonality['months_of_history']} months of history"
            : "running on segment priors ({$seasonality['months_of_history']} of {$seasonality['months_required']} months of history needed to measure it)";

        $accuracyLine = $accuracy && ($accuracy['observations'] ?? 0) > 0
            ? "Recent forecast accuracy: {$accuracy['accuracy_pct']}%, bias {$accuracy['bias_pct']}%."
            : 'No accuracy history scored yet.';

        return <<<PROMPT
        Period: {$forecast['from']} to {$forecast['to']}.
        Expected orders: {$forecast['totals']['orders']} ({$forecast['totals']['committed_orders']} already placed).
        Expected units: {$forecast['totals']['units']}, with a P10-P90 range of {$forecast['totals']['units_p10']} to {$forecast['totals']['units_p90']}.
        Expected revenue: {$forecast['totals']['revenue']} TJS.
        By segment: {$segments}.
        Purchasing shortfalls: {$materials}.
        Estimated purchase cost: {$procurement['total_purchase']} TJS.
        Seasonality is {$state}.
        {$accuracyLine}
        PROMPT;
    }
}
