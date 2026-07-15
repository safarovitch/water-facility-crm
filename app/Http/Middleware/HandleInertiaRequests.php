<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
  /**
   * The root template that's loaded on the first page visit.
   *
   * @see https://inertiajs.com/server-side-setup#root-template
   *
   * @var string
   */
  protected $rootView = 'app';

  /**
   * Determines the current asset version.
   *
   * @see https://inertiajs.com/asset-versioning
   */
  public function version(Request $request): ?string
  {
    return parent::version($request);
  }

  /**
   * Define the props that are shared by default.
   *
   * @see https://inertiajs.com/shared-data
   *
   * @return array<string, mixed>
   */
  public function share(Request $request): array
  {
    [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

    $user = $request->user();

    return [
      ...parent::share($request),
      'name' => config('app.name'),
      'quote' => ['message' => trim($message), 'author' => trim($author)],
      'auth' => [
        'user' => $user ? [
          ...$user->toArray(),
          'roles' => $user->getRoleNames(),
          'sip_extension' => $user->sip_extension,
          'sip_password' => $user->sip_password,
        ] : null,
        // Ability flags for the UI. Every flag mirrors a server-side route /
        // controller restriction — hiding an element here is never the only
        // guard.
        'can' => $user ? [
          'accessAdmin'           => $user->isStaff(),
          'viewAdminStats'        => $user->hasAdminAccess(),
          'manageClients'         => $user->isStaff(),
          'deleteClients'         => $user->hasAdminAccess(),
          'viewForecasts'         => $user->isStaff(),
          'assignCurriers'        => $user->hasAdminAccess() || $user->isCurrierManager(),
          'viewCurrierActivities' => $user->hasAdminAccess() || $user->isCurrierManager(),
          'manageOrders'          => $user->hasAdminAccess() || $user->isCurrierManager(),
          'deleteOrders'          => $user->hasAdminAccess(),
          'accessAccounting'      => $user->isStaff(),
          'manageAccounting'      => $user->hasAdminAccess(),
          'manageUsers'           => $user->hasAdminAccess(),
          'manageProducts'        => $user->hasAdminAccess(),
          'manageInventory'       => $user->hasAdminAccess(),
          'manageRawMaterials'    => $user->hasAdminAccess(),
          'manageSubscriptions'   => $user->hasAdminAccess(),
        ] : [],
      ],
      'asterisk' => [
        'host' => config('services.asterisk.host'),
        'port' => config('services.asterisk.wss_port'),
        'domain' => config('services.asterisk.domain'),
      ],
      'available_locales' => config('app.available_locales'),
      'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
      'flash' => [
        'success' => $request->session()->get('success'),
        'error'   => $request->session()->get('error'),
        'pending_overpayment_refund' => $request->session()->get('pending_overpayment_refund'),
      ],
      // Surface the result of the phone-OTP staging endpoints so the auth
      // Vue pages can pick up the Telegram deep-link without needing extra
      // round-trips. Populated by PhoneAuthController via back()->with(...).
      'otpFlow' => [
        'phone'        => $request->session()->get('phone'),
        'deep_link'    => $request->session()->get('deep_link'),
        'awaiting_otp' => (bool) $request->session()->get('awaiting_otp', false),
        'name'         => $request->session()->get('name'),
      ],
      'adminMode' => $request->session()->get('admin_mode', false) || $request->is('admin') || $request->is('admin/*'),
      'currency' => config('app.currency'),
      // Couriers without manager/admin rights only see their own workload,
      // so the badge must not leak the global pending-order count to them.
      'pending_orders_count' => match (true) {
        ! $user                  => 0,
        $user->isCourierOnly()   => \App\Models\Order::where('status', 'pending')->where('courier_id', $user->id)->count(),
        default                  => \App\Models\Order::where('status', 'pending')->count(),
      },
    ];
  }
}
