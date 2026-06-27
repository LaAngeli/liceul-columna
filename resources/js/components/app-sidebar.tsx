import { Link } from '@inertiajs/react';
import { Bell, BellRing, LayoutGrid, MessageSquare, Palette, ShieldCheck, UserCog } from 'lucide-react';
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
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/**
 * Sidebar-ul cabinetului elev/părinte — aceeași arhitectură ca panoul staff (Filament):
 * navigare grupată pe categorii, cu un grup „Setări" care adună toate preferințele.
 */
export function AppSidebar() {
    const t = useTranslations();
    const { isCurrentUrl } = useCurrentUrl();

    const groups: { label: string; items: NavItem[] }[] = [
        {
            label: t('cabinet.grp_main', 'Principal'),
            items: [{ title: t('cabinet.nav_home', 'Acasă'), href: dashboard(), icon: LayoutGrid }],
        },
        {
            label: t('cabinet.grp_communication', 'Comunicare'),
            items: [
                { title: t('cabinet.nav_messages', 'Mesaje'), href: '/cabinet/mesaje', icon: MessageSquare },
                { title: t('cabinet.nav_notifications', 'Notificări'), href: '/cabinet/notificari', icon: Bell },
            ],
        },
        {
            label: t('cabinet.grp_settings', 'Setări'),
            items: [
                { title: t('cabinet.set_profile', 'Profil'), href: editProfile(), icon: UserCog },
                { title: t('cabinet.set_security', 'Securitate'), href: editSecurity(), icon: ShieldCheck },
                { title: t('cabinet.set_notifications', 'Notificări'), href: '/cabinet/notificari/setari', icon: BellRing },
                { title: t('cabinet.set_appearance', 'Aspect'), href: editAppearance(), icon: Palette },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
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
