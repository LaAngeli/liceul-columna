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
 * Returnează cheia plurală corespunzătoare (`baseKey_one` la count===1, altfel `baseKey_other`).
 * Pentru toate cele 3 limbi suportate folosim regula simplă one/other — suficient pentru contoarele
 * UI uzuale. Pentru rusă regula reală e one/few/many; aproximăm la one/other (1 vs. rest), iar dacă
 * apar contoare unde diferența 2-4 contează vizibil, se poate adăuga `_few` ulterior fără modificări
 * la cod (helper-ul se va extinde cu detecția 2-4 doar pentru locale='ru').
 *
 * Folosire: `t(pluralKey('cabinet.cockpit_unread_messages', count))`.
 */
export function pluralKey(baseKey: string, count: number): string {
    return `${baseKey}_${count === 1 ? 'one' : 'other'}`;
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
