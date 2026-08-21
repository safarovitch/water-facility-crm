<?php

use App\Enums\ClientSegment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The admin routes name every tier in their role middleware, so all of
    // them have to exist for the request to get through.
    foreach ([...User::ADMIN_ROLES, ...User::COURIER_ROLES, 'Client'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    Product::create([
        'name'       => ['en' => 'Bottle 19L'],
        'sku'        => 'B19W',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 100,
    ]);
});

function admin(): User
{
    $user = User::factory()->create(['phone_verified_at' => now()]);
    $user->assignRole('Admin');

    return $user;
}

function courier(): User
{
    $user = User::factory()->create(['phone_verified_at' => now()]);
    $user->assignRole('Currier');

    return $user;
}

it('renders every forecasting page for an admin', function (string $path, string $component) {
    $this->actingAs(admin())
        ->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/admin/forecasts/demand', 'forecasts/Demand'],
    ['/admin/forecasts/accuracy', 'forecasts/Accuracy'],
    ['/admin/forecasts/seasonality', 'forecasts/Seasonality'],
    ['/admin/forecasts/segments', 'forecasts/Segments'],
    ['/admin/forecasts/routes', 'forecasts/Routes'],
]);

it('keeps company-wide demand and route planning away from plain couriers', function (string $path) {
    $this->actingAs(courier())->get($path)->assertForbidden();
})->with([
    ['/admin/forecasts/routes'],
]);

it('refuses a seasonality index outside the allowed range', function () {
    $this->actingAs(admin())
        ->post('/admin/forecasts/seasonality', [
            'segment' => ClientSegment::School->value,
            'month'   => 7,
            'index'   => 99,
        ])
        ->assertSessionHasErrors('index');
});

it('lets an admin pin a seasonality month by hand', function () {
    $this->actingAs(admin())
        ->post('/admin/forecasts/seasonality', [
            'segment' => ClientSegment::School->value,
            'month'   => 7,
            'index'   => 0.1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('demand_seasonality', [
        'segment' => ClientSegment::School->value,
        'month'   => 7,
        'source'  => 'manual',
    ]);
});

it('marks a segment set through the UI as manual so classifiers leave it alone', function () {
    $client = User::factory()->create();
    $client->assignRole('Client');

    $this->actingAs(admin())
        ->post("/admin/forecasts/segments/{$client->id}", ['segment' => ClientSegment::School->value])
        ->assertRedirect();

    $profile = UserProfile::where('user_id', $client->id)->first();

    expect($profile->segment)->toBe(ClientSegment::School)
        ->and($profile->segment_source)->toBe('manual')
        ->and($profile->segmentIsLocked())->toBeTrue();
});

it('rejects a segment that is not in the vocabulary', function () {
    $client = User::factory()->create();
    $client->assignRole('Client');

    $this->actingAs(admin())
        ->post("/admin/forecasts/segments/{$client->id}", ['segment' => 'spaceport'])
        ->assertSessionHasErrors('segment');
});

it('renders the production plan for a plain courier, who has to read it daily', function () {
    // Viewing is staff-tier on purpose: the person filling bottles needs the
    // number. Only the totals pages are held back from couriers.
    $this->actingAs(courier())
        ->get('/admin/production')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('production/Plan'));
});

it('does not let a plain courier record production or re-count stock', function () {
    $product = Product::first();

    $this->actingAs(courier())
        ->post('/admin/production/record', ['product_id' => $product->id, 'date' => '2026-08-22', 'units' => 50])
        ->assertForbidden();

    $this->actingAs(courier())
        ->post('/admin/production/count', ['product_id' => $product->id, 'units' => 50])
        ->assertForbidden();
});

it('lets a manager record production and a stock count', function () {
    $product = Product::first();

    $this->actingAs(admin())
        ->post('/admin/production/record', ['product_id' => $product->id, 'date' => '2026-08-22', 'units' => 240])
        ->assertRedirect();

    $this->actingAs(admin())
        ->post('/admin/production/count', ['product_id' => $product->id, 'units' => 60])
        ->assertRedirect();

    $this->assertDatabaseHas('production_runs', ['product_id' => $product->id, 'type' => 'production', 'units' => 240]);
    $this->assertDatabaseHas('production_runs', ['product_id' => $product->id, 'type' => 'count', 'units' => 60]);
});

it('rejects a negative production figure', function () {
    $product = Product::first();

    $this->actingAs(admin())
        ->post('/admin/production/record', ['product_id' => $product->id, 'date' => '2026-08-22', 'units' => -5])
        ->assertSessionHasErrors('units');
});

