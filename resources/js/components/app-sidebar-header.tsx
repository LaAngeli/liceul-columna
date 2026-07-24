import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppClock } from '@/components/app-clock';
import { AppUserBadge } from '@/components/app-user-badge';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { LanguageSwitcher } from '@/components/public/language-switcher';
import { ThemeToggle } from '@/components/public/theme-toggle';
import { MenuToggle } from '@/components/ui/menu-toggle';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { useLocale, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Cât de des recerem doar prop-ul `notificationsUnread` din server (în ms). 60s e suficient pentru
 * a percepe „aproape live" fără să încărcăm serverul. Real-time prin WebSockets = Faza B (Reverb).
 */
const NOTIFICATION_POLL_MS = 60_000;

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const t = useTranslations();
    const locale = useLocale();
    // Logo-ul din navbar duce la homepage-ul public pe limba curentă (RO la root, RU/EN cu prefix).
    const homeHref = locale === 'ro' ? '/' : `/${locale}`;
    const { openMobile, toggleSidebar } = useSidebar();

    // Header „dinamic la scroll": după primii pixeli derulați devine compact, cu fundal
    // translucid + blur și umbră — rămâne lipit sus (sticky) fără să consume spațiu vertical.
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Polling natival Inertia v3: la fiecare 60s reîmprospătăm DOAR prop-ul de unread (`only:[…]`),
    // fără să refacem pagina. Pauzat când tab-ul nu e vizibil (Inertia gestionează automat).
    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({ only: ['notificationsUnread'] });
        }, NOTIFICATION_POLL_MS);

        return () => window.clearInterval(interval);
    }, []);

    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex shrink-0 items-center gap-2 border-b px-6 md:rounded-t-xl md:px-4',
                'transition-[height,background-color,box-shadow,border-color] duration-300 ease-out motion-reduce:transition-none',
                'group-has-data-[collapsible=icon]/sidebar-wrapper:h-12',
                scrolled
                    ? 'h-14 border-sidebar-border/70 bg-background/85 shadow-[0_10px_28px_-20px_rgba(15,77,119,0.45)] backdrop-blur-md supports-[backdrop-filter]:bg-background/70'
                    : 'h-16 border-sidebar-border/50 bg-transparent',
            )}
        >
            {/* MOBIL — structură de app-bar: clopoțel STÂNGA · logo CENTRAT · meniu DREAPTA.
                Logo-ul e singurul element clickabil din centru (link strict pe lockup, nu pe bară). */}
            <NotificationBell className="-ml-2 md:hidden" />
            <Link
                href={homeHref}
                aria-label="Liceul Columna"
                // Centrat pe orizontală ȘI verticală; min-h-11 → zonă tactilă de 44px. Logo-ul e la
                // h-[37px] (+15% față de h-8): tot sub cei 44px ai link-ului, deci navbar-ul (h-16/h-14
                // la scroll, înălțime FIXĂ, logo poziționat absolut) nu se schimbă cu niciun pixel.
                className="absolute top-1/2 left-1/2 inline-flex min-h-11 -translate-x-1/2 -translate-y-1/2 items-center rounded-md px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:hidden"
            >
                <img src="/images/logo/columna-horizontal.png" alt="Liceul Columna" className="h-[37px] w-auto dark:hidden" />
                <img src="/images/logo/columna-horizontal-white.png" alt="Liceul Columna" className="hidden h-[37px] w-auto dark:block" />
            </Link>

            {/* DESKTOP — trigger-ul sidebar-ului + breadcrumbs, ca până acum. */}
            <div className="hidden min-w-0 items-center gap-2 md:flex">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-2 sm:gap-3">
                <AppClock />
                <AppUserBadge />

                {/* Comutatoare limbă + temă (sincronizate cu Filament prin localStorage `theme` și
                    cu serverul prin /set-locale). Ascunse pe ecrane foarte înguste pentru spațiu.
                    `prefixed={false}`: cabinetul e în zona autentificată, cu rute FĂRĂ prefix de
                    limbă (limba vine din cookie/user) → redirect pe URL-ul curent, nu pe `/ru/...`
                    care ar da 404. */}
                <LanguageSwitcher prefixed={false} className="hidden sm:inline-flex" />
                <ThemeToggle variant="icon" className="hidden sm:block" />

                <NotificationBell className="hidden md:inline-flex" />

                {/* Mobil: hamburger animat (☰→✕) pe dreapta — 44px. */}
                <MenuToggle
                    open={openMobile}
                    onClick={toggleSidebar}
                    label={openMobile ? t('action.close', 'Închide') : t('action.menu', 'Meniu')}
                    className="-mr-2 md:hidden"
                />
            </div>
        </header>
    );
}
