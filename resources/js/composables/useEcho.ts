import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo | null = null;

/**
 * Initializes and returns a Laravel Echo instance.
 */
export const useEcho = () => {
    if (typeof window === 'undefined') return null;

    if (!echo) {
        try {
            // @ts-ignore
            window.Pusher = Pusher;

            // Site-wide security status
            const isSiteSecure = window.location.protocol === 'https:';

            // Environment variables
            const host = import.meta.env.VITE_REVERB_HOST;
            const port = import.meta.env.VITE_REVERB_PORT;
            const envScheme = import.meta.env.VITE_REVERB_SCHEME ?? (isSiteSecure ? 'https' : 'http');
            
            // SECURITY RULE: If the site is served over HTTPS, we MUST use wss://.
            // Using ws:// on an https:// site will cause "Mixed Content" errors and be blocked by the browser.
            const resolvedScheme = isSiteSecure ? 'https' : envScheme;
            const isSecure = resolvedScheme === 'https';

            // HOST RESOLUTION: Fallback to current domain if placeholder or empty.
            const resolvedHost = (!host || host === 'localhost' || host === 'reverb') 
                ? window.location.hostname 
                : host;

            // PORT RESOLUTION: 
            // - If wss is used, we default to 443 (standard secure port).
            // - If port 80 is given with wss, we ignore it and use 443.
            let resolvedPort = port ? Number(port) : (isSecure ? 443 : 80);
            if (isSecure && resolvedPort === 80) {
                resolvedPort = 443;
            }

            const config = {
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY || 'missing-key',
                wsHost: resolvedHost,
                wsPort: resolvedPort,
                wssPort: resolvedPort,
                forceTLS: isSecure,
                enabledTransports: ['ws', 'wss'],
            };

            // Always log in development or if connectivity fails
            console.log('[Echo] Connecting to:', `${isSecure ? 'wss' : 'ws'}://${config.wsHost}:${config.wsPort}`);

            echo = new Echo(config);
        } catch (e) {
            console.error('[Echo] Initialization failed:', e);
            return null;
        }
    }

    return echo;
};
