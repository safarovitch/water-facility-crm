<?php

namespace App\Http\Controllers;

use App\Services\Forecasting\RoutePlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Turns a day's demand — committed and confidently predicted — into vehicle
 * runs, so dispatch is planned against tomorrow's volume rather than
 * discovered on the morning.
 */
class RoutePlanController extends Controller
{
    public function index(Request $request, RoutePlanner $planner): Response
    {
        $date = $this->resolveDate($request->input('date'));

        $options = array_filter([
            'capacity'        => $request->integer('capacity') ?: null,
            'max_stops'       => $request->integer('max_stops') ?: null,
            'min_probability' => $request->has('min_probability') ? (float) $request->input('min_probability') : null,
        ], fn ($v) => $v !== null);

        return Inertia::render('forecasts/Routes')->with([
            'plan' => $planner->plan($date, $options),
            'date' => $date->toDateString(),
        ]);
    }

    private function resolveDate($value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        // Tomorrow by default: today's routes are usually already rolling.
        return today()->addDay();
    }
}
