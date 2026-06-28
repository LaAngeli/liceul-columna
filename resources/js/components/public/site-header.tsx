import { router, usePage } from '@inertiajs/react';
import { ChevronDown, GraduationCap, LayoutDashboard, Menu, Phone, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { BrandButton, Container, FourStar } from '@/components/public/brand';
import { LanguageSwitcher } from '@/components/public/language-switcher';
import { LogoLockup } from '@/components/public/logo-lockup';
import { ThemeToggle } from '@/components/public/theme-toggle';
import { useTranslations } from '@/lib/i18n';
import { mainNav, utilityNav } from '@/lib/public-navigation';
import { cn } from '@/lib/utils';
import { dashboard, login } from '@/routes';

function useIsActive() {
    const { url } = usePage();
    const path = url.replace(/^\/(ru|en)(?=\/|$)/, '') || '/';
    return (href?: string) => {
        if (!href) return false;
        if (href === '/') return path === '/';
        return path === href || path.startsWith(`${href}/`);
    };
}

export function SiteHeader() {
    const { auth } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const isActive = useIsActive();
    const t = useTranslations();
    const label = (tKey: string | undefined, fallback: string) => (tKey ? t(tKey, fallback) : fallback);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Meniul mobil e un overlay full-screen: blochează scroll-ul fundalului,
    // se închide cu Escape și la orice navigare Inertia.
    useEffect(() => {
        if (!mobileOpen) {
            return;
        }
        const close = () => setMobileOpen(false);
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        const stopVisit = router.on('start', close);
        return () => {
            document.body.style.overflow = prevOverflow;
            window.removeEventListener('keydown', onKey);
            stopVisit();
        };
    }, [mobileOpen]);

    const cabinetHref = auth?.user ? (auth.canAccessAdmin ? '/admin' : dashboard().url) : login().url;
    const enrollHref = '/inregistrarea-student';

    return (
        <header className="sticky top-0 z-40 w-full">
            {/* Bara utilitară (navy) */}
            <div className="on-navy hidden border-b keyline bg-brand-navy text-[color:var(--brand-navy-foreground)] lg:block">
                <Container className="flex items-center justify-between gap-6 py-1.5 text-[0.78rem]">
                    <nav className="flex items-center gap-5">
                        {utilityNav.map((item) => (
                            <LocaleLink key={item.href} href={item.href} className="font-medium text-white/80 transition-colors hover:text-white">
                                {label(item.tKey, item.title)}
                            </LocaleLink>
                        ))}
                    </nav>
                    <div className="flex items-center gap-3">
                        <a href="tel:+37322742852" className="font-medium text-white/80 hover:text-white">
                            (+373) 22 74 28 52
                        </a>
                        <span className="text-white/30">·</span>
                        <LanguageSwitcher />
                    </div>
                </Container>
            </div>

            {/* Bara principală */}
            <div
                className={cn(
                    'border-b keyline bg-background/90 backdrop-blur transition-shadow supports-[backdrop-filter]:bg-background/75',
                    scrolled && 'shadow-[0_8px_24px_-18px_rgba(15,77,119,0.55)]',
                )}
            >
                <Container className={cn('flex items-center justify-between gap-4 transition-all', scrolled ? 'py-2' : 'py-2.5')}>
                    <LocaleLink href="/" aria-label="Liceul Columna" className="shrink-0">
                        <LogoLockup imgClassName={cn('w-auto transition-all', scrolled ? 'h-8 sm:h-9' : 'h-9 sm:h-10')} />
                    </LocaleLink>

                    {/* Navigare desktop */}
                    <nav className="hidden items-center gap-0.5 xl:flex">
                        {mainNav.map((item) =>
                            item.children ? (
                                <div key={item.title} className="group relative">
                                    <button
                                        type="button"
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-md px-3 py-2 text-[0.92rem] font-semibold text-brand-navy/80 transition-colors hover:text-brand-navy',
                                            item.href && isActive(item.href) && 'text-brand-navy',
                                        )}
                                    >
                                        {label(item.tKey, item.title)}
                                        <ChevronDown className="size-3.5 transition-transform group-hover:rotate-180" />
                                    </button>
                                    <div className="invisible absolute top-full left-0 z-50 min-w-64 translate-y-1 rounded-[12px] border keyline bg-popover p-1.5 opacity-0 shadow-lg transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                        {item.children.map((child) => (
                                            <LocaleLink
                                                key={child.href}
                                                href={child.href}
                                                className={cn(
                                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-foreground/85 hover:bg-accent hover:text-brand-navy',
                                                    isActive(child.href) && 'bg-accent font-semibold text-brand-navy',
                                                )}
                                            >
                                                <FourStar className="size-2 text-brand-green" />
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
                                        'rounded-md px-3 py-2 text-[0.92rem] font-semibold text-brand-navy/80 transition-colors hover:text-brand-navy',
                                        isActive(item.href) && 'text-brand-navy',
                                    )}
                                >
                                    {label(item.tKey, item.title)}
                                </LocaleLink>
                            ),
                        )}
                    </nav>

                    <div className="flex items-center gap-2">
                        <ThemeToggle className="hidden lg:block" />
                        <BrandButton href={cabinetHref} variant="ghost" icon={LayoutDashboard} className="hidden h-10 min-h-10 px-3.5 text-sm sm:inline-flex">
                            {t('action.cabinet', 'Cabinet')}
                        </BrandButton>
                        <BrandButton href={enrollHref} variant="primary" icon={GraduationCap} className="hidden h-10 min-h-10 px-3.5 text-sm md:inline-flex">
                            {t('menu.enroll', 'Înscrie copilul')}
                        </BrandButton>
                        <button
                            type="button"
                            onClick={() => setMobileOpen((v) => !v)}
                            className="inline-flex size-11 items-center justify-center rounded-md border keyline text-brand-navy xl:hidden"
                            aria-label={t('action.menu', 'Meniu')}
                            aria-expanded={mobileOpen}
                        >
                            {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                </Container>
            </div>

            {/* Navigare mobil — overlay full-screen (navy imersiv, ca secțiunile de brand) */}
            {mobileOpen && (
                <div className="on-navy fixed inset-0 z-50 flex flex-col bg-brand-navy text-[color:var(--brand-navy-foreground)] duration-200 animate-in fade-in-0 slide-in-from-top-3 xl:hidden">
                    <div className="dotgrid pointer-events-none absolute inset-0 opacity-[0.1]" aria-hidden="true" />

                    {/* Bara overlay: logo + închidere */}
                    <div className="relative border-b border-white/15" style={{ paddingTop: 'env(safe-area-inset-top)' }}>
                        <Container className="flex items-center justify-between gap-4 py-3">
                            <LocaleLink href="/" aria-label="Liceul Columna" onClick={() => setMobileOpen(false)} className="shrink-0">
                                <LogoLockup imgClassName="h-9 w-auto" />
                            </LocaleLink>
                            <button
                                type="button"
                                onClick={() => setMobileOpen(false)}
                                className="inline-flex size-11 items-center justify-center rounded-md border border-white/25 text-white transition-colors hover:border-brand-green hover:text-brand-green"
                                aria-label={t('action.close', 'Închide')}
                            >
                                <X className="size-5" />
                            </button>
                        </Container>
                    </div>

                    {/* Lista de navigare (scroll) */}
                    <nav className="relative flex-1 overflow-y-auto overscroll-contain">
                        <Container className="py-5">
                            <ul className="divide-y divide-white/10">
                                {mainNav.map((item, i) => {
                                    const index = String(i + 1).padStart(2, '0');
                                    return item.children ? (
                                        <li key={item.title}>
                                            <details className="group">
                                                <summary className="flex min-h-14 cursor-pointer list-none items-center gap-4 py-3">
                                                    <span className="numeral text-sm text-brand-green">{index}</span>
                                                    <span className="display flex-1 text-[clamp(1.375rem,5.5vw,1.75rem)] leading-tight">{label(item.tKey, item.title)}</span>
                                                    <ChevronDown className="size-5 shrink-0 text-white/60 transition-transform group-open:rotate-180" />
                                                </summary>
                                                <ul className="mb-2 ml-9 grid gap-0.5">
                                                    {item.children.map((child) => (
                                                        <li key={child.href}>
                                                            <LocaleLink
                                                                href={child.href}
                                                                onClick={() => setMobileOpen(false)}
                                                                className="flex min-h-11 items-center gap-2.5 rounded-md px-2 text-[0.95rem] font-medium text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                                                            >
                                                                <FourStar className="size-2 shrink-0 text-brand-green" />
                                                                {label(child.tKey, child.title)}
                                                            </LocaleLink>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </details>
                                        </li>
                                    ) : (
                                        <li key={item.href}>
                                            <LocaleLink href={item.href!} onClick={() => setMobileOpen(false)} className="flex min-h-14 items-center gap-4 py-3">
                                                <span className="numeral text-sm text-brand-green">{index}</span>
                                                <span className="display flex-1 text-[clamp(1.375rem,5.5vw,1.75rem)] leading-tight">{label(item.tKey, item.title)}</span>
                                            </LocaleLink>
                                        </li>
                                    );
                                })}
                            </ul>

                            {/* Resurse (linkuri utilitare) */}
                            <div className="mt-7 border-t border-white/15 pt-6">
                                <p className="eyebrow text-white/55">{t('menu.resources', 'Resurse')}</p>
                                <ul className="mt-3 grid grid-cols-2 gap-x-4">
                                    {utilityNav.map((item) => (
                                        <li key={item.href}>
                                            <LocaleLink
                                                href={item.href}
                                                onClick={() => setMobileOpen(false)}
                                                className="flex min-h-11 items-center text-sm text-white/75 transition-colors hover:text-white"
                                            >
                                                {label(item.tKey, item.title)}
                                            </LocaleLink>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Container>
                    </nav>

                    {/* Subsol fix: CTA + contact + limbă/temă */}
                    <div className="relative border-t border-white/15 bg-brand-navy/80 backdrop-blur" style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
                        <Container className="space-y-4 py-4">
                            <div className="grid grid-cols-2 gap-2">
                                <BrandButton href={cabinetHref} variant="ghost-navy" icon={LayoutDashboard} className="w-full text-sm">
                                    {t('action.cabinet', 'Cabinet')}
                                </BrandButton>
                                <BrandButton href={enrollHref} variant="primary" icon={GraduationCap} className="w-full text-sm">
                                    {t('menu.enroll', 'Înscrie copilul')}
                                </BrandButton>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <a href="tel:+37322742852" className="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-white/85 hover:text-white">
                                    <Phone className="size-4 text-brand-green" /> (+373) 22 74 28 52
                                </a>
                                <LanguageSwitcher />
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-white/70">{t('theme.label', 'Temă')}</span>
                                <ThemeToggle variant="tabs" />
                            </div>
                        </Container>
                    </div>
                </div>
            )}

            {/* Bară CTA fixă pe mobil */}
            <div className="fixed inset-x-0 bottom-0 z-40 flex gap-2 border-t keyline bg-background/95 p-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))] backdrop-blur md:hidden">
                <BrandButton href={cabinetHref} variant="ghost" icon={LayoutDashboard} className="flex-1 text-sm">
                    {t('action.cabinet', 'Cabinet')}
                </BrandButton>
                <BrandButton href={enrollHref} variant="primary" icon={GraduationCap} className="flex-1 text-sm">
                    {t('menu.enroll', 'Înscrie copilul')}
                </BrandButton>
            </div>
        </header>
    );
}
