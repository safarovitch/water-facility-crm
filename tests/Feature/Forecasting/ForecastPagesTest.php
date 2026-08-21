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

