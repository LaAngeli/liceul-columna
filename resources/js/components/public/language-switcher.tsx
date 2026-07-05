import { usePage } from '@inertiajs/react';
import { localizePath, useLocale, useRouteSlugs } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const LOCALES = ['ro', 'ru', 'en'] as const;

/**
 * Comutator de limbă animat (pastilă segmentată cu indicator glisant).
 * Fiecare opțiune duce la /set-locale/{cod} cu redirect către pagina curentă
 * tradusă; serverul salvează preferința (cookie + user) și reîncarcă.
 *
 * `prefixed` (implicit true) = SITE PUBLIC: URL-urile au prefix de limbă (`/ru/...`),
 * deci redirectul se re-localizează prin `localizePath`. Pentru ZONA AUTENTIFICATĂ
 * (cabinet/dashboard/setări) unde rutele NU au prefix — limba vine din cookie/`user.locale` —
 * treci `prefixed={false}`: redirectul rămâne URL-ul curent NEATINS. Altfel s-ar construi
 * un `/ru/dashboard` inexistent → 404 (deși preferința se salvează). Vezi routes/web.php.
 */
export function LanguageSwitcher({ className, prefixed = true }: { className?: string; prefixed?: boolean }) {
    const { url } = usePage();
    const locale = useLocale();
    const routeSlugs = useRouteSlugs();
    const activeIndex = Math.max(0, LOCALES.indexOf(locale as (typeof LOCALES)[number]));

    return (
        <div
            className={cn(
                'relative inline-flex rounded-full border border-border bg-background/60 p-0.5 text-xs font-semibold backdrop-blur',
                className,
            )}
            role="group"
            aria-label="Limbă"
        >
            <span
                className="absolute top-0.5 bottom-0.5 left-0.5 w-9 rounded-full bg-primary shadow-sm transition-transform duration-300 ease-out"
                style={{ transform: `translateX(${activeIndex * 100}%)` }}
                aria-hidden
            />
            {LOCALES.map((code) => (
                <a
                    key={code}
                    href={`/set-locale/${code}?redirect=${encodeURIComponent(prefixed ? localizePath(url, code, routeSlugs) : url)}`}
                    aria-current={code === locale ? 'true' : undefined}
                    className={cn(
                        'relative z-10 inline-flex min-h-10 w-9 items-center justify-center rounded-full py-2 text-center uppercase transition-colors md:min-h-0 md:py-1',
                        code === locale ? 'text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {code}
                </a>
            ))}
        </div>
    );
}
