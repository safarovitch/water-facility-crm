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

    if ($request->user()) {
      Log::info('HandleInertiaRequests User:', [
        'id' => $request->user()->id,
        'email' => $request->user()->email,
        'sip' => $request->user()->sip_extension,
      ]);
    }

    return [
      ...parent::share($request),
      'name' => config('app.name'),
      'quote' => ['message' => trim($message), 'author' => trim($author)],
      'auth' => [
        'user' => $request->user() ? [
          ...$request->user()->toArray(),
          'sip_extension' => $request->user()->sip_extension,
          'sip_password' => $request->user()->sip_password,
        ] : null,
      ],
      'asterisk' => [
        'host' => config('services.asterisk.host'),
        'port' => config('services.asterisk.wss_port'),
        'domain' => config('services.asterisk.domain'),
      ],
      'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
    ];
  }
}
