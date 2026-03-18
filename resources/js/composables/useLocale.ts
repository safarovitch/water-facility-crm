import { usePage } from '@inertiajs/vue3';

type TranslatableValue = string | Record<string, string> | null | undefined;

/**
 * Returns a helper that resolves a multilang field to the best available string.
 * Handles three forms:
 *   - already a plain string  → returned as-is
 *   - a JSON-encoded string   → parsed, then locale-resolved
 *   - a plain object          → locale-resolved directly
 */
export function useLocale() {
    const availableLocales = (usePage().props.available_locales ?? ['en']) as string[];

    const t = (value: TranslatableValue, fallback = '—'): string => {
        if (!value) return fallback;

        let resolved: Record<string, string>;

        if (typeof value === 'string') {
            // Could be a raw JSON string from DB::table() queries
            if (value.trimStart().startsWith('{')) {
                try {
                    resolved = JSON.parse(value) as Record<string, string>;
                } catch {
                    return value; // Malformed JSON → display as plain text
                }
            } else {
                return value; // Ordinary plain-text string
            }
        } else {
            resolved = value;
        }

        // Pick the best locale
        for (const locale of availableLocales) {
            if (resolved[locale]) return resolved[locale];
        }

        return Object.values(resolved).find(Boolean) ?? fallback;
    };

    return { t };
}
