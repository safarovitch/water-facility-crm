<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ClientController extends Controller
{
  public function index(): Response
  {
    $pagination = request()->has('pagination')
      ? request()->input('pagination')
      : ['limit' => 50, 'page' => 1];

    $clients = User::role('Client')
      ->with(['userProfile', 'phones'])
      ->when(
        request('search'),
        fn($q, $search) =>
        $q->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%")
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
      )
      ->paginate($pagination['limit'], ['*'], 'page', $pagination['page']);

    return Inertia::render('clients/Index')->with([
      'clients' => $clients,
    ]);
  }

  /**
   * Display the specified client (Admin Show Page).
   */
  public function show(Request $request, User $client)
  {
    // Load relation
    $client->load(['userProfile', 'addresses', 'phones']);

    // Fetch phone numbers for activity log lookup
    $phoneNumbers = $client->phones->pluck('phone')->toArray();

    // Fetch call history via Spatie activity log based on phone properties
    $calls = collect([]);
    if (!empty($phoneNumbers)) {
      $callsQuery = Activity::where('log_name', 'call')
        ->where(function ($query) use ($phoneNumbers) {
          foreach ($phoneNumbers as $phone) {
            $query->orWhereJsonContains('properties->phone', $phone);
          }
        });

      // Limit to recent 50 for the show timeline
      $calls = $callsQuery->latest()->limit(50)->get();
    }

    return Inertia::render('clients/Show', [
      'client' => $client,
      'calls' => $calls,
      // 'orders' => $client->orders()->latest()->get() // Future relationship stub
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('clients/Create');
  }

  public function store(StoreClientRequest $request)
  {
    $user = User::where('email', $request->email)->first();

    if ($user && $user->isClient()) {
      return back()->withErrors(['email' => 'This email is already registered as a client.'])->withInput();
    }

    DB::transaction(function () use ($request, &$user) {
      if (!$user) {
        $user = User::create([
          'name'     => $request->name,
          'email'    => $request->email,
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
          'credit_limit' => $request->credit_limit ?? 0,
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

    return redirect()->route('clients.index')
      ->with('success', 'Client created successfully.');
  }

  public function edit(User $client): Response
  {
    $client->load(['userProfile', 'addresses', 'phones']);

    return Inertia::render('clients/Edit')->with([
      'client' => $client,
    ]);
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
          'credit_limit' => $request->credit_limit ?? 0,
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

    return redirect()->route('clients.index')
      ->with('success', 'Client updated successfully.');
  }

  public function destroy(User $client)
  {
    // Prevent deletion if the client has active orders
    if ($client->orders()->whereNotIn('status', ['delivered', 'cancelled'])->exists()) {
      return back()->with('error', 'Cannot delete a client with active orders.');
    }

    $client->delete();

    return redirect()->route('clients.index')
      ->with('success', 'Client deleted successfully.');
  }
}
