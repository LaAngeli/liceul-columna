import { useCallback } from 'react';

export type GetInitialsFn = (fullName: string) => string;

/**
 * Inițialele afișate în avatare — OGLINDA exactă a `App\Support\Initials` din backend, ca același
 * om să apară la fel în panou și în cabinet:
 *  1. marcajele în paranteze drepte („[DEMO]") nu fac parte din nume și se elimină;
 *  2. se păstrează doar cuvintele care conțin măcar o literă;
 *  3. se iau primele litere ale PRIMELOR DOUĂ cuvinte rămase (convenția „Nume Prenume").
 *
 * Varianta veche lua primul + ULTIMUL cuvânt, deci „[DEMO] Ursu Valentin" dădea „[V", iar
 * „Bujor-Cobili Carolina Maria" dădea „BM" în loc de „BC".
 */
export function getInitials(fullName: string): string {
    const words = fullName
        .replace(/\[[^\]]*\]/gu, ' ')
        .trim()
        .split(/\s+/u)
        .filter((word) => /\p{L}/u.test(word));

    return words
        .slice(0, 2)
        .map((word) => (word.match(/\p{L}/u)?.[0] ?? '').toUpperCase())
        .join('');
}

export function useInitials(): GetInitialsFn {
    return useCallback(getInitials, []);
}
