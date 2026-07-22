import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpenCheck,
    CalendarDays,
    CalendarX,
    ChevronRight,
    ClipboardList,
    Clock3,
    FileText,
    LayoutGrid,
    MessageSquare,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLocale, useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

/** Un modul de catalog: destinație proprie + subsecțiuni adresabile prin `?sectiune=`. */
interface CatalogModule {
    title: string;
    path: string;
    icon: LucideIcon;
    subs: { title: string; section: string }[];
}

/**
 * Sidebar-ul cabinetului elev/părinte. Ierarhie pe categorii: Principal · Catalog (modulele
 * Note / Absențe / Orar / Teme, expandabile, cu subsecțiuni) · Documente · Evenimente · Comunicare.
 * Cabinetul e DOAR pentru vizualizare — fără secțiune „Setări" (datele contului apar read-only în
 * „Profil" din meniul de utilizator). Logo-ul trimite la homepage-ul public PE LIMBA curentă.
 */
export function AppSidebar() {
    const t = useTranslations();
    const locale = useLocale();
    const { isCurrentUrl } = useCurrentUrl();
    const { url } = usePage();
    // Homepage-ul public pe limba cabinetului: RO la root, RU/EN cu prefix de URL.
    const homeHref = locale === 'ro' ? '/' : `/${locale}`;

    // Starea activă se citește din URL: calea = modulul, `?sectiune=` = subsecțiunea.
    const [path, queryString] = url.split('?');
    const activeSection = new URLSearchParams(queryString ?? '').get('sectiune');

    // Deschis/închis per modul — implicit deschis modulul activ; utilizatorul poate plia manual.
    const [openOverrides, setOpenOverrides] = useState<Record<string, boolean>>({});

    const catalogModules: CatalogModule[] = [
        {
            title: t('cabinet.nav_grades', 'Note'),
            path: '/cabinet/note',
            icon: BookOpenCheck,
            subs: [
                { title: t('cabinet.catalog_sec_current', 'Note curente'), section: 'curente' },
                { title: t('cabinet.catalog_sec_averages', 'Medii semestriale'), section: 'medii' },
            ],
        },
        {
            title: t('cabinet.nav_absences', 'Absențe'),
            path: '/cabinet/absente',
            icon: CalendarX,
            subs: [
                { title: t('cabinet.catalog_sec_register', 'Registru'), section: 'registru' },
                { title: t('cabinet.catalog_sec_motivations', 'Motivări'), section: 'motivari' },
            ],
        },
        {
            title: t('cabinet.nav_schedule', 'Orar'),
            path: '/cabinet/orar',
            icon: Clock3,
            subs: [
                { title: t('cabinet.day_plan', 'Ziua mea'), section: 'zi' },
                { title: t('cabinet.catalog_sec_week', 'Orarul săptămânal'), section: 'saptamana' },
            ],
        },
        {
            title: t('cabinet.nav_homework', 'Teme'),
            path: '/cabinet/teme',
            icon: ClipboardList,
            subs: [],
        },
    ];

    const groups: { label: string; items: NavItem[] }[] = [
        {
            label: t('cabinet.grp_main', 'Principal'),
            items: [{ title: t('cabinet.nav_home', 'Acasă'), href: dashboard(), icon: LayoutGrid }],
        },
    ];

    const trailingGroups: { label: string; items: NavItem[] }[] = [
        {
            label: t('cabinet.grp_documents', 'Documente'),
            items: [{ title: t('cabinet.nav_documents', 'Documente'), href: '/cabinet/documente', icon: FileText }],
        },
        {
            label: t('cabinet.grp_events', 'Evenimente'),
            items: [{ title: t('ccal.title', 'Calendar'), href: '/cabinet/calendar', icon: CalendarDays }],
        },
        {
            label: t('cabinet.grp_communication', 'Comunicare'),
            items: [
                { title: t('cabinet.nav_messages', 'Mesaje'), href: '/cabinet/mesaje', icon: MessageSquare },
                { title: t('cabinet.nav_notifications', 'Notificări'), href: '/cabinet/notificari', icon: Bell },
            ],
        },
    ];

    const renderSimpleGroup = (group: { label: string; items: NavItem[] }) => (
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
    );

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
                {groups.map(renderSimpleGroup)}

                {/* === CATALOG — modulele Note / Absențe / Orar / Teme, expandabile === */}
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>{t('cabinet.grp_catalog', 'Catalog')}</SidebarGroupLabel>
                    <SidebarMenu>
                        {catalogModules.map((module) => {
                            const moduleActive = path === module.path;
                            const open = openOverrides[module.path] ?? moduleActive;
                            // Secțiunea implicită a modulului = prima — activă și fără `?sectiune=` în URL.
                            const currentSection = activeSection ?? module.subs[0]?.section;

                            if (module.subs.length === 0) {
                                return (
                                    <SidebarMenuItem key={module.path}>
                                        <SidebarMenuButton asChild isActive={moduleActive} tooltip={{ children: module.title }}>
                                            <Link href={module.path} prefetch>
                                                <module.icon />
                                                <span>{module.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            }

                            return (
                                <Collapsible
                                    key={module.path}
                                    asChild
                                    open={open}
                                    onOpenChange={(next) => setOpenOverrides((prev) => ({ ...prev, [module.path]: next }))}
                                >
                                    <SidebarMenuItem>
                                        {/* Titlul NAVIGHEAZĂ la modul (secțiunea implicită); chevronul doar pliază. */}
                                        <SidebarMenuButton asChild isActive={moduleActive} tooltip={{ children: module.title }}>
                                            <Link href={module.path} prefetch>
                                                <module.icon />
                                                <span>{module.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuAction
                                                className="transition-transform data-[state=open]:rotate-90"
                                                aria-label={module.title}
                                            >
                                                <ChevronRight />
                                            </SidebarMenuAction>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {module.subs.map((sub) => (
                                                    <SidebarMenuSubItem key={sub.section}>
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={moduleActive && currentSection === sub.section}
                                                        >
                                                            <Link href={`${module.path}?sectiune=${sub.section}`} prefetch>
                                                                <span>{sub.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>

                {trailingGroups.map(renderSimpleGroup)}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
