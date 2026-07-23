/**
 * Banner de consimțământ cookies (site public) — GDPR / Legea 133/2011.
 * Categorii granulare (necesare blocate ON + preferințe / statistici / marketing opt-in),
 * butoane cu prominență egală Acceptă/Respinge (fără dark patterns), persistat în localStorage.
 * `getCookieConsent()` e expus ca să poată condiționa scripturile viitoare (analytics etc.).
 * Re-deschidere prin `window.dispatchEvent(new CustomEvent('cookie-settings:open'))` (link footer).
 */
import { Cookie, Shield } from 'lucide-react';
import { useEffect, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { BrandButton } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const CONSENT_KEY = 'cookie-consent';
const CONSENT_VERSION = 1;

export interface CookieConsentValue {
    v: number;
    necessary: true;
    preferences: boolean;
    analytics: boolean;
    marketing: boolean;
    ts: number;
}

export function getCookieConsent(): CookieConsentValue | null {
    if (typeof window === 'undefined') {
return null;
}

    try {
        const raw = localStorage.getItem(CONSENT_KEY);

        if (!raw) {
return null;
}

        const c = JSON.parse(raw) as CookieConsentValue;

        return c && c.v === CONSENT_VERSION ? c : null;
    } catch {
        return null;
    }
}

function Toggle({ checked, onChange, disabled, label }: { checked: boolean; onChange: (v: boolean) => void; disabled?: boolean; label: string }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => !disabled && onChange(!checked)}
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
                checked ? 'bg-brand-green' : 'bg-brand-navy/20',
                disabled && 'cursor-not-allowed opacity-60',
            )}
        >
            <span className={cn('inline-block size-5 transform rounded-full bg-white shadow transition-transform', checked ? 'translate-x-[1.375rem]' : 'translate-x-0.5')} />
        </button>
    );
}

