<?php

namespace App\Http\Controllers;

use App\Enums\ClientSegment;
use App\Models\DemandSeasonality;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Forecasting\DemandForecastService;
use App\Services\Forecasting\ForecastAccuracyService;
use App\Services\Forecasting\ForecastNarrator;
use App\Services\Forecasting\ProcurementForecastService;
use App\Services\Forecasting\SeasonalityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aggregate demand: how many bottles, when, and what has to be bought.
 *
 * This is the volume view. ForecastController remains the per-client calendar
 * ("who is likely to order on Tuesday"); the two answer different questions
 * off the same model and are deliberately kept as separate pages rather than
 * one page with a mode switch.
 */
class DemandForecastController extends Controller
{
    public function index(
        Request $request,
        DemandForecastService $forecast,
        ProcurementForecastService $procurement,
        ForecastAccuracyService $accuracy,
        ForecastNarrator $narrator,
    ): Response {
        $horizon = $this->resolveHorizon($request->input('horizon'));
        $from    = today();
        $to      = today()->addDays($horizon - 1);

        $segments = array_values(array_filter(
            (array) $request->input('segments', []),
            fn ($s) => in_array($s, ClientSegment::getValues(), true),
        ));

        $result = $forecast->forecast($from, $to, [
            'include_clients' => false,
            'segments'        => $segments,
        ]);

        $requirements = $procurement->withCoverage(
            $procurement->requirements($result['products']),
            $horizon,
        );

        $metrics = $accuracy->metrics();

        return Inertia::render('forecasts/Demand')->with([
            'horizon'     => $horizon,
            'forecast'    => $result,
            'procurement' => $requirements,
            'unmeasured'  => $procurement->unmeasuredReusables(),
            'accuracy'    => $metrics['total'],
            'segments'    => $this->segmentOptions(),
            'filters'     => ['segments' => $segments],
            // Rendered as a hint alongside the numbers, never in place of them.
            'narrative'   => $narrator->narrate($result, $requirements, $metrics['total']),
            'aiEnabled'   => $narrator->available(),
        ]);
    }

    public function accuracy(Request $request, ForecastAccuracyService $accuracy): Response
    {
        $days = (int) $request->input('days', 90);
        $days = max(7, min(365, $days));

        return Inertia::render('forecasts/Accuracy')->with([
            'days'    => $days,
            'metrics' => $accuracy->metrics($days),
        ]);
    }

    /**
     * The seasonality curves, with the evidence behind each number.
     */
    public function seasonality(SeasonalityService $seasonality): Response
    {
        $rows = DemandSeasonality::all()->groupBy(fn ($row) => $row->segment->value);

        $curves = [];

        foreach (ClientSegment::cases() as $segment) {
            $stored = $rows->get($segment->value, collect())->keyBy('month');

            $curves[] = [
                'segment' => $segment->value,
                'label'   => $segment->label(),
                'months'  => collect(range(1, 12))->map(function (int $month) use ($segment, $stored) {
                    $row = $stored->get($month);

                    return [
                        'month'          => $month,
                        'index'          => $row ? round((float) $row->index, 3) : $segment->priorIndexFor($month),
                        'prior'          => round($segment->priorIndexFor($month), 3),
                        'observed'       => $row?->observed_index !== null ? round((float) $row->observed_index, 3) : null,
                        'source'         => $row->source ?? 'prior',
                        'sample_size'    => (int) ($row->sample_size ?? 0),
                    ];
                })->all(),
            ];
        }

        return Inertia::render('forecasts/Seasonality')->with([
            'curves' => $curves,
            'status' => $seasonality->status(),
            'limits' => [
                'floor'   => (float) config('forecasting.index_floor'),
                'ceiling' => (float) config('forecasting.index_ceiling'),
            ],
        ]);
    }

    /**
     * Override one month of one segment's curve by hand.
     *
     * Stored with source = manual, which recomputation is required to leave
     * alone. Passing a null index clears the override and returns the month to
     * the measured/prior value on the next recompute.
     */
    public function updateSeasonality(Request $request, SeasonalityService $seasonality): RedirectResponse
    {
        $data = $request->validate([
            'segment' => ['required', 'string', 'in:' . implode(',', ClientSegment::getValues())],
            'month'   => ['required', 'integer', 'min:1', 'max:12'],
            'index'   => [
                'nullable',
                'numeric',
                'min:' . config('forecasting.index_floor'),
                'max:' . config('forecasting.index_ceiling'),
            ],
        ]);

        if ($data['index'] === null) {
            DemandSeasonality::where('segment', $data['segment'])
                ->where('month', $data['month'])
                ->where('source', 'manual')
                ->delete();
        } else {
            DemandSeasonality::updateOrCreate(
                ['segment' => $data['segment'], 'month' => $data['month']],
                ['index' => $data['index'], 'source' => 'manual', 'observed_index' => null],
            );
        }

        // Recompute so the rest of the year renormalises around the override
        // and the curve still averages 1.0.
        $seasonality->recompute();

        return back()->with('success', __('Seasonality updated'));
    }

    /**
     * Client segment assignments, the input the whole seasonal model rests on.
     */
    public function segments(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $clients = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Client'))
            ->with('userProfile:id,user_id,company_name,segment,segment_source,segment_confidence')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('userProfile', fn ($p) => $p->where('company_name', 'like', "%{$search}%"))))
            ->when($request->filled('segment'), fn ($q) => $q->whereHas(
                'userProfile',
                fn ($p) => $p->where('segment', $request->input('segment')),
            ))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id'           => $user->id,
                'name'         => $user->name,
                'company_name' => $user->userProfile?->company_name,
                'segment'      => $user->userProfile?->segment?->value ?? ClientSegment::Unknown->value,
                'source'       => $user->userProfile?->segment_source ?? 'default',
                'confidence'   => $user->userProfile?->segment_confidence,
            ]);

        return Inertia::render('forecasts/Segments')->with([
            'clients'  => $clients,
            'segments' => $this->segmentOptions(),
            'filters'  => [
                'search'  => $search,
                'segment' => $request->input('segment'),
            ],
            'counts'   => UserProfile::query()
                ->selectRaw('segment, COUNT(*) as c')
                ->groupBy('segment')
                ->pluck('c', 'segment'),
        ]);
    }

    public function updateSegment(Request $request, User $user, SeasonalityService $seasonality): RedirectResponse
    {
        $data = $request->validate([
            'segment' => ['required', 'string', 'in:' . implode(',', ClientSegment::getValues())],
        ]);

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                // Marked manual so neither the keyword rules nor the AI pass
                // will ever overwrite this choice on a later run.
                'segment'               => $data['segment'],
                'segment_source'        => 'manual',
                'segment_confidence'    => 1.0,
                'segment_classified_at' => now(),
            ],
        );

        $seasonality->flush();

        return back()->with('success', __('Segment updated'));
    }

    private function resolveHorizon($value): int
    {
        $horizon = (int) ($value ?: config('forecasting.default_horizon_days'));

        return max(7, min((int) config('forecasting.max_horizon_days'), $horizon));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function segmentOptions(): array
    {
        return collect(ClientSegment::cases())
            ->map(fn (ClientSegment $s) => ['value' => $s->value, 'label' => $s->label()])
            ->all();
    }
}
