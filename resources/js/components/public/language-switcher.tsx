import { usePage } from '@inertiajs/react';
import { localizePath, useLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const LOCALES = ['ro', 'ru', 'en'] as const;

/**
 * Comutator de limbă animat (pastilă segmentată cu indicator glisant).
 * Fiecare opțiune duce la /set-locale/{cod} cu redirect către pagina curentă
 * tradusă; serverul salvează preferința (cookie + user) și reîncarcă.
 */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { url } = usePage();
    const locale = useLocale();
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
                    href={`/set-locale/${code}?redirect=${encodeURIComponent(localizePath(url, code))}`}
                    aria-current={code === locale ? 'true' : undefined}
                    className={cn(
                        'relative z-10 inline-flex min-h-11 w-9 items-center justify-center rounded-full py-2 text-center uppercase transition-colors md:min-h-0 md:py-1',
                        code === locale ? 'text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {code}
                </a>
            ))}
        </div>
    );
}
