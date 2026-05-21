<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a legacy email+password login, the user lands here without a verified
 * phone. We hold them on /account/setup-phone until they complete the
 * Telegram OTP loop, except for a small allowlist of routes (logout, the
 * setup screen itself, asset URLs).
 */
class EnsurePhoneVerified
{
  /**
   * Route name prefixes that should NEVER be gated.
   */
  private const ALLOWED_PREFIXES = [
    'account.setup-phone',
    'logout',
    'login',
    'register',
    'password.',
    'verification.',
    'horizon.',      // Laravel Horizon
    'telegraph.',    // Bot webhook
  ];

  /**
   * Path prefixes that should NEVER be gated (webhooks, assets, JSON APIs the
   * setup page itself depends on).
   */
  private const ALLOWED_PATH_PREFIXES = [
    'telegraph/',
    'storage/',
    'build/',
    'up',
  ];

  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();
    if (!$user) {
      return $next($request);
    }

    if ($user->phone_verified_at !== null) {
      return $next($request);
    }

    if ($this->isAllowed($request)) {
      return $next($request);
    }

    // Inertia partial reloads need a 409 with X-Inertia-Location so the
    // SPA performs a hard nav to the setup page. Plain GETs just redirect.
    return redirect()->route('account.setup-phone');
  }

  private function isAllowed(Request $request): bool
  {
    $name = optional($request->route())->getName() ?? '';
    foreach (self::ALLOWED_PREFIXES as $prefix) {
      if ($name === $prefix || Str::startsWith($name, $prefix)) {
        return true;
      }
    }

    $path = ltrim($request->path(), '/');
    foreach (self::ALLOWED_PATH_PREFIXES as $prefix) {
      if (Str::startsWith($path, $prefix)) {
        return true;
      }
    }

    return false;
  }
}
