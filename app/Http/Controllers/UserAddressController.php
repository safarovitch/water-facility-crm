<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
  public function store(Request $request, User $client): RedirectResponse
  {
    $validated = $request->validate([
      'label'        => 'required|string|max:100',
      'address_line' => 'required|string|max:500',
      'city'         => 'nullable|string|max:100',
      'region'       => 'nullable|string|max:100',
      'lat'          => 'nullable|numeric|between:-90,90',
      'lng'          => 'nullable|numeric|between:-180,180',
      'is_default'   => 'boolean',
    ]);

    if (!empty($validated['is_default'])) {
      // Un-default others first
      $client->addresses()->update(['is_default' => false]);
    }

    $client->addresses()->create($validated);

    return back()->with('success', 'Address added.');
  }

  public function update(Request $request, User $client, UserAddress $address): RedirectResponse
  {
    abort_unless($address->user_id === $client->id, 403);

    $validated = $request->validate([
      'label'        => 'required|string|max:100',
      'address_line' => 'required|string|max:500',
      'city'         => 'nullable|string|max:100',
      'region'       => 'nullable|string|max:100',
      'lat'          => 'nullable|numeric|between:-90,90',
      'lng'          => 'nullable|numeric|between:-180,180',
      'is_default'   => 'boolean',
    ]);

    if (!empty($validated['is_default'])) {
      $client->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
    }

    $address->update($validated);

    return back()->with('success', 'Address updated.');
  }

  public function destroy(User $client, UserAddress $address): RedirectResponse
  {
    abort_unless($address->user_id === $client->id, 403);
    $address->delete();

    // If deleted was default, promote the first remaining one
    if ($address->is_default) {
      $client->addresses()->first()?->update(['is_default' => true]);
    }

    return back()->with('success', 'Address removed.');
  }

  public function setDefault(User $client, UserAddress $address): RedirectResponse
  {
    abort_unless($address->user_id === $client->id, 403);

    $client->addresses()->update(['is_default' => false]);
    $address->update(['is_default' => true]);

    return back()->with('success', 'Default address updated.');
  }
}
