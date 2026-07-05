import { useEffect, useState } from 'react';

const JS_LOCALE: Record<string, string> = {
    ro: 'ro-RO',
    ru: 'ru-RU',
    en: 'en-GB',
};

interface LiveClock {
    /** Ziua săptămânii, forma scurtă (ex. „dum."). Se capitalizează în componentă. */
    weekday: string;
    /** Ziua + luna scurtă (ex. „5 iul."). */
    dayMonth: string;
    /** Ora și minutul (ex. „12:47"). */
    hm: string;
    /** Secundele, 2 cifre (ex. „16") — afișate estompat. */
    ss: string;
    /** false pe server / înainte de hidratare (evită mismatch); true după prima tresărire client. */
    ready: boolean;
}

/**
 * Ceas live (client-side). Returnează părțile datei/orei formatate pe limba curentă, actualizate la
 * fiecare secundă și expuse SEPARAT (zi / dată / oră / secunde) ca UI-ul să poată da ierarhie
 * vizuală (ora accentuată, secundele estompate). Pornește GOL ca să nu apară mismatch de hidratare.
 */
export function useLiveClock(locale: string): LiveClock {
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
        return { weekday: '', dayMonth: '', hm: '', ss: '', ready: false };
    }

    return {
        weekday: now.toLocaleDateString(jsLocale, { weekday: 'short' }),
        dayMonth: now.toLocaleDateString(jsLocale, { day: 'numeric', month: 'short' }),
        hm: now.toLocaleTimeString(jsLocale, { hour: '2-digit', minute: '2-digit' }),
        ss: String(now.getSeconds()).padStart(2, '0'),
        ready: true,
    };
}
