<?php

use App\Enums\OrderStatus;
use App\Models\ForecastSnapshot;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Forecasting\ForecastAccuracyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([\App\Events\OrderCreated::class]);

    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L'],
        'sku'        => 'B19A',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 1000,
    ]);
});

/** Place an order of $units bottles on $date. */
function orderOn(string $date, int $units): void
{
    $at    = Carbon::parse($date);
    $order = new Order([
        'user_id'      => User::factory()->create()->id,
        'status'       => OrderStatus::Delivered,
        'total_amount' => $units * 20,
    ]);
    $order->created_at = $at;
    $order->updated_at = $at;
    $order->save();

    $order->items()->create([
        'product_id' => test()->product->id,
        'quantity'   => $units,
        'unit_price' => 20,
        'subtotal'   => $units * 20,
        'is_gift'    => false,
    ]);
}

/** Record a snapshot predicting $units for $date. */
function predict(string $generatedOn, string $horizonDate, float $units): void
{
    app(ForecastAccuracyService::class)->record([[
        'horizon_date'      => $horizonDate,
        'scope'             => 'total',
        'scope_key'         => null,
        'predicted_orders'  => 1,
        'predicted_units'   => $units,
        'predicted_revenue' => $units * 20,
        'units_p10'         => $units * 0.7,
        'units_p90'         => $units * 1.3,
    ]], Carbon::parse($generatedOn));
}

it('never scores a day that is still accepting orders', function () {
    Carbon::setTestNow('2026-07-10');

    predict('2026-07-09', '2026-07-10', 100);   // today
    predict('2026-07-09', '2026-07-11', 100);   // tomorrow
    predict('2026-07-08', '2026-07-09', 100);   // yesterday

    $reconciled = app(ForecastAccuracyService::class)->reconcile();

    // Scoring today would record a permanent, fictitious over-forecast
    // simply because the day is not finished.
    expect($reconciled)->toBe(1)
        ->and(ForecastSnapshot::whereNotNull('reconciled_at')->first()->horizon_date->toDateString())
        ->toBe('2026-07-09');

    Carbon::setTestNow();
});

it('measures accuracy, bias and band coverage against real orders', function () {
    Carbon::setTestNow('2026-07-10');

    predict('2026-07-08', '2026-07-09', 100);
    orderOn('2026-07-09 10:00', 80);

    app(ForecastAccuracyService::class)->reconcile();

    $metrics = app(ForecastAccuracyService::class)->metrics();

    expect($metrics['total']['actual_units'])->toBe(80.0)
        ->and($metrics['total']['predicted_units'])->toBe(100.0)
        // 20 units of error on 80 actual is 25% WAPE.
        ->and($metrics['total']['wape'])->toBe(25.0)
        ->and($metrics['total']['accuracy_pct'])->toBe(75.0)
        // Forecast ran 25% above reality.
        ->and($metrics['total']['bias_pct'])->toBe(25.0)
        // 80 sits inside the 70-130 band.
        ->and($metrics['total']['coverage_pct'])->toBe(100.0);

    Carbon::setTestNow();
});

it('waits for enough evidence before correcting itself', function () {
    Carbon::setTestNow('2026-07-30');

    // Five consistently light days: a real pattern, but not yet enough of one.
    foreach (range(1, 5) as $i) {
        $date = Carbon::parse('2026-07-01')->addDays($i)->toDateString();
        predict('2026-07-01', $date, 50);
        orderOn($date . ' 10:00', 100);
    }

    app(ForecastAccuracyService::class)->reconcile();

    expect((int) config('forecasting.bias_min_observations'))->toBeGreaterThan(5)
        ->and(app(ForecastAccuracyService::class)->biasFactor())->toBe(1.0);

    Carbon::setTestNow();
});

it('corrects a proven bias but never by more than the configured clamp', function () {
    Carbon::setTestNow('2026-07-30');

    // Twenty days forecast at half of what actually happened. Uncorrected
    // this would be a 2x error; the clamp holds the correction to 25%.
    foreach (range(1, 20) as $i) {
        $date = Carbon::parse('2026-07-01')->addDays($i)->toDateString();
        predict('2026-07-01', $date, 50);
        orderOn($date . ' 10:00', 100);
    }

    app(ForecastAccuracyService::class)->reconcile();

    $max    = (float) config('forecasting.bias_max_adjustment');
    $factor = app(ForecastAccuracyService::class)->biasFactor();

    expect($factor)->toBe(1 + $max)
        ->and($factor)->toBeGreaterThan(1.0);

    Carbon::setTestNow();
});

it('separates accuracy by lead time so a long horizon cannot hide behind a short one', function () {
    Carbon::setTestNow('2026-07-30');

    // Same day, two vintages: a next-day forecast that was right and a
    // three-week-out one that was not.
    predict('2026-07-09', '2026-07-10', 100);
    predict('2026-06-19', '2026-07-10', 40);
    orderOn('2026-07-10 10:00', 100);

    app(ForecastAccuracyService::class)->reconcile();

    $buckets = app(ForecastAccuracyService::class)->metrics()['by_lead_time'];

    expect($buckets['0-1 days']['accuracy_pct'])->toBe(100.0)
        ->and($buckets['15-30 days']['accuracy_pct'])->toBe(40.0);

    Carbon::setTestNow();
});
