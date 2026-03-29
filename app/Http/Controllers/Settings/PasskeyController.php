<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/Passkeys', [
            'passkeys' => $request->user()->passkeys()
                ->select(['id', 'authenticatable_id', 'name', 'created_at', 'last_used_at'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function registerOptions(
        Request $request,
        GeneratePasskeyRegisterOptionsAction $generatePasskeyRegisterOptionsAction,
    ): JsonResponse {
        $optionsJson = $generatePasskeyRegisterOptionsAction->execute($request->user(), asJson: true);

        return response()->json([
            'options' => json_decode($optionsJson, true),
        ]);
    }

    public function store(
        Request $request,
        StorePasskeyAction $storePasskeyAction,
    ) {
        $request->validate([
            'name' => 'required|string|max:255',
            'passkey' => 'required|string',
            'passkey_options' => 'required|string',
        ]);

        $storePasskeyAction->execute(
            authenticatable: $request->user(),
            passkeyJson: $request->input('passkey'),
            passkeyOptionsJson: $request->input('passkey_options'),
            hostName: $request->getHost(),
            additionalProperties: ['name' => $request->input('name')]
        );

        return back()->with('status', 'Passkey added successfully.');
    }

    public function destroy(Passkey $passkey)
    {
        if ((int) $passkey->authenticatable_id !== (int) auth()->id()) {
            abort(403);
        }

        $passkey->delete();

        return back()->with('status', 'Passkey removed.');
    }
}
