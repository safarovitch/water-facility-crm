<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only admins and managers may use the CRM web app. Any other signed-in
 * session (e.g. a client still holding a remember-me cookie) is ended.
 */
class EnsureCanLogIn
{
  public function handle(Request $request, Closure $next): Response
  {
    $user = $request->user();

    if ($user && !$user->canLogIn()) {
      Auth::guard('web')->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect()->route('login');
    }

    return $next($request);
  }
}
