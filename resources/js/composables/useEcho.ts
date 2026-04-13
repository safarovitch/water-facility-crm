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

            const host = import.meta.env.VITE_REVERB_HOST;
            const port = import.meta.env.VITE_REVERB_PORT;
            const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
            
            // If VITE_REVERB_HOST is missing or "localhost" but we are on a production domain,
            // we should probably fallback to the current hostname for better reliability.
            const resolvedHost = (!host || host === 'localhost' || host === 'reverb') 
                ? window.location.hostname 
                : host;

            const isSecure = scheme === 'https';

            const config = {
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY || 'missing-key',
                wsHost: resolvedHost,
                wsPort: port ? Number(port) : (isSecure ? 443 : 80),
                wssPort: port ? Number(port) : 443,
                forceTLS: isSecure,
                enabledTransports: ['ws', 'wss'],
            };

            if (import.meta.env.DEV) {
                console.log('[Echo] Connecting to:', `${isSecure ? 'wss' : 'ws'}://${config.wsHost}:${isSecure ? config.wssPort : config.wsPort}`);
            }

            echo = new Echo(config);
        } catch (e) {
            console.error('[Echo] Initialization failed:', e);
            return null;
        }
    }

    return echo;
};
