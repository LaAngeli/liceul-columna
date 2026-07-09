import { ExternalLink, KeyRound, ShieldCheck } from 'lucide-react';
import { useTranslations } from '@/lib/i18n';

// URL-urile magazinelor de aplicații — TREBUIE să coincidă cu comanda `app:generate-authenticator-qr`
// (sursa QR-urilor statice din public/images/authenticator/). Dacă se schimbă, actualizează în ambele
// locuri și re-rulează comanda.
const STORES = [
    {
        key: 'android',
        url: 'https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2',
        qr: '/images/authenticator/google-authenticator-android.svg',
        labelKey: 'settings.authapp_android_label',
        labelFallback: 'Android',
        storeKey: 'settings.authapp_android_store',
        storeFallback: 'Google Play',
    },
    {
        key: 'ios',
        url: 'https://apps.apple.com/md/app/google-authenticator/id388497605',
        qr: '/images/authenticator/google-authenticator-ios.svg',
        labelKey: 'settings.authapp_ios_label',
        labelFallback: 'iPhone / iPad',
        storeKey: 'settings.authapp_ios_store',
        storeFallback: 'App Store',
    },
] as const;

/**
 * Ghid „ai nevoie de o aplicație de autentificare" pentru fluxul 2FA-TOTP: explică pe scurt cum
 * funcționează, listează pașii și oferă 2 coduri QR (Android + iOS) pentru descărcarea aplicației
 * Google Authenticator. Afișat unde se configurează 2FA (cabinet + pagina de configurare forțată),
 * pentru utilizatorii care nu au încă un autentificator instalat.
 */
export default function AuthenticatorAppGuide() {
    const t = useTranslations();

    const steps = [
        t('settings.authapp_step1', 'Instalează pe telefon o aplicație de autentificare (recomandăm Google Authenticator) — scanează unul dintre codurile QR de mai jos cu camera telefonului sau caut-o în magazinul de aplicații.'),
        t('settings.authapp_step2', 'Revino aici și apasă butonul „Activează 2FA" de mai jos. Se va afișa un cod QR unic pentru contul tău.'),
        t('settings.authapp_step3', 'Deschide aplicația de autentificare, apasă „+" și scanează acel cod. Contul liceului va apărea în listă.'),
        t('settings.authapp_step4', 'Introdu codul de 6 cifre afișat de aplicație (se schimbă la fiecare 30 de secunde). Gata — îl vei folosi la fiecare conectare.'),
    ];

    const qrAlt = t('settings.authapp_qr_alt', 'Cod QR pentru descărcarea aplicației');
    const openStore = t('settings.authapp_open_store', 'Deschide în magazin');

    return (
        <section className="rounded-xl border border-primary/20 bg-primary/5 p-4 sm:p-5">
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                    <ShieldCheck className="size-5" />
                </span>
                <div className="min-w-0">
                    <h4 className="font-semibold">{t('settings.authapp_title', 'Ai nevoie de o aplicație de autentificare')}</h4>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('settings.authapp_intro', 'Autentificarea în doi pași adaugă un al doilea pas la conectare: pe lângă parolă, introduci un cod temporar generat de o aplicație de pe telefonul tău. Astfel, chiar dacă cineva îți află parola, nu îți poate accesa contul fără telefon. Ai nevoie o singură dată de o aplicație gratuită de autentificare.')}
                    </p>
                </div>
            </div>

            {/* Pași */}
            <ol className="mt-4 space-y-2.5">
                {steps.map((step, index) => (
                    <li key={index} className="flex gap-3 text-sm">
                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground" aria-hidden="true">
                            {index + 1}
                        </span>
                        <span className="text-foreground/90">{step}</span>
                    </li>
                ))}
            </ol>

            {/* Descărcare aplicație — QR + link */}
            <div className="mt-5">
                <p className="text-sm font-medium">{t('settings.authapp_download', 'Descarcă aplicația Google Authenticator:')}</p>
                <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {STORES.map((store) => {
                        const label = t(store.labelKey, store.labelFallback);

                        return (
                            <a
                                key={store.key}
                                href={store.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="group flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-card p-3 transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:border-sidebar-border"
                            >
                                <img
                                    src={store.qr}
                                    alt={`${qrAlt} — ${label}`}
                                    width={92}
                                    height={92}
                                    className="size-[92px] shrink-0 rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                                />
                                <span className="min-w-0">
                                    <span className="block font-semibold">{label}</span>
                                    <span className="block text-xs text-muted-foreground">{t(store.storeKey, store.storeFallback)}</span>
                                    <span className="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-primary group-hover:underline">
                                        {openStore}
                                        <ExternalLink className="size-3" aria-hidden="true" />
                                    </span>
                                </span>
                            </a>
                        );
                    })}
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                    {t('settings.authapp_other', 'Funcționează și cu alte aplicații de autentificare (Authy, Microsoft Authenticator ș.a.).')}
                </p>
            </div>

            {/* Alternativa email */}
            <p className="mt-4 flex items-start gap-2 rounded-lg bg-background/70 p-3 text-xs text-muted-foreground">
                <KeyRound className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                {t('settings.authapp_email_note', 'Nu ai un smartphone sau preferi altă metodă? Poți primi codul de conectare pe e-mail — vezi opțiunea de mai jos.')}
            </p>
        </section>
    );
}
