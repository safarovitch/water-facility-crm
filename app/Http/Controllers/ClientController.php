<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsQueries;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Models\UserProfile;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\OrderStatus;
use Spatie\Activitylog\Models\Activity;

class ClientController extends Controller
{
  use SortsQueries;

  public function index(): Response
  {
    $query = User::role('Client')
      ->with(['userProfile', 'phones'])
      // Nested where keeps the name/email/phone OR group from leaking past the
      // Client-role scope (an ungrouped orWhere would return non-clients).
      ->when(
        request('search'),
        fn($q, $search) =>
        $q->where(function ($sub) use ($search) {
          $sub->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhereHas('phones', fn($p) => $p->where('phone', 'like', "%{$search}%"));
        })
      )
      ->when(
        request('type'),
        fn($q, $type) =>
        $q->whereHas('userProfile', fn($p) => $p->where('type', $type))
      )
      ->when(
        request('status'),
        fn($q, $status) =>
        $q->where('status', $status)
      );

    // Whitelisted sorting. "Type" sorts via a correlated subquery on the
    // user_profiles table so we avoid a join (and its column-ambiguity with
    // the Client-role scope).
    $this->applySort($query, [
      'name'         => 'name',
      'status'       => 'status',
      'user_profile' => fn($q, $dir) => $q->orderBy(
        UserProfile::select('type')->whereColumn('user_profiles.user_id', 'users.id'),
        $dir
      ),
    ]);

    $clients = $query
      ->paginate(request()->integer('per_page', 50))
      ->withQueryString();

    return Inertia::render('clients/Index')->with([
      'clients' => $clients,
    ]);
  }

  /**
   * Display the specified client (Admin Show Page).
   * Redirects to the unified users.show profile.
   */
  public function show(Request $request, User $client)
  {
      return redirect()->route('admin.users.show', $client);
  }

  /**
   * Fetch paginated orders for a client (JSON for infinite scroll).
   */
  public function orders(User $client)
  {
    return $client->orders()
      ->with(['items.product'])
      ->latest()
      ->paginate(15);
  }

  public function create(): Response
  {
    return Inertia::render('clients/Create');
  }

  public function store(StoreClientRequest $request)
  {
    $user = $request->email ? User::where('email', $request->email)->first() : null;

    if ($user && $user->isClient()) {
      return back()->withErrors(['email' => 'This email is already registered as a client.'])->withInput();
    }

    DB::transaction(function () use ($request, &$user) {
      if (!$user) {
        $user = User::create([
          'name'     => $request->name,
          'email'    => $request->email ?: null,
          'password' => Hash::make(Str::random(16)),
          'status'   => 'active',
        ]);
      } else {
        // Optionally update existing user info if it was changed in the form
        $user->update([
          'name'  => $request->name,
        ]);
      }

      $user->assignRole('Client');

      UserProfile::updateOrCreate(
        ['user_id' => $user->id],
        [
          'type'         => $request->type,
          'company_name' => $request->company_name,
          'region'       => $request->region,
          'notes'        => $request->notes,
        ]
      );

      // Create phone numbers
      foreach ($request->input('phones', []) as $i => $phoneData) {
        UserPhone::create([
          'user_id'    => $user->id,
          'label'      => $phoneData['label'] ?? 'Mobile',
          'phone'      => $phoneData['phone'],
          'is_default' => $phoneData['is_default'] ?? ($i === 0),
        ]);
      }

      // Create initial addresses if provided
      foreach ($request->input('addresses', []) as $i => $addr) {
        UserAddress::create([
          'user_id'      => $user->id,
          'label'        => $addr['label']        ?? 'Main',
          'address_line' => $addr['address_line'] ?? '',
          'city'         => $addr['city']         ?? null,
          'region'       => $addr['region']       ?? null,
          'lat'          => $addr['lat']          ?? null,
          'lng'          => $addr['lng']          ?? null,
          'is_default'   => $i === 0,
        ]);
      }
    });

    return redirect()->route('admin.clients.index')
      ->with('success', 'Client created successfully.');
  }

  public function edit(User $client): Response
  {
    $client->load(['userProfile', 'addresses', 'phones']);

    // Candidates for profile transfer: any user except the current client.
    // Most useful: recently registered users who don't yet have a Client role
    // and don't already own this client's data. We let the admin search by
    // name/email/phone from the UI, so we just send a small recent slice here.
    $transferCandidates = User::where('id', '!=', $client->id)
      ->with('phones:id,user_id,phone,is_default')
      ->latest('id')
      ->limit(50)
      ->get(['id', 'name', 'email']);

    return Inertia::render('clients/Edit')->with([
      'client' => $client,
      'transferCandidates' => $transferCandidates,
    ]);
  }

