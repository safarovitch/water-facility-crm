/**
 * Admin-specific route helpers.
 *
 * IMPORTANT: This file lives in resources/js/lib/ — NOT in resources/js/routes/
 * because the Wayfinder Vite plugin regenerates and wipes the entire routes/
 * directory on every build. This location is safe from that.
 */

const qs = (options?: { query?: Record<string, string> }) =>
    options?.query ? '?' + new URLSearchParams(options.query).toString() : ''

export const adminDashboard = (options?: { query?: Record<string, string> }) => ({
    url: '/admin' + qs(options),
    method: 'get' as const,
})
adminDashboard.url = (options?: { query?: Record<string, string> }) => '/admin' + qs(options)

export const adminSwitchMode = (options?: { query?: Record<string, string> }) => ({
    url: '/admin/switch-mode' + qs(options),
    method: 'post' as const,
})
adminSwitchMode.url = (options?: { query?: Record<string, string> }) => '/admin/switch-mode' + qs(options)
