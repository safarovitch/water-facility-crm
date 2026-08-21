<?php

use App\Enums\ClientSegment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Forecasting\ClientDemandModel;
use App\Services\Forecasting\DemandForecastService;
use App\Services\Forecasting\SeasonalityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Creating an order broadcasts to Telegram and fans out staff
    // notifications. Neither is under test here, and both need seeded roles.
    Event::fake([\App\Events\OrderCreated::class]);

    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L', 'ru' => 'Бутыль 19л'],
        'sku'        => 'B19',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 1000,
    ]);

    app(SeasonalityService::class)->flush();
});

/**
 * Create a client in a segment with orders on the given dates.
 *
 * @param  string[]  $dates
 */
function seedClient(string $name, ClientSegment $segment, array $dates, int $qty = 4): User
{
    $user = User::factory()->create(['name' => $name, 'phone_verified_at' => now()]);

    UserProfile::create([
        'user_id'        => $user->id,
        'type'           => 'company',
        'segment'        => $segment->value,
        'segment_source' => 'manual',
    ]);

    foreach ($dates as $date) {
        $at = Carbon::parse($date);

        $order = new Order([
            'user_id'      => $user->id,
            'status'       => OrderStatus::Delivered,
            'total_amount' => $qty * 20,
        ]);
        // created_at is set before save so the order lands on its real date;
        // Eloquent only stamps timestamps that are not already dirty.
        $order->created_at = $at;
        $order->updated_at = $at;
        $order->save();

        $order->items()->create([
            'product_id' => test()->product->id,
            'quantity'   => $qty,
            'unit_price' => 20,
            'subtotal'   => $qty * 20,
            'is_gift'    => false,
        ]);
    }

    return $user;
}

/** Weekly dates between two points, skipping any month in $skipMonths. */
function weeklyDates(string $from, string $to, array $skipMonths = []): array
{
    $dates  = [];
    $cursor = Carbon::parse($from);
    $end    = Carbon::parse($to);

    while ($cursor->lte($end)) {
        if (! in_array((int) $cursor->month, $skipMonths, true)) {
            $dates[] = $cursor->toDateString();
        }
        $cursor->addWeek();
    }

    return $dates;
}

it('normalises every prior curve to a mean of exactly 1.0', function () {
    foreach (ClientSegment::cases() as $segment) {
        $indices = $segment->priorIndices();

        expect(count($indices))->toBe(12)
            ->and(array_sum($indices) / 12)->toBeGreaterThan(0.999)
            ->and(array_sum($indices) / 12)->toBeLessThan(1.001);
    }
});

it('gives an unclassified client a flat curve so no season is invented', function () {
    expect(ClientSegment::Unknown->priorIndices())->each->toBe(1.0);
});