  /**
   * Transfer this client's profile (UserProfile, addresses, phones, orders,
   * wallet) to another user. Used when the same person registers a second
   * account and we want their CRM history to follow the new auth row.
   *
   * The source user keeps existence but loses Client role and all linked
   * client data. The target user receives the Client role and the data.
   */
  public function transferProfile(Request $request, User $client)
  {
    $request->validate([
      'target_user_id' => ['required', 'integer', 'exists:users,id', 'different:'.$client->id],
    ]);

    $target = User::findOrFail($request->target_user_id);

    if ($target->id === $client->id) {
      return back()->withErrors(['target_user_id' => 'Source and target must be different users.']);
    }

    DB::transaction(function () use ($client, $target) {
      // Remove any data already attached to the target so the source's data
      // (the canonical history) wins. Target is typically a fresh duplicate
      // account with little or no data, so this is safe.
      $target->userProfile()->delete();
      $target->addresses()->delete();
      $target->phones()->delete();
      Wallet::where('user_id', $target->id)->delete();

      // Move data from source to target.
      UserProfile::where('user_id', $client->id)->update(['user_id' => $target->id]);
      UserAddress::where('user_id', $client->id)->update(['user_id' => $target->id]);
      UserPhone::where('user_id', $client->id)->update(['user_id' => $target->id]);
      Wallet::where('user_id', $client->id)->update(['user_id' => $target->id]);
      \App\Models\Order::where('user_id', $client->id)->update(['user_id' => $target->id]);

      // Swap the Client role.
      if ($client->hasRole('Client')) {
        $client->removeRole('Client');
      }
      if (!$target->hasRole('Client')) {
        $target->assignRole('Client');
      }
    });

    return redirect()
      ->route('admin.clients.edit', $target->id)
      ->with('success', "Client profile transferred to {$target->name}.");
  }

  public function update(UpdateClientRequest $request, User $client)
  {
    DB::transaction(function () use ($request, $client) {
      $client->update([
        'name'  => $request->name,
        'email' => $request->email,
      ]);

      $client->userProfile()->updateOrCreate(
        ['user_id' => $client->id],
        [
          'type'         => $request->type,
          'company_name' => $request->company_name,
          'region'       => $request->region,
          'notes'        => $request->notes,
        ]
      );

      // Sync phones: update existing, create new, delete removed
      $incomingPhoneIds = [];
      foreach ($request->input('phones', []) as $i => $phoneData) {
        if (!empty($phoneData['id'])) {
          $existing = UserPhone::where('id', $phoneData['id'])->where('user_id', $client->id)->first();
          if ($existing) {
            $existing->update([
              'label'      => $phoneData['label'] ?? 'Mobile',
              'phone'      => $phoneData['phone'],
              'is_default' => $phoneData['is_default'] ?? ($i === 0),
            ]);
            $incomingPhoneIds[] = $existing->id;
          }
        } else {
          $created = UserPhone::create([
            'user_id'    => $client->id,
            'label'      => $phoneData['label'] ?? 'Mobile',
            'phone'      => $phoneData['phone'],
            'is_default' => $phoneData['is_default'] ?? ($i === 0),
          ]);
          $incomingPhoneIds[] = $created->id;
        }
      }
      $client->phones()->whereNotIn('id', $incomingPhoneIds)->delete();

      // Sync addresses: update existing, create new, delete removed
      $incomingIds = [];
      foreach ($request->input('addresses', []) as $i => $addr) {
        if (!empty($addr['id'])) {
          $existing = UserAddress::where('id', $addr['id'])->where('user_id', $client->id)->first();
          if ($existing) {
            $existing->update([
              'label'        => $addr['label']        ?? 'Main',
              'address_line' => $addr['address_line'] ?? '',
              'city'         => $addr['city']         ?? null,
              'region'       => $addr['region']       ?? null,
              'lat'          => $addr['lat']          ?? null,
              'lng'          => $addr['lng']          ?? null,
              'is_default'   => $addr['is_default']  ?? ($i === 0),
            ]);
            $incomingIds[] = $existing->id;
          }
        } else {
          $created = UserAddress::create([
            'user_id'      => $client->id,
            'label'        => $addr['label']        ?? 'Main',
            'address_line' => $addr['address_line'] ?? '',
            'city'         => $addr['city']         ?? null,
            'region'       => $addr['region']       ?? null,
            'lat'          => $addr['lat']          ?? null,
            'lng'          => $addr['lng']          ?? null,
            'is_default'   => $addr['is_default']  ?? ($i === 0),
          ]);
          $incomingIds[] = $created->id;
        }
      }
      // Delete addresses that were removed from the form
      $client->addresses()->whereNotIn('id', $incomingIds)->delete();
    });

    return redirect()->route('admin.clients.index')
      ->with('success', 'Client updated successfully.');
  }

  public function destroy(User $client)
  {
    // Prevent deletion if the client has active orders
    if ($client->orders()->whereNotIn('status', ['delivered', 'cancelled'])->exists()) {
      return back()->with('error', 'Cannot delete a client with active orders.');
    }

    $client->delete();

    return redirect()->route('admin.clients.index')
      ->with('success', 'Client deleted successfully.');
  }
}
