import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, X } from 'lucide-react';
import { useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { LanguageSwitcher } from '@/components/public/language-switcher';
import { ThemeToggle } from '@/components/public/theme-toggle';
import { T } from '@/components/t';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { mainNav, utilityNav } from '@/lib/public-navigation';
import { dashboard, login } from '@/routes';

function useIsActive() {
    const { url } = usePage();
    const path = url.replace(/^\/(ru|en)(?=\/|$)/, '') || '/';
    return (href?: string) => {
        if (!href) {
            return false;
        }
        if (href === '/') {
            return path === '/';
        }
        return path === href || path.startsWith(`${href}/`);
    };
}

export function SiteHeader() {
    const { auth } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const isActive = useIsActive();
    const t = useTranslations();
    const label = (tKey: string | undefined, fallback: string) => (tKey ? t(tKey, fallback) : fallback);

    return (
        <header className="sticky top-0 z-40 w-full border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            {/* Bara utilitară */}
            <div className="hidden border-b border-border/60 bg-muted/40 text-xs text-muted-foreground lg:block">
                <div className="mx-auto flex max-w-7xl items-center justify-center gap-4 px-6 py-1.5">
                    <nav className="flex items-center gap-3">
                        {utilityNav.map((item) => (
                            <LocaleLink key={item.href} href={item.href} className={cn('hover:text-foreground', isActive(item.href) && 'font-medium text-foreground')}>
                                {label(item.tKey, item.title)}
                            </LocaleLink>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Bara principală */}
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-3 sm:gap-4 sm:px-6">
                <LocaleLink href="/" className="flex items-center gap-2.5">
                    <img src="/images/logo/columna-navy.png" alt="Liceul Columna" className="h-11 w-auto dark:hidden" />
                    <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="hidden h-11 w-auto dark:block" />
                    <span className="font-serif text-lg leading-tight font-semibold tracking-tight">Liceul Columna</span>
                </LocaleLink>

                {/* Navigare desktop */}
                <nav className="hidden items-center gap-1 xl:flex">
                    {mainNav.map((item) =>
                        item.children ? (
                            <div key={item.title} className="group relative">
                                <button
                                    type="button"
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground',
                                        item.href && isActive(item.href) && 'text-primary',
                                    )}
                                >
                                    {label(item.tKey, item.title)}
                                    <ChevronDown className="size-3.5 transition-transform group-hover:rotate-180" />
                                </button>
                                <div className="invisible absolute top-full left-0 z-50 min-w-56 translate-y-1 rounded-md border border-border bg-popover p-1 opacity-0 shadow-lg transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                    {item.href && (
                                        <LocaleLink href={item.href} className="block rounded-sm px-3 py-2 text-sm font-medium hover:bg-accent">
                                            {label(item.tKey, item.title)} — {t('action.presentation', 'prezentare')}
                                        </LocaleLink>
                                    )}
                                    {item.children.map((child) => (
                                        <LocaleLink key={child.href} href={child.href} className={cn('block rounded-sm px-3 py-2 text-sm hover:bg-accent', isActive(child.href) && 'bg-accent font-medium')}>
                                            {label(child.tKey, child.title)}
                                        </LocaleLink>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <LocaleLink
                                key={item.href}
                                href={item.href!}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground',
                                    isActive(item.href) && 'text-primary',
                                )}
                            >
                                {label(item.tKey, item.title)}
                            </LocaleLink>
                        ),
                    )}
                </nav>

                <div className="flex items-center gap-2">
                    <div className="hidden xl:block">
                        <LanguageSwitcher />
                    </div>
                    <ThemeToggle className="hidden xl:block" />
                    {!auth?.user ? (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <Link href={login()}>
                                <T k="action.login" fallback="Autentificare" />
                            </Link>
                        </Button>
                    ) : auth.canAccessAdmin ? (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <a href="/admin">
                                <T k="action.panel" fallback="Panou" />
                            </a>
                        </Button>
                    ) : (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <Link href={dashboard()}>
                                <T k="action.cabinet" fallback="Cabinet" />
                            </Link>
                        </Button>
                    )}
                    <button
                        type="button"
                        onClick={() => setMobileOpen((v) => !v)}
                        className="inline-flex size-11 items-center justify-center rounded-md border border-border xl:hidden"
                        aria-label={t('action.menu', 'Meniu')}
                    >
                        {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                </div>
            </div>

            {/* Navigare mobil */}
            {mobileOpen && (
                <div className="border-t border-border bg-background xl:hidden">
                    <nav className="mx-auto max-w-7xl space-y-1 px-4 py-4 sm:px-6">
                        {mainNav.map((item) =>
                            item.children ? (
                                <details key={item.title} className="group">
                                    <summary className="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-medium hover:bg-accent">
                                        {label(item.tKey, item.title)}
                                        <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div className="ml-3 border-l border-border pl-3">
                                        {item.href && (
                                            <LocaleLink href={item.href} className="block rounded-sm px-3 py-2 text-sm hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                                {label(item.tKey, item.title)} — {t('action.presentation', 'prezentare')}
                                            </LocaleLink>
                                        )}
                                        {item.children.map((child) => (
                                            <LocaleLink key={child.href} href={child.href} className="block rounded-sm px-3 py-2 text-sm hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                                {label(child.tKey, child.title)}
                                            </LocaleLink>
                                        ))}
                                    </div>
                                </details>
                            ) : (
                                <LocaleLink key={item.href} href={item.href!} className="block rounded-md px-3 py-2 text-sm font-medium hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                    {label(item.tKey, item.title)}
                                </LocaleLink>
                            ),
                        )}
                        <div className="mt-2 border-t border-border pt-2">
                            {utilityNav.map((item) => (
                                <LocaleLink key={item.href} href={item.href} className="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                    {label(item.tKey, item.title)}
                                </LocaleLink>
                            ))}
                        </div>
                        <div className="mt-3 flex items-center justify-between gap-3 border-t border-border px-3 pt-4">
                            <span className="text-sm font-medium text-muted-foreground">{t('language', 'Limbă')}</span>
                            <LanguageSwitcher />
                        </div>
                        <div className="mt-2 flex items-center justify-between gap-3 px-3">
                            <span className="text-sm font-medium text-muted-foreground">{t('theme.label', 'Temă')}</span>
                            <ThemeToggle variant="tabs" />
                        </div>
                    </nav>
                </div>
            )}
        </header>
    );
}
