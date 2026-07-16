import { ref } from 'vue';
import { ru } from '@/i18n/ru';

export type AppLocale = 'ru' | 'en';

export const APP_LOCALES: { code: AppLocale; label: string; short: string }[] = [
    { code: 'ru', label: 'Русский', short: 'RU' },
    { code: 'en', label: 'English', short: 'EN' },
];

const STORAGE_KEY = 'fann.locale';
const LEGACY_STORAGE_KEY = 'fann.landing.locale';
const COOKIE_NAME = 'locale';

function isAppLocale(value: unknown): value is AppLocale {
    return value === 'ru' || value === 'en';
}

/**
 * Resolve the initial locale:
 *   1. explicit user choice (localStorage, incl. the legacy landing key)
 *   2. Russian — the default for everyone until they switch explicitly
 */
function detectInitialLocale(): AppLocale {
    if (typeof window === 'undefined') return 'ru';
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (isAppLocale(stored)) return stored;
        const legacy = window.localStorage.getItem(LEGACY_STORAGE_KEY);
        if (isAppLocale(legacy)) return legacy;
    } catch {
        /* localStorage unavailable (private mode, SSR) */
    }
    return 'ru';
}

function persistLocale(next: AppLocale) {
    try {
        window.localStorage.setItem(STORAGE_KEY, next);
        window.localStorage.setItem(LEGACY_STORAGE_KEY, next);
    } catch {
        /* ignore */
    }
    if (typeof document !== 'undefined') {
        document.documentElement.lang = next;
        // Mirror the choice into a plain cookie so Laravel can localize
        // server-rendered output (validation errors, flash messages).
        document.cookie = `${COOKIE_NAME}=${next}; path=/; max-age=31536000; samesite=lax`;
    }
}

// Singleton reactive state shared across every component and page.
const locale = ref<AppLocale>(detectInitialLocale());

if (typeof document !== 'undefined') {
    // Make sure <html lang> and the backend cookie reflect the detected
    // locale even before the user touches the switcher.
    persistLocale(locale.value);
}

function interpolate(text: string, params?: Record<string, string | number>): string {
    if (!params) return text;
    return text.replace(/\{(\w+)\}/g, (match, key: string) => (key in params ? String(params[key]) : match));
}

/**
 * App-wide translator. Keys are the original English strings; the Russian
 * dictionary lives in resources/js/i18n/ru.ts. Unknown keys fall back to
 * the English text itself, so nothing ever renders blank.
 *
 *   t('Orders')                        → «Заказы»
 *   t('Hello, {name}', { name: 'A' }) → «Здравствуйте, A»
 */
export function useI18n() {
    const setLocale = (next: AppLocale) => {
        locale.value = next;
        persistLocale(next);
    };

    const t = (text: string, params?: Record<string, string | number>): string => {
        const translated = locale.value === 'ru' ? (ru[text] ?? text) : text;
        return interpolate(translated, params);
    };

    return { locale, setLocale, t, locales: APP_LOCALES };
}