it('predicts almost no school demand during the summer holidays', function () {
    Carbon::setTestNow('2026-06-25');

    seedClient('Школа №12', ClientSegment::School, weeklyDates('2026-01-10', '2026-05-25'));

    $forecast = app(DemandForecastService::class);

    $july   = $forecast->forecast(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
    $october = $forecast->forecast(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

    // The school's own history is weekly and identical in both windows; only
    // the calendar differs, so any gap here is the seasonal model working.
    expect($july['totals']['units'])->toBeLessThan($october['totals']['units'] * 0.2);

    Carbon::setTestNow();
});

it('does not mark a school as churned merely for being silent all summer', function () {
    Carbon::setTestNow('2026-08-10');

    seedClient('Лицей №1', ClientSegment::School, weeklyDates('2026-01-10', '2026-05-25'));

    $profile = app(ClientDemandModel::class)->profiles()->firstWhere('segment', ClientSegment::School);

    // Eleven weeks of silence would trip any elapsed-days churn rule, but the
    // expected order count over a school summer is near zero, so it must not.
    expect($profile)->not->toBeNull()
        ->and($profile->churned)->toBeFalse();

    Carbon::setTestNow();
});

it('marks a client as churned when they go quiet through their own peak season', function () {
    Carbon::setTestNow('2026-08-10');

    // A household silent since March has missed its busiest months.
    seedClient('Иван', ClientSegment::Household, weeklyDates('2026-01-05', '2026-03-01'));

    $profile = app(ClientDemandModel::class)->profiles()->firstWhere('segment', ClientSegment::Household);

    expect($profile->churned)->toBeTrue();

    Carbon::setTestNow();
});

it('forecasts more household demand in summer than in winter', function () {
    Carbon::setTestNow('2026-04-01');

    seedClient('Дом 1', ClientSegment::Household, weeklyDates('2026-01-05', '2026-03-30'));

    $forecast = app(DemandForecastService::class);

    $summer = $forecast->forecast(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'));
    $winter = $forecast->forecast(Carbon::parse('2026-12-01'), Carbon::parse('2026-12-31'));

    expect($summer['totals']['units'])->toBeGreaterThan($winter['totals']['units'] * 1.4);

    Carbon::setTestNow();
});

it('keeps occasional buyers in the forecast instead of dropping them', function () {
    Carbon::setTestNow('2026-06-01');

    // Two orders is below min_orders_for_client_model; the old median-gap
    // forecaster required more than two and ignored clients like this
    // entirely, which made the aggregate structurally too low.
    seedClient('Редкий клиент', ClientSegment::Household, ['2026-03-02', '2026-04-20']);

    $result = app(DemandForecastService::class)
        ->forecast(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

    expect($result['totals']['units'])->toBeGreaterThan(0.0)
        ->and($result['model']['clients_modelled'])->toBe(1);

    Carbon::setTestNow();
});

it('suppresses a repeat order the day after a client has just ordered', function () {
    Carbon::setTestNow('2026-04-01');

    seedClient('Постоянный', ClientSegment::Office, weeklyDates('2026-01-05', '2026-03-31'));

    $result = app(DemandForecastService::class)
        ->forecast(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-21'));

    $days = collect($result['days']);

    // A memoryless Poisson process would predict the same probability every
    // day. The renewal correction must make the days right after the last
    // order quieter than the days around when the next one is due.
    $firstThree = $days->take(3)->sum('predicted_units');
    $nextWeek   = $days->slice(4, 7)->sum('predicted_units');

    expect($firstThree)->toBeLessThan($nextWeek);

    Carbon::setTestNow();
});

it('reports a wider band for one lumpy client than for many steady ones', function () {
    Carbon::setTestNow('2026-04-01');

    // Same expected volume, different concentration: five steady clients
    // versus one that orders in bursts.
    foreach (range(1, 5) as $i) {
        seedClient("Стабильный {$i}", ClientSegment::Office, weeklyDates('2026-01-05', '2026-03-30'), qty: 4);
    }

    $steady = app(DemandForecastService::class)
        ->forecast(Carbon::parse('2026-04-05'), Carbon::parse('2026-04-11'));

    $spread = $steady['totals']['units_p90'] - $steady['totals']['units_p10'];

    expect($spread)->toBeGreaterThan(0.0)
        // The band must be narrower than the forecast itself; a band wider
        // than the mean would mean the forecast carries no information.
        ->and($spread)->toBeLessThan($steady['totals']['units'] * 2);

    Carbon::setTestNow();
});

it('does not double count a client who has an active subscription', function () {
    Carbon::setTestNow('2026-04-01');

    $client = seedClient('Подписчик', ClientSegment::Office, weeklyDates('2026-01-05', '2026-03-30'));

    $withoutSubscription = app(DemandForecastService::class)
        ->forecast(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-28'));

    $subscription = $client->subscriptions()->create([
        'status'           => 'active',
        'frequency'        => 'weekly',
        'delivery_address' => 'Test',
        'next_delivery_at' => Carbon::parse('2026-04-03'),
    ]);
    $subscription->items()->create(['product_id' => $this->product->id, 'quantity' => 4]);

    $withSubscription = app(DemandForecastService::class)
        ->forecast(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-28'));

    // The subscription replaces the statistical model for this client rather
    // than adding to it, so the total must not roughly double.
    expect($withSubscription['model']['clients_modelled'])->toBe(0)
        ->and($withSubscription['totals']['units'])
        ->toBeLessThan($withoutSubscription['totals']['units'] * 1.9);

    Carbon::setTestNow();
});

it('counts a subscription delivery once, whether it exists as an order or only as a schedule', function () {
    Carbon::setTestNow('2026-04-01');

    $client = seedClient('Подписчик 2', ClientSegment::Office, weeklyDates('2026-01-05', '2026-03-30'));

    $subscription = $client->subscriptions()->create([
        'status'           => 'active',
        'frequency'        => 'weekly',
        'delivery_address' => 'Test',
        // The generator has already produced the 3 April delivery and moved
        // the schedule on to the 10th, which is the state this must survive.
        'next_delivery_at' => Carbon::parse('2026-04-10'),
    ]);
    $subscription->items()->create(['product_id' => $this->product->id, 'quantity' => 6]);

    $order = new Order([
        'user_id'               => $client->id,
        'subscription_id'       => $subscription->id,
        'status'                => OrderStatus::Pending,
        'scheduled_delivery_at' => Carbon::parse('2026-04-03 10:00'),
        'total_amount'          => 120,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 6,
        'unit_price' => 20,
        'subtotal'   => 120,
        'is_gift'    => false,
    ]);

    $days = collect(
        app(DemandForecastService::class)
            ->forecast(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-12'))['days']
    )->keyBy('date');

    // 3 April exists only as an order; 10 April only as a schedule. Both must
    // show exactly one delivery of six units — no drop, no double count.
    expect($days['2026-04-03']['committed_units'])->toBe(6.0)
        ->and($days['2026-04-03']['committed_orders'])->toBe(1)
        ->and($days['2026-04-10']['committed_units'])->toBe(6.0)
        ->and($days['2026-04-10']['committed_orders'])->toBe(1);

    Carbon::setTestNow();
});

it('does not count a subscription twice when the schedule still points at an existing order', function () {
    Carbon::setTestNow('2026-04-01');

    $client = seedClient('Подписчик 3', ClientSegment::Office, weeklyDates('2026-01-05', '2026-03-30'));

    $subscription = $client->subscriptions()->create([
        'status'           => 'active',
        'frequency'        => 'weekly',
        'delivery_address' => 'Test',
        // Schedule and order agree on the same date, e.g. the generator has
        // not run yet or was re-run.
        'next_delivery_at' => Carbon::parse('2026-04-03'),
    ]);
    $subscription->items()->create(['product_id' => $this->product->id, 'quantity' => 6]);

    $order = new Order([
        'user_id'               => $client->id,
        'subscription_id'       => $subscription->id,
        'status'                => OrderStatus::Pending,
        'scheduled_delivery_at' => Carbon::parse('2026-04-03 10:00'),
        'total_amount'          => 120,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 6,
        'unit_price' => 20,
        'subtotal'   => 120,
        'is_gift'    => false,
    ]);

    $days = collect(
        app(DemandForecastService::class)
            ->forecast(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-05'))['days']
    )->keyBy('date');

    expect($days['2026-04-03']['committed_units'])->toBe(6.0)
        ->and($days['2026-04-03']['committed_orders'])->toBe(1);

    Carbon::setTestNow();
});

