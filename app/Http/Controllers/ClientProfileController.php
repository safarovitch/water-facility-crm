<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Models\UserProfile;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $user->load(['userProfile', 'addresses', 'phones']);

        return Inertia::render('profile/EditClient')->with([
            'client' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            // Profile fields
            'type' => ['required', new EnumValue(ClientType::class)],
            'company_name' => ['nullable', 'string', 'max:255', 'required_if:type,' . ClientType::Company],
            'region' => ['nullable', 'string', 'max:255'],
            
            // Validating phones
            'phones' => ['nullable', 'array'],
            'phones.*.id' => ['nullable', 'integer'],
            'phones.*.label' => ['required', 'string', 'max:50'],
            'phones.*.phone' => ['required', 'string', 'max:30'],
            'phones.*.is_default' => ['nullable', 'boolean'],

            // Validating addresses
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.label' => ['required', 'string', 'max:50'],
            'addresses.*.address_line' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.region' => ['nullable', 'string', 'max:100'],
            'addresses.*.lat' => ['nullable', 'numeric'],
            'addresses.*.lng' => ['nullable', 'numeric'],
            'addresses.*.is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $user) {
            $user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);

            $user->userProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'type'         => $request->type,
                    'company_name' => $request->company_name,
                    'region'       => $request->region,
                    // Note: notes purposefully left out for separation of concern
                ]
            );

            // Sync phones: update existing, create new, delete removed
            $incomingPhoneIds = [];
            foreach ($request->input('phones', []) as $i => $phoneData) {
                if (!empty($phoneData['id'])) {
                    $existing = UserPhone::where('id', $phoneData['id'])->where('user_id', $user->id)->first();
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
                        'user_id'    => $user->id,
                        'label'      => $phoneData['label'] ?? 'Mobile',
                        'phone'      => $phoneData['phone'],
                        'is_default' => $phoneData['is_default'] ?? ($i === 0),
                    ]);
                    $incomingPhoneIds[] = $created->id;
                }
            }
            $user->phones()->whereNotIn('id', $incomingPhoneIds)->delete();

            // Sync addresses: update existing, create new, delete removed
            $incomingIds = [];
            foreach ($request->input('addresses', []) as $i => $addr) {
                if (!empty($addr['id'])) {
                    $existing = UserAddress::where('id', $addr['id'])->where('user_id', $user->id)->first();
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
                        'user_id'      => $user->id,
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
            $user->addresses()->whereNotIn('id', $incomingIds)->delete();
        });

        return back()->with('success', 'Profile updated successfully.');
    }
}
