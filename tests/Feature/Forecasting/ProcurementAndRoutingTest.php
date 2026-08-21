<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Forecasting\ProcurementForecastService;
use App\Services\Forecasting\RoutePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([\App\Events\OrderCreated::class]);

    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L'],
        'sku'        => 'B19P',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 1000,
    ]);

    $this->bottle = RawMaterial::create([
        'name'          => 'Bottle 19L container',
        'sku'           => 'RM-B19',
        'unit'          => 'pcs',
        'current_stock' => 100,
        'cost_per_unit' => 25,
        'deposit_price' => 30,
        'status'        => 'active',
        'is_reusable'   => true,
    ]);

    $this->cap = RawMaterial::create([
        'name'          => 'Cap',
        'sku'           => 'RM-CAP',
        'unit'          => 'pcs',
        'current_stock' => 50,
        'cost_per_unit' => 0.5,
        'deposit_price' => 0,
        'status'        => 'active',
        'is_reusable'   => false,
    ]);

    $this->product->rawMaterials()->attach([
        $this->bottle->id => ['quantity' => 1],
        $this->cap->id    => ['quantity' => 1],
    ]);
});

it('only needs to replace the reusable containers that never come back', function () {
    // 100 issued, 90 returned: a 90% return rate.
    $order = new Order([
        'user_id'      => User::factory()->create()->id,
        'status'       => OrderStatus::Delivered,
        'total_amount' => 2000,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 100,
        'unit_price' => 20,
        'subtotal'   => 2000,
        'is_gift'    => false,
    ]);
    $order->returnedMaterials()->attach($this->bottle->id, ['quantity' => 90, 'deferred_quantity' => 0]);

    $service = app(ProcurementForecastService::class);

    expect($service->returnRates()[$this->bottle->id])->toBe(0.9);

    $requirements = $service->requirements([
        ['product_id' => $this->product->id, 'units' => 1000.0],
    ]);

    $bottle = collect($requirements['materials'])->firstWhere('id', $this->bottle->id);
    $cap    = collect($requirements['materials'])->firstWhere('id', $this->cap->id);

    // 1000 bottles will circulate, but only the 10% that go missing have to
    // be bought. Treating throughput as the purchase requirement would order
    // ten times too many.
    expect($bottle['circulating'])->toBe(1000.0)
        ->and($bottle['net_required'])->toBe(100.0)
        // Caps are consumed outright, so gross is the requirement.
        ->and($cap['net_required'])->toBe(1000.0);
});

it('treats a deferred return as a timing difference, not a loss', function () {
    $order = new Order([
        'user_id'      => User::factory()->create()->id,
        'status'       => OrderStatus::Delivered,
        'total_amount' => 2000,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 100,
        'unit_price' => 20,
        'subtotal'   => 2000,
        'is_gift'    => false,
    ]);
    $order->returnedMaterials()->attach($this->bottle->id, ['quantity' => 40, 'deferred_quantity' => 60]);

    expect(app(ProcurementForecastService::class)->returnRates()[$this->bottle->id])->toBe(1.0);
});

it('assumes total loss for a reusable material with no return history yet', function () {
    $service = app(ProcurementForecastService::class);

    $requirements = $service->requirements([
        ['product_id' => $this->product->id, 'units' => 200.0],
    ]);

    $bottle = collect($requirements['materials'])->firstWhere('id', $this->bottle->id);

    // Nothing measured, so the plan is a worst case rather than a guess, and
    // unmeasuredReusables() lets the UI say so out loud.
    expect($bottle['return_rate'])->toBeNull()
        ->and($bottle['net_required'])->toBe(200.0)
        ->and($service->unmeasuredReusables())->toHaveKey($this->bottle->id);
});

it('reports days of cover so urgency is visible, not just shortfall', function () {
    $service = app(ProcurementForecastService::class);

    $requirements = $service->withCoverage(
        $service->requirements([['product_id' => $this->product->id, 'units' => 300.0]]),
        horizonDays: 30,
    );

    $cap = collect($requirements['materials'])->firstWhere('id', $this->cap->id);

    // 300 caps over 30 days is 10 a day against 50 in stock.
    expect($cap['daily_burn'])->toBe(10.0)
        ->and($cap['days_of_cover'])->toBe(5);
});

it('never loads a vehicle beyond its capacity', function () {
    Carbon::setTestNow('2026-07-01');

    $date = Carbon::parse('2026-07-02');

    // Nine stops of 20 bottles each against a 50-bottle vehicle: at least
    // four routes are unavoidable.
    foreach (range(1, 9) as $i) {
        $client = User::factory()->create(['name' => "Client {$i}"]);
        UserAddress::create([
            'user_id'      => $client->id,
            'label'        => 'Home',
            'address_line' => "Street {$i}",
            'lat'          => 38.55 + $i * 0.01,
            'lng'          => 68.78 + $i * 0.01,
            'is_default'   => true,
        ]);

        $order = new Order([
            'user_id'               => $client->id,
            'status'                => OrderStatus::Confirmed,
            'scheduled_delivery_at' => $date->copy()->setTime(10, 0),
            'total_amount'          => 400,
        ]);
        $order->save();
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity'   => 20,
            'unit_price' => 20,
            'subtotal'   => 400,
            'is_gift'    => false,
        ]);
    }

    $plan = app(RoutePlanner::class)->plan($date, ['capacity' => 50, 'max_stops' => 25]);

    expect($plan['summary']['stops'])->toBe(9)
        ->and($plan['summary']['units'])->toBe(180.0)
        ->and(count($plan['routes']))->toBeGreaterThanOrEqual(4);

    foreach ($plan['routes'] as $route) {
        expect($route['units'])->toBeLessThanOrEqual(50.0);
    }

    // Every stop must appear exactly once across the plan; a routing pass that
    // quietly drops deliveries is worse than no plan at all.
    $planned = collect($plan['routes'])->flatMap(fn ($r) => collect($r['stops'])->pluck('client_id'));
    expect($planned->unique()->count())->toBe(9);

    Carbon::setTestNow();
});

it('surfaces stops it cannot place on the map instead of dropping them', function () {
    Carbon::setTestNow('2026-07-01');

    $date   = Carbon::parse('2026-07-02');
    $client = User::factory()->create(['name' => 'No coordinates']);

    $order = new Order([
        'user_id'               => $client->id,
        'status'                => OrderStatus::Confirmed,
        'scheduled_delivery_at' => $date->copy()->setTime(10, 0),
        'total_amount'          => 100,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 5,
        'unit_price' => 20,
        'subtotal'   => 100,
        'is_gift'    => false,
    ]);

    $plan = app(RoutePlanner::class)->plan($date);

    expect($plan['routes'])->toBeEmpty()
        ->and($plan['unlocated'])->toHaveCount(1)
        ->and($plan['summary']['geocoded_pct'])->toBe(0.0)
        // The units still count toward the day, so procurement is unaffected
        // by a missing pin.
        ->and($plan['summary']['units'])->toBe(5.0);

    Carbon::setTestNow();
});

it('respects the confidence threshold before routing a predicted stop', function () {
    Carbon::setTestNow('2026-07-01');

    $plan = app(RoutePlanner::class)->plan(Carbon::parse('2026-07-02'), ['min_probability' => 0.99]);

    expect($plan['summary']['predicted_stops'])->toBe(0);

    Carbon::setTestNow();
});
