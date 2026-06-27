import { useEffect, useState } from 'react';

const JS_LOCALE: Record<string, string> = {
    ro: 'ro-RO',
    ru: 'ru-RU',
    en: 'en-GB',
};

/**
 * Ceas live (client-side). Returnează data + ora formatate pe limba curentă, actualizate la fiecare
 * secundă. Pornește GOL (server / înainte de hidratare) ca să nu apară mismatch de hidratare.
 */
export function useLiveClock(locale: string): { date: string; time: string } {
    const jsLocale = JS_LOCALE[locale] ?? 'ro-RO';
    const [now, setNow] = useState<Date | null>(null);

    useEffect(() => {
        // Inițializare client-only (pornim GOL pe server ca să nu apară mismatch de hidratare).
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setNow(new Date());
        const id = setInterval(() => setNow(new Date()), 1000);

        return () => clearInterval(id);
    }, []);

    if (now === null) {
        return { date: '', time: '' };
    }

    return {
        date: now.toLocaleDateString(jsLocale, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }),
        time: now.toLocaleTimeString(jsLocale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }),
    };
}
