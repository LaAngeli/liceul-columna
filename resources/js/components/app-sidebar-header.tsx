import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';
import { AppClock } from '@/components/app-clock';
import { AppUserBadge } from '@/components/app-user-badge';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { LanguageSwitcher } from '@/components/public/language-switcher';
import { ThemeToggle } from '@/components/public/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useTranslations } from '@/lib/i18n';
import { notifications } from '@/routes/cabinet';
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
    const unread = Number(usePage().props.notificationsUnread ?? 0);

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
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                {/* Pe mobil e SINGURA cale spre meniu → țintă tactilă de 44px (WCAG 2.5.5).
                    Pe desktop rămâne compact (28px), unde ținta e mouse-ul. */}
                <SidebarTrigger className="-ml-1 size-11 md:size-7" />
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

                <Link
                    href={notifications.url()}
                    aria-label={t('cabinet.notif_title')}
                    title={t('cabinet.notif_title')}
                    className="relative inline-flex size-11 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground md:size-9"
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Link>
            </div>
        </header>
    );
}