export function CookieConsent() {
    const t = useTranslations();
    // Deschis DIN PRIMA randare când nu există consimțământ salvat: citirea se face în
    // inițializatorul lazy (rulează o singură dată, la montare), nu într-un efect — fără setState
    // sincron în efect și fără frame-ul în care bannerul lipsește.
    // ⚠️ Dacă se activează vreodată SSR (azi nu e operațional — nu există entry `ssr.tsx` și nici
    // bundle în `bootstrap/ssr`), `getCookieConsent()` întoarce null pe server, deci bannerul ar
    // apărea în HTML-ul SSR: atunci treci pe `useSyncExternalStore` cu `getServerSnapshot`.
    const [open, setOpen] = useState(() => !getCookieConsent());
    const [showPrefs, setShowPrefs] = useState(false);
    const [prefs, setPrefs] = useState({ preferences: true, analytics: false, marketing: false });

    useEffect(() => {
        // setState-urile de aici sunt în CALLBACK-ul unui eveniment (nu în corpul efectului):
        // sincronizarea cu un sistem extern, exact ce permite regula.
        const reopen = () => {
            const c = getCookieConsent();

            if (c) {
                setPrefs({ preferences: c.preferences, analytics: c.analytics, marketing: c.marketing });
            }

            setShowPrefs(true);
            setOpen(true);
        };
        window.addEventListener('cookie-settings:open', reopen);

        return () => window.removeEventListener('cookie-settings:open', reopen);
    }, []);

    const persist = (vals: { preferences: boolean; analytics: boolean; marketing: boolean }) => {
        const value: CookieConsentValue = { v: CONSENT_VERSION, necessary: true, ...vals, ts: Date.now() };

        try {
            localStorage.setItem(CONSENT_KEY, JSON.stringify(value));
        } catch {
            /* ignore */
        }

        window.dispatchEvent(new CustomEvent('cookie-consent:updated', { detail: value }));
        setOpen(false);
        setShowPrefs(false);
    };

    if (!open) {
return null;
}

    const categories = [
        { key: 'necessary' as const, locked: true, on: true, title: t('cookies.necessary_t', 'Strict necesare'), desc: t('cookies.necessary_d', 'Esențiale pentru funcționarea site-ului (sesiune, securitate, limbă). Mereu active.') },
        { key: 'preferences' as const, locked: false, on: prefs.preferences, title: t('cookies.prefs_t', 'Preferințe'), desc: t('cookies.prefs_d', 'Rețin opțiuni precum tema (luminos/întunecat) și limba aleasă.') },
        { key: 'analytics' as const, locked: false, on: prefs.analytics, title: t('cookies.analytics_t', 'Statistici'), desc: t('cookies.analytics_d', 'Ne ajută să înțelegem, în mod anonim, cum este folosit site-ul.') },
        { key: 'marketing' as const, locked: false, on: prefs.marketing, title: t('cookies.marketing_t', 'Marketing'), desc: t('cookies.marketing_d', 'Pentru conținut și anunțuri relevante (momentan inactive).') },
    ];

    return (
        <div className="fixed inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))] z-50 px-3 pb-1 md:bottom-4 md:px-4">
            <div
                role="dialog"
                aria-modal="false"
                aria-labelledby="cookie-title"
                className="mx-auto max-h-[calc(100dvh-6rem)] max-w-3xl overflow-x-hidden overflow-y-auto rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-background/98 shadow-[0_18px_50px_-20px_rgba(15,77,119,0.55)] backdrop-blur"
            >
                <div className="p-5 sm:p-6">
                    <div className="flex items-start gap-3">
                        <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-navy/8 text-brand-navy">
                            <Cookie className="size-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <h2 id="cookie-title" className="display text-[1.25rem] text-brand-navy">
                                {t('cookies.title', 'Respectăm confidențialitatea ta')}
                            </h2>
                            <p className="mt-1.5 text-sm leading-relaxed text-brand-gray">
                                {t('cookies.text', 'Folosim cookies necesare pentru funcționarea site-ului și, doar cu acordul tău, cookies pentru preferințe și statistici.')}{' '}
                                <LocaleLink href="/politica-cookies" className="font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-2">
                                    {t('cookies.policy_cookies', 'Politica cookie-uri')}
                                </LocaleLink>
                                {' · '}
                                <LocaleLink href="/confidentialitate" className="font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-2">
                                    {t('cookies.policy', 'Politica de confidențialitate')}
                                </LocaleLink>
                            </p>
                        </div>
                    </div>

                    {showPrefs && (
                        <ul className="mt-5 space-y-3 border-t keyline pt-5">
                            {categories.map((c) => (
                                <li key={c.key} className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold text-brand-navy">{c.title}</span>
                                            {c.locked && (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-brand-green/15 px-2 py-0.5 text-[0.7rem] font-semibold text-brand-navy">
                                                    <Shield className="size-3" /> {t('cookies.always_on', 'Mereu active')}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs leading-relaxed text-brand-gray">{c.desc}</p>
                                    </div>
                                    <Toggle
                                        checked={c.on}
                                        disabled={c.locked}
                                        label={c.title}
                                        onChange={(v) => setPrefs((p) => ({ ...p, [c.key]: v }))}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <BrandButton variant="primary" onClick={() => persist({ preferences: true, analytics: true, marketing: true })} className="w-full sm:w-auto">
                            {t('cookies.accept_all', 'Acceptă toate')}
                        </BrandButton>
                        {showPrefs ? (
                            <BrandButton variant="ghost" onClick={() => persist(prefs)} className="w-full sm:w-auto">
                                {t('cookies.save', 'Salvează preferințele')}
                            </BrandButton>
                        ) : (
                            <BrandButton variant="ghost" onClick={() => persist({ preferences: false, analytics: false, marketing: false })} className="w-full sm:w-auto">
                                {t('cookies.necessary_only', 'Doar necesare')}
                            </BrandButton>
                        )}
                        {!showPrefs && (
                            <button
                                type="button"
                                onClick={() => setShowPrefs(true)}
                                className="inline-flex min-h-11 items-center justify-center font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4 sm:ml-auto"
                            >
                                {t('cookies.preferences', 'Preferințe')}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
