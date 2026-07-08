import { Link } from '@inertiajs/react';
import { Bell, CalendarDays, FileText, LayoutGrid, MessageSquare, UserCircle } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLocale, useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/**
 * Sidebar-ul cabinetului elev/părinte. Navigare grupată; cabinetul e DOAR pentru vizualizare —
 * fără secțiune „Setări” (datele contului apar read-only în „Profil”, iar gestiunea conturilor revine
 * personalului). Logo-ul de brand trimite la homepage-ul site-ului public, PE LIMBA curentă
 * (RO la root, RU/EN cu prefix) — altfel serverul ar redirecta `/` → `/ru`, cu un bounce în plus.
 */
export function AppSidebar() {
    const t = useTranslations();
    const locale = useLocale();
    const { isCurrentUrl } = useCurrentUrl();
    // Homepage-ul public pe limba cabinetului: RO la root, RU/EN cu prefix de URL.
    const homeHref = locale === 'ro' ? '/' : `/${locale}`;

    const groups: { label: string; items: NavItem[] }[] = [
        {
            label: t('cabinet.grp_main', 'Principal'),
            items: [
                { title: t('cabinet.nav_home', 'Acasă'), href: dashboard(), icon: LayoutGrid },
                { title: t('profile.head', 'Profil'), href: '/cabinet/profil', icon: UserCircle },
            ],
        },
        {
            // „Documente" = categorie proprie (ca la staff: grup în sidebar + pagina cu taburi pe
            // subcategorii în interior), nu un element sub „Principal".
            label: t('cabinet.grp_documents', 'Documente'),
            items: [
                { title: t('cabinet.nav_documents', 'Documente'), href: '/cabinet/documente', icon: FileText },
            ],
        },
        {
            label: t('cabinet.grp_events', 'Evenimente'),
            items: [
                { title: t('ccal.title', 'Calendar'), href: '/cabinet/calendar', icon: CalendarDays },
            ],
        },
        {
            label: t('cabinet.grp_communication', 'Comunicare'),
            items: [
                { title: t('cabinet.nav_messages', 'Mesaje'), href: '/cabinet/mesaje', icon: MessageSquare },
                { title: t('cabinet.nav_notifications', 'Notificări'), href: '/cabinet/notificari', icon: Bell },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        {/* Buton „logo" mai înalt (h-14) ca să încapă lockup-ul de 42px cu spațiu de respirație,
                            și `justify-center` ca logo-ul să fie CENTRAT orizontal (nu aliniat stânga). */}
                        <SidebarMenuButton size="lg" className="h-14 justify-center" asChild>
                            <Link href={homeHref} aria-label="Liceul Columna">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <SidebarGroup key={group.label} className="px-2 py-0">
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={isCurrentUrl(item.href)} tooltip={{ children: item.title }}>
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
