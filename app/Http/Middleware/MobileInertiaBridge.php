<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the mobile management app consume the existing Inertia admin
 * controllers as a JSON API, so web and mobile share one implementation of
 * every admin operation (and one set of role checks).
 *
 * Must be registered AFTER HandleInertiaRequests in the group (that
 * middleware installs the version resolver this one reads).
 *
 * What it does:
 *  - Requires a Bearer token. The group runs StartSession (the controllers
 *    flash to the session), which would otherwise let a plain browser
 *    session cookie authenticate these state-changing routes without CSRF
 *    protection. Token-only access closes that hole.
 *  - Marks the request as an Inertia request (with the current asset
 *    version) so `Inertia::render()` responds with the page JSON
 *    ({component, props, url, version}) instead of HTML.
 *  - Converts the redirect responses the controllers return after mutations
 *    into JSON: {ok, redirect, flash} on success, 422 {ok, errors} when the
 *    controller redirected back with validation errors.
 */
class MobileInertiaBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            abort(401, 'Token authentication required.');
        }

        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', (string) Inertia::getVersion());

        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            return $this->redirectToJson($request, $response);
        }

        return $response;
    }

    private function redirectToJson(Request $request, RedirectResponse $response): Response
    {
        $session = $request->hasSession() ? $request->session() : null;

        $errors = [];
        if ($session && $session->has('errors')) {
            /** @var \Illuminate\Support\ViewErrorBag $bag */
            $bag = $session->get('errors');
            $errors = $bag->getBag('default')->toArray();
        }

        $payload = [
            'ok'       => empty($errors),
            'redirect' => $response->getTargetUrl(),
            'flash'    => [
                'success' => $session?->get('success'),
                'error'   => $session?->get('error'),
                'warning' => $session?->get('warning'),
            ],
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;

            return response()->json($payload, 422);
        }

        return response()->json($payload);
    }
}
