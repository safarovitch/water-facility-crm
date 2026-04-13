import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo | null = null;

/**
 * Initializes and returns a Laravel Echo instance.
 * Returns null if initialization fails instead of crashing the app.
 */
export const useEcho = () => {
    if (typeof window === 'undefined') return null;

    if (!echo) {
        try {
            // @ts-ignore
            window.Pusher = Pusher;

            const config = {
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY || 'missing-key',
                wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
                wsPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 80,
                wssPort: import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : 443,
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            };

            echo = new Echo(config);
        } catch (e) {
            console.error('[Echo] Initialization failed:', e);
            return null;
        }
    }

    return echo;
};
