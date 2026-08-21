<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Forecasting\ProductionPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The daily "how many do we fill" page.
 *
 * Separate from the forecasting dashboards on purpose. This one is used every
 * morning by people who do not want a model explained to them, so it takes a
 * date (or a range) and answers with a number.
 */
class ProductionPlanController extends Controller
{
    public function index(Request $request, ProductionPlanService $plans): Response
    {
        [$from, $to] = $this->resolveRange($request);

        return Inertia::render('production/Plan')->with([
            'plan' => $plans->plan($from, $to),
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
        ]);
    }

    /**
     * Record how many were actually filled on a day.
     */
    public function record(Request $request, ProductionPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'date'       => ['required', 'date'],
            'units'      => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $plans->recordProduction(
            Carbon::parse($data['date']),
            (int) $data['product_id'],
            (int) $data['units'],
            auth()->id(),
            $data['notes'] ?? null,
        );

        return back()->with('success', __('Production recorded'));
    }

    /**
     * Record a physical stock count, which re-anchors the ready-stock balance.
     */
    public function count(Request $request, ProductionPlanService $plans): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'units'      => ['required', 'integer', 'min:0', 'max:1000000'],
            'date'       => ['nullable', 'date'],
        ]);

        $plans->recordCount(
            // A `nullable` field that was simply not sent is absent from
            // validated(), not null — the mobile client posts a count without
            // a date and would otherwise 500 here.
            isset($data['date']) ? Carbon::parse($data['date']) : today(),
            (int) $data['product_id'],
            (int) $data['units'],
            auth()->id(),
        );

        return back()->with('success', __('Stock count saved'));
    }

    /**
     * Single date or an inclusive range, defaulting to today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $this->parseDate($request->input('from') ?? $request->input('date')) ?? today();
        $to   = $this->parseDate($request->input('to')) ?? $from->copy();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function parseDate($value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
