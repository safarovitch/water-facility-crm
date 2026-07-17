<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts a page view for every successful GET page load on the web app —
 * both classic loads and Inertia SPA navigations (which arrive as GET XHRs
 * with the X-Inertia header). Counters are aggregated per path per day in
 * the page_views table and surfaced on the admin dashboard.
 */
class TrackPageView
{
    /** Paths that are infrastructure, not pages. */
    private const EXCLUDED_PREFIXES = [
        'build/',
        'storage/',
        'passkeys/',
        'broadcasting/',
        '_debugbar/',
        'up',
        'favicon',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isPageView($request, $response)) {
            // Normalize numeric IDs so /admin/orders/1 and /admin/orders/2
            // aggregate as one URL on the dashboard.
            $path = '/' . trim($request->path(), '/');
            $path = preg_replace('#/\d+(?=/|$)#', '/{id}', $path) ?? $path;

            try {
                PageView::record($path === '/' ? '/' : rtrim($path, '/'));
            } catch (\Throwable $e) {
                // Analytics must never break page delivery.
                Log::warning('TrackPageView failed', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }

    private function isPageView(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        // Only count documents/Inertia pages, not JSON polling endpoints.
        if (! $request->header('X-Inertia') && ! str_contains((string) $request->header('Accept'), 'text/html')) {
            return false;
        }

        $path = trim($request->path(), '/');
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
