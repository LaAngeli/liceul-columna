import { usePage } from '@inertiajs/react';

type Messages = Record<string, unknown>;

/** Rezolvă o cheie cu puncte („nav.about") dintr-un set de traduceri. */
export function resolveKey(messages: Messages | undefined, key: string): string | undefined {
    if (!messages) {
        return undefined;
    }

    const value = key.split('.').reduce<unknown>((acc, part) => {
        if (acc && typeof acc === 'object') {
            return (acc as Record<string, unknown>)[part];
        }
        return undefined;
    }, messages);

    return typeof value === 'string' ? value : undefined;
}

export function useLocale(): string {
    return usePage().props.locale;
}

/**
 * Returnează `t(key, fallback?)` pentru limba curentă.
 * Cheile vin din `lang/{locale}/site.php`, partajate prin Inertia.
 */
export function useTranslations(): (key: string, fallback?: string) => string {
    const { messages, locale } = usePage().props;
    const current = messages?.[locale];

    return (key, fallback) => resolveKey(current, key) ?? fallback ?? key;
}

/**
 * Prefixează o cale internă cu limba dată (ro = fără prefix, la root).
 * Elimină întâi un eventual prefix existent (/ru, /en).
 */
export function localizePath(path: string, locale: string): string {
    const stripped = path.replace(/^\/(ru|en)(?=\/|$)/, '') || '/';

    if (locale === 'ro') {
        return stripped;
    }

    return stripped === '/' ? `/${locale}` : `/${locale}${stripped}`;
}
