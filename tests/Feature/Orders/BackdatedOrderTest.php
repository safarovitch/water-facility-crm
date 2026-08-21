<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The admin routes name every tier in their role middleware, so all of
    // them have to exist for the request to get through.
    foreach ([...User::ADMIN_ROLES, ...User::COURIER_ROLES, 'Client'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->product = Product::create([
        'name'       => ['en' => 'Bottle 19L', 'ru' => 'Бутыль 19л'],
        'sku'        => 'B19',
        'price'      => 20,
        'sale_price' => 0,
        'cost'       => 10,
        'weight'     => 1.5,
        'dimensions' => ['h' => 40],
        'currency'   => 'TJS',
        'quantity'   => 100,
    ]);

    $this->client = User::factory()->create(['phone_verified_at' => now()]);
    $this->client->assignRole('Client');
});

/** Payload for POST /admin/orders/store, optionally carrying an order date. */
function orderPayload(User $client, Product $product, ?string $createdAt = null): array
{
    return array_filter([
        'user_id'    => $client->id,
        'created_at' => $createdAt,
        'items'      => [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 20],
        ],
    ]);
}

function staffUser(string $role): User
{
    $user = User::factory()->create(['phone_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('records the order under the date an admin picked', function () {
    $backdate = now()->subDays(3)->setTime(14, 30);

    $this->actingAs(staffUser('Admin'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product, $backdate->format('Y-m-d\TH:i')))
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->created_at->format('Y-m-d H:i'))->toBe($backdate->format('Y-m-d H:i'))
        // updated_at moves with it, so a backdated order doesn't look edited.
        ->and($order->updated_at->format('Y-m-d H:i'))->toBe($backdate->format('Y-m-d H:i'));
});

it('stamps the current time when no date is given', function () {
    $this->actingAs(staffUser('Admin'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product))
        ->assertRedirect();

    expect(Order::latest('id')->first()->created_at->diffInMinutes(now()))->toBeLessThan(2);
});

it('numbers a backdated order in the year it belongs to', function () {
    $lastYear = now()->subYear()->startOfYear()->addMonths(2);
    $this->actingAs(staffUser('Admin'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product, $lastYear->format('Y-m-d\TH:i')))
        ->assertRedirect();

    expect(Order::latest('id')->first()->order_number)
        ->toBe('WF-' . $lastYear->format('Y') . '-00001');
});

it('does not collide with numbers already issued for that year', function () {
    $lastYear = now()->subYear()->startOfYear()->addMonths(2);

    // An order that already carries this year's first number.
    $existing = new Order([
        'user_id'      => $this->client->id,
        'total_amount' => 0,
    ]);
    $existing->created_at = $lastYear;
    $existing->save();

    $this->actingAs(staffUser('Admin'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product, $lastYear->format('Y-m-d\TH:i')))
        ->assertRedirect();

    expect($existing->fresh()->order_number)->toBe('WF-' . $lastYear->format('Y') . '-00001')
        ->and(Order::latest('id')->first()->order_number)->toBe('WF-' . $lastYear->format('Y') . '-00002');
});

it('rejects a date in the future', function () {
    $this->actingAs(staffUser('Admin'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product, now()->addDay()->format('Y-m-d\TH:i')))
        ->assertSessionHasErrors('created_at');

    expect(Order::count())->toBe(0);
});

it('ignores a date posted by a currier manager', function () {
    $backdate = now()->subDays(3)->setTime(14, 30);

    $this->actingAs(staffUser('Currier manager'))
        ->post('/admin/orders/store', orderPayload($this->client, $this->product, $backdate->format('Y-m-d\TH:i')))
        ->assertRedirect();

    expect(Order::latest('id')->first()->created_at->diffInMinutes(now()))->toBeLessThan(2);
});
