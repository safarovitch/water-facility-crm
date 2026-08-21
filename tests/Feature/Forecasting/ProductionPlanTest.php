<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionRun;
use App\Models\User;
use App\Services\Forecasting\ProductionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([\App\Events\OrderCreated::class]);

    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L'],
        'sku'        => 'B19PR',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 0,
    ]);

    Carbon::setTestNow('2026-08-21');
});

afterEach(fn () => Carbon::setTestNow());

/** An open order due for delivery on a date. */
function dueOn(string $date, int $units, bool $gift = false, ?int $userId = null): Order
{
    $order = new Order([
        'user_id'               => $userId ?? User::factory()->create()->id,
        'status'                => OrderStatus::Confirmed,
        'scheduled_delivery_at' => Carbon::parse($date . ' 10:00'),
        'total_amount'          => $units * 20,
    ]);
    $order->save();

    $order->items()->create([
        'product_id' => test()->product->id,
        'quantity'   => $units,
        'unit_price' => 20,
        'subtotal'   => $units * 20,
        'is_gift'    => $gift,
    ]);

    return $order;
}

/** Set the ready-stock anchor. */
function countStock(string $date, int $units): void
{
    app(ProductionPlanService::class)->recordCount(Carbon::parse($date), test()->product->id, $units);
}

function planFor(string $from, ?string $to = null): array
{
    $plan = app(ProductionPlanService::class)->plan(Carbon::parse($from), Carbon::parse($to ?? $from));

    return collect($plan['products'])->firstWhere('product_id', test()->product->id) ?? [];
}

it('tells you to fill exactly what the day needs, less what is already ready', function () {
    countStock('2026-08-21', 20);
    dueOn('2026-08-22', 180);

    $plan = planFor('2026-08-22');

    expect($plan['needed'])->toBe(180)
        ->and($plan['ready_now'])->toBe(20)
        ->and($plan['to_fill'])->toBe(160);
});

it('asks for nothing when stock already covers the day', function () {
    countStock('2026-08-21', 300);
    dueOn('2026-08-22', 180);

    expect(planFor('2026-08-22')['to_fill'])->toBe(0);
});

it('counts gift bottles, because a free bottle is still a real bottle', function () {
    countStock('2026-08-21', 0);
    dueOn('2026-08-22', 100);
    dueOn('2026-08-22', 10, gift: true);

    expect(planFor('2026-08-22')['needed'])->toBe(110);
});

it('derives ready stock from the count, plus production, minus deliveries', function () {
    countStock('2026-08-18', 100);

    app(ProductionPlanService::class)->recordProduction(Carbon::parse('2026-08-19'), $this->product->id, 50);

    $delivered = dueOn('2026-08-20', 30);
    $delivered->update(['status' => OrderStatus::Delivered, 'actual_delivery_at' => Carbon::parse('2026-08-20 12:00')]);

    // 100 counted + 50 filled - 30 delivered = 120
    $stock = app(ProductionPlanService::class)->readyStock($this->product->id, Carbon::parse('2026-08-20'));

    expect($stock['units'])->toBe(120.0)
        ->and($stock['has_count'])->toBeTrue()
        ->and($stock['counted_on'])->toBe('2026-08-18');
});

it('lets a fresh count override the running arithmetic', function () {
    countStock('2026-08-18', 100);
    app(ProductionPlanService::class)->recordProduction(Carbon::parse('2026-08-19'), $this->product->id, 50);

    // Somebody walks the warehouse on the 20th and finds 60, not 150. The
    // newer count wins outright — staff should never have to hunt for the
    // entry that made the balance wrong.
    countStock('2026-08-20', 60);

    $stock = app(ProductionPlanService::class)->readyStock($this->product->id, Carbon::parse('2026-08-20'));

    expect($stock['units'])->toBe(60.0)
        ->and($stock['counted_on'])->toBe('2026-08-20');
});

it('subtracts only what the courier actually handed over on a short delivery', function () {
    countStock('2026-08-18', 100);

    $order = dueOn('2026-08-19', 40);
    $order->update(['status' => OrderStatus::Delivered, 'actual_delivery_at' => Carbon::parse('2026-08-19 12:00')]);
    // Ordered 40, only 25 fit on the van. The other 15 never left stock.
    $order->items()->first()->update(['delivered_quantity' => 25]);

    expect(app(ProductionPlanService::class)->readyStock($this->product->id, Carbon::parse('2026-08-19'))['units'])
        ->toBe(75.0);
});

