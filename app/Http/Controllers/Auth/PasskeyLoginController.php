<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;
use Spatie\LaravelPasskeys\Support\Config;

class PasskeyLoginController extends Controller
{
    /**
     * Generate authentication options and store them in the session
     * using put() instead of flash() so they survive the AJAX round-trip.
     */
    public function authenticationOptions(): JsonResponse
    {
        $action = Config::getAction(
            'generate_passkey_authentication_options',
            GeneratePasskeyAuthenticationOptionsAction::class
        );

        $options = $action->execute();

        // Re-store with put() because the action uses flash() which gets
        // consumed after the current response, before the authenticate POST.
        Session::put('passkey-authentication-options', $options);

        return response()->json(json_decode($options, true));
    }

    /**
     * Authenticate using the passkey credential from the browser.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate([
            'start_authentication_response' => ['required', 'json'],
        ]);

        $findPasskey = Config::getAction(
            'find_passkey',
            FindPasskeyToAuthenticateAction::class,
        );

        $passkeyOptions = Session::pull('passkey-authentication-options');

        if (! $passkeyOptions) {
            return back()->withErrors([
                'passkey' => 'Authentication session expired. Please try again.',
            ]);
        }

        $passkey = $findPasskey->execute(
            $request->input('start_authentication_response'),
            $passkeyOptions,
        );

        if (! $passkey || ! $passkey->authenticatable) {
            return back()->withErrors([
                'passkey' => 'Passkey authentication failed. The passkey may not be registered.',
            ]);
        }

        auth()->login($passkey->authenticatable, $request->boolean('remember'));
        Session::regenerate();

        event(new PasskeyUsedToAuthenticateEvent($passkey, $request));

        $redirectUrl = Session::has('passkeys.redirect')
            ? Session::pull('passkeys.redirect')
            : Config::getRedirectAfterLogin();

        return redirect($redirectUrl);
    }
}
