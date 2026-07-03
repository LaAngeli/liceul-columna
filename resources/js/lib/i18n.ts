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

export type RouteSlugMap = Record<string, Partial<Record<'ru' | 'en', string>>>;

/** Harta de traducere a slug-urilor (RU/EN pe segment canonic RO) — vezi App\Support\RouteSlugs. */
export function useRouteSlugs(): RouteSlugMap {
    return usePage().props.routeSlugs ?? {};
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

/** Construiește harta inversă (locale → slug tradus → segment canonic RO) dintr-un RouteSlugMap. */
function reverseSlugMap(slugMap: RouteSlugMap): Record<string, Record<string, string>> {
    const reverse: Record<string, Record<string, string>> = { ru: {}, en: {} };

    for (const [roSegment, translations] of Object.entries(slugMap)) {
        for (const locale of ['ru', 'en'] as const) {
            const translated = translations[locale];

            if (translated) {
                reverse[locale][translated] = roSegment;
            }
        }
    }

    return reverse;
}

/**
 * Prefixează o cale internă cu limba dată (ro = fără prefix, la root) și traduce slug-ul
 * segment cu segment via `slugMap` (App\Support\RouteSlugs, partajat prin Inertia).
 *
 * Simetric: `path` poate fi FIE un path canonic RO scris literal în cod (href="/scoala-primara"
 * din JSX — fără prefix, deci „limba curentă" detectată e RO, iar traducerea RO→RO e identitate),
 * FIE URL-ul curent din browser, deja localizat (ex. /ru/nachalnaya-shkola, la comutarea de
 * limbă) — prefixul e detectat, segmentele sunt „reverse-mapate" spre canonicul RO, apoi
 * „forward-mapate" spre limba țintă. Un segment fără intrare în hartă rămâne neschimbat
 * (fallback sigur — pagini încă netraduse).
 */
export function localizePath(path: string, locale: string, slugMap: RouteSlugMap = {}): string {
    const [pathname, rest] = splitPathnameFromRest(path);
    const match = pathname.match(/^\/(ru|en)(?=\/|$)/);
    const currentLocale = match ? match[1] : 'ro';
    const stripped = pathname.replace(/^\/(ru|en)(?=\/|$)/, '') || '/';

    if (stripped === '/') {
        return (locale === 'ro' ? '/' : `/${locale}`) + rest;
    }

    const reverse = reverseSlugMap(slugMap);
    const canonicalSegments = stripped
        .slice(1)
        .split('/')
        .map((segment) => (currentLocale === 'ro' ? segment : (reverse[currentLocale]?.[segment] ?? segment)));

    const targetSegments = canonicalSegments.map((segment) => (locale === 'ro' ? segment : (slugMap[segment]?.[locale as 'ru' | 'en'] ?? segment)));

    const targetPath = `/${targetSegments.join('/')}`;

    return (locale === 'ro' ? targetPath : `/${locale}${targetPath}`) + rest;
}

/** Desparte un path de query string/hash (translatăm doar segmentele de pathname). */
function splitPathnameFromRest(path: string): [pathname: string, rest: string] {
    const idx = path.search(/[?#]/);

    return idx === -1 ? [path, ''] : [path.slice(0, idx), path.slice(idx)];
}