it('says plainly when nobody has counted stock, instead of assuming zero is right', function () {
    dueOn('2026-08-22', 50);

    $plan = planFor('2026-08-22');

    expect($plan['has_count'])->toBeFalse()
        ->and($plan['ready_now'])->toBe(0)
        ->and($plan['counted_on'])->toBeNull();
});

it('carries stock forward across a range so no day over-produces', function () {
    countStock('2026-08-21', 0);
    dueOn('2026-08-22', 100);
    dueOn('2026-08-23', 100);
    dueOn('2026-08-24', 100);

    $plan = planFor('2026-08-22', '2026-08-24');

    // Just-in-time: with no stock to start, every day fills exactly its own
    // demand and carries nothing into the next. Asserting the invariant rather
    // than a literal 300 keeps the test honest — each of those orders belongs
    // to a different client, so the forecast legitimately adds a little on top
    // of the confirmed 100.
    foreach ($plan['days'] as $day) {
        expect($day['to_fill'])->toBe($day['needed'] - $day['opening'] - $day['recorded'])
            ->and($day['closing'])->toBe(0)
            ->and($day['to_fill'])->toBeGreaterThanOrEqual(100);
    }

    expect($plan['to_fill'])->toBeGreaterThanOrEqual(300);
});

it('spends existing stock before filling anything new', function () {
    countStock('2026-08-21', 150);
    dueOn('2026-08-22', 100);
    dueOn('2026-08-23', 100);

    $days = collect(planFor('2026-08-22', '2026-08-23')['days'])->keyBy('date');

    // 150 in stock covers day one outright, and what is left carries into day
    // two so only the balance gets filled.
    expect($days['2026-08-22']['to_fill'])->toBe(0)
        ->and($days['2026-08-23']['opening'])->toBeGreaterThan(0)
        ->and($days['2026-08-23']['to_fill'])->toBeGreaterThan(0)
        ->and($days['2026-08-23']['to_fill'])->toBeLessThan($days['2026-08-23']['needed']);
});

it('drops the requirement once production has been recorded for that day', function () {
    countStock('2026-08-21', 0);
    dueOn('2026-08-22', 180);

    expect(planFor('2026-08-22')['to_fill'])->toBe(180);

    app(ProductionPlanService::class)->recordProduction(Carbon::parse('2026-08-22'), $this->product->id, 180);

    $plan = planFor('2026-08-22');

    expect($plan['recorded'])->toBe(180)
        ->and($plan['to_fill'])->toBe(0);
});

it('replaces a day figure when it is recorded again, rather than adding to it', function () {
    $service = app(ProductionPlanService::class);

    $service->recordProduction(Carbon::parse('2026-08-22'), $this->product->id, 200);
    $service->recordProduction(Carbon::parse('2026-08-22'), $this->product->id, 150);

    expect(ProductionRun::production()->where('product_id', $this->product->id)->count())->toBe(1)
        ->and((int) ProductionRun::production()->first()->units)->toBe(150);
});

it('includes a subscription delivery that has no order yet', function () {
    countStock('2026-08-21', 0);

    $client = User::factory()->create();
    $subscription = $client->subscriptions()->create([
        'status'           => 'active',
        'frequency'        => 'weekly',
        'delivery_address' => 'Test',
        'next_delivery_at' => Carbon::parse('2026-08-24'),
    ]);
    $subscription->items()->create(['product_id' => $this->product->id, 'quantity' => 12]);

    expect(planFor('2026-08-24')['needed'])->toBe(12);
});

it('rounds a fractional forecast up, never down', function () {
    countStock('2026-08-21', 0);

    // Nobody fills 6.4 bottles, and rounding down would guarantee a shortfall.
    $plan = planFor('2026-08-22');

    expect($plan['needed'])->toBeInt()
        ->and($plan['to_fill'])->toBeInt();
});

it('puts an open order with no delivery date on the first day rather than nowhere', function () {
    countStock('2026-08-21', 0);

    $order = new Order([
        'user_id'      => User::factory()->create()->id,
        'status'       => OrderStatus::Confirmed,
        'total_amount' => 400,
    ]);
    $order->save();
    $order->items()->create([
        'product_id' => $this->product->id,
        'quantity'   => 20,
        'unit_price' => 20,
        'subtotal'   => 400,
        'is_gift'    => false,
    ]);

    expect(planFor('2026-08-21')['needed'])->toBe(20);
});
