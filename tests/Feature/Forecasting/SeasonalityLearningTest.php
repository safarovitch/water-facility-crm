<?php

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\DemandSeasonality;
use App\Models\Product;
use App\Models\User;
use App\Services\Forecasting\SeasonalityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L'],
        'sku'        => 'B19S',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 10000,
    ]);

    app(SeasonalityService::class)->flush();
});

/**
 * Plant a known monthly shape into order history.
 *
 * Orders are inserted straight through the query builder rather than the
 * model: this seeds thousands of rows, and Order::boot() would fire an
 * OrderCreated event and issue a fresh order-number query for every one.
 *
 * @param  array<int, float>  $shape  relative demand per month, January first
 */
function plantHistory(ClientSegment $segment, array $shape, int $clients, int $months, string $endsOn): void
{
    $userIds = [];

    for ($i = 0; $i < $clients; $i++) {
        $user = User::factory()->create(['name' => "{$segment->value} client {$i}", 'phone_verified_at' => now()]);
        $userIds[] = $user->id;

        DB::table('user_profiles')->insert([
            'user_id'        => $user->id,
            'type'           => 'company',
            'segment'        => $segment->value,
            'segment_source' => 'manual',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    $orders = [];
    $end    = Carbon::parse($endsOn)->startOfMonth();
    $sequence = 0;

    for ($m = $months - 1; $m >= 0; $m--) {
        $month = $end->copy()->subMonths($m);
        // Orders per client this month, scaled by the planted shape.
        $perClient = (int) round(4 * $shape[$month->month - 1]);

        foreach ($userIds as $userId) {
            for ($k = 0; $k < $perClient; $k++) {
                $day = $month->copy()->addDays(($k * 3) % 27);
                $sequence++;

                $orders[] = [
                    'order_number'   => 'WF-TEST-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'user_id'        => $userId,
                    'status'         => OrderStatus::Delivered,
                    'payment_status' => 'paid',
                    'total_amount'   => 80,
                    'created_at'     => $day->toDateTimeString(),
                    'updated_at'     => $day->toDateTimeString(),
                ];
            }
        }
    }

    foreach (array_chunk($orders, 500) as $chunk) {
        DB::table('orders')->insert($chunk);
    }

    $items = DB::table('orders')->pluck('id')->map(fn ($id) => [
        'order_id'   => $id,
        'product_id' => test()->product->id,
        'quantity'   => 4,
        'unit_price' => 20,
        'subtotal'   => 80,
        'is_gift'    => false,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all();

    foreach (array_chunk($items, 500) as $chunk) {
        DB::table('order_items')->insert($chunk);
    }
}

it('stays on priors until there is a full seasonal cycle of history', function () {
    Carbon::setTestNow('2026-08-21');

    // Eight months: not enough to distinguish season from trend.
    plantHistory(ClientSegment::Unknown, array_fill(0, 12, 1.0), clients: 4, months: 8, endsOn: '2026-08-01');

    $seasonality = app(SeasonalityService::class);

    expect($seasonality->learningEnabled())->toBeFalse();

    $report = $seasonality->recompute();

    expect($report['learning'])->toBeFalse()
        ->and(DemandSeasonality::where('segment', ClientSegment::Unknown->value)->pluck('source')->unique()->all())
        ->toBe(['prior']);

    Carbon::setTestNow();
});

it('recovers a planted seasonal shape once enough history exists', function () {
    Carbon::setTestNow('2026-12-21');

    // A deliberately summer-heavy shape planted into the Unknown segment,
    // whose prior is flat — so anything the curve learns can only have come
    // from the data, not from the prior leaking through.
    $shape = [0.5, 0.5, 0.75, 1.0, 1.5, 2.0, 2.25, 2.0, 1.25, 0.75, 0.5, 0.5];

    plantHistory(ClientSegment::Unknown, $shape, clients: 6, months: 30, endsOn: '2026-12-01');

    $seasonality = app(SeasonalityService::class);

    expect($seasonality->learningEnabled())->toBeTrue();

    $seasonality->recompute();
    $seasonality->flush();

    $curve = $seasonality->curveFor(ClientSegment::Unknown);

    // July was planted at 4.5x January. The measured curve must reproduce the
    // ordering and a substantial part of the amplitude; shrinkage toward the
    // flat prior deliberately pulls it in, so an exact match is not expected.
    expect($curve[7])->toBeGreaterThan($curve[1] * 2.0)
        ->and($curve[6])->toBeGreaterThan($curve[12])
        ->and($curve[1])->toBeLessThan(1.0)
        ->and($curve[7])->toBeGreaterThan(1.0);

    // The invariant the rest of the forecaster depends on.
    $mean = array_sum($curve) / 12;
    expect($mean)->toBeGreaterThan(0.98)->and($mean)->toBeLessThan(1.02);

    Carbon::setTestNow();
});

it('records that measured months came from data rather than the prior', function () {
    Carbon::setTestNow('2026-12-21');

    plantHistory(ClientSegment::Unknown, array_fill(0, 12, 1.0), clients: 6, months: 30, endsOn: '2026-12-01');

    app(SeasonalityService::class)->recompute();

    $sources = DemandSeasonality::where('segment', ClientSegment::Unknown->value)->pluck('source', 'month');

    expect($sources->filter(fn ($s) => $s !== 'prior')->count())->toBeGreaterThan(0);

    Carbon::setTestNow();
});

it('never lets a manual override be overwritten by recomputation', function () {
    Carbon::setTestNow('2026-12-21');

    plantHistory(ClientSegment::Unknown, array_fill(0, 12, 1.0), clients: 6, months: 30, endsOn: '2026-12-01');

    DemandSeasonality::updateOrCreate(
        ['segment' => ClientSegment::Unknown->value, 'month' => 7],
        ['index' => 0.25, 'source' => 'manual', 'sample_size' => 0],
    );

    app(SeasonalityService::class)->recompute();

    $row = DemandSeasonality::where('segment', ClientSegment::Unknown->value)->where('month', 7)->first();

    expect((float) $row->index)->toBe(0.25)
        ->and($row->source)->toBe('manual');

    Carbon::setTestNow();
});

it('clamps a freak month instead of letting it become a permanent multiplier', function () {
    expect((float) config('forecasting.index_ceiling'))->toBeLessThanOrEqual(3.0)
        ->and((float) config('forecasting.index_floor'))->toBeGreaterThan(0.0);

    Carbon::setTestNow('2026-12-21');

    // One month with twenty times the normal volume.
    $shape = array_fill(0, 12, 1.0);
    $shape[6] = 20.0;

    plantHistory(ClientSegment::Unknown, $shape, clients: 6, months: 30, endsOn: '2026-12-01');

    app(SeasonalityService::class)->recompute();
    app(SeasonalityService::class)->flush();

    $curve = app(SeasonalityService::class)->curveFor(ClientSegment::Unknown);

    expect($curve[7])->toBeLessThanOrEqual((float) config('forecasting.index_ceiling'));

    Carbon::setTestNow();
});
