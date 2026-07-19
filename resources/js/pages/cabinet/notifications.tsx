import { Head, Link, router } from '@inertiajs/react';
import { Bell, BellOff, BookOpen, CalendarDays, Flag, GraduationCap, Inbox, MailCheck, Megaphone, MessageSquare, SquarePen, UserPlus  } from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { open, read, readAll, settings as notificationSettings } from '@/routes/cabinet/notifications';

interface NotificationItem {
    id: string;
    type: string | null;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string | null;
}

// Dicționar tip-notificare → iconiță lucide (oglindă a NotificationType::icon() din PHP). Folosit
// pentru diferențiere vizuală în inboxul cabinetului. Fallback la Bell pentru tipuri necunoscute.
const TYPE_ICONS: Record<string, LucideIcon> = {
    new_grade: GraduationCap,
    new_absence: CalendarDays,
    new_homework: BookOpen,
    status_change: Flag,
    new_message: MessageSquare,
    announcement: Megaphone,
    grade_correction_request: SquarePen,
    absence_motivation_submitted: MailCheck,
    document_request_submitted: Inbox,
    admission_request_submitted: UserPlus,
};

interface Props {
    notifications: NotificationItem[];
    // Totalul REAL de necitite (lista de mai sus e plafonată la 50). Butonul „Marchează tot" se
    // afișează după acest total — altfel necititele mai vechi de cele 50 lăsau un badge de neșters.
    unreadTotal: number;
}

export default function NotificationsPage({ notifications, unreadTotal }: Props) {
    const t = useTranslations();
    // Optimistic mark-read: id-urile sunt mutate INSTANT în acest Set la click; cardul apare ca citit
    // imediat, fără să așteptăm round-trip-ul server. Pe eroare HTTP, le scoatem (revenire la „necitit").
    const [optimisticRead, setOptimisticRead] = useState<Set<string>>(new Set());
    // Afișăm butonul dacă există necitite în lista curentă SAU în restul (necitite mai vechi de 50):
    // markAllRead operează pe întreaga relație, deci golește tot, inclusiv cele neafișate.
    const visibleUnread = notifications.some((n) => !n.read && !optimisticRead.has(n.id));
    const hasUnread = visibleUnread || unreadTotal > notifications.filter((n) => !n.read).length;

    function markRead(id: string) {
        if (optimisticRead.has(id)) {
            return;
        }

        setOptimisticRead((prev) => new Set(prev).add(id));
        router.post(read(id).url, {}, {
            preserveScroll: true,
            preserveState: true,
            onError: () =>
                setOptimisticRead((prev) => {
                    const next = new Set(prev);
                    next.delete(id);

                    return next;
                }),
        });
    }

    function markAll() {
        router.post(readAll().url, {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <>
            <Head title={t('cabinet.notif_title')} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-semibold">{t('cabinet.notif_title')}</h1>
                    <Link
                        href={notificationSettings().url}
                        className="ml-auto inline-flex items-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        {t('cabinet.notif_settings')}
                    </Link>
                    {hasUnread && (
                        <button
                            type="button"
                            onClick={markAll}
                            className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            {t('cabinet.notif_mark_all')}
                        </button>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <EmptyState icon={BellOff} title={t('cabinet.notif_empty')} />
                ) : (
                    <ul className="flex flex-col gap-2">
                        {notifications.map((n) => (
                            <NotificationCard
                                key={n.id}
                                notification={optimisticRead.has(n.id) ? { ...n, read: true } : n}
                                onMarkRead={markRead}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

/**
 * Card-link pattern accesibil: cardul ÎNTREG e ținta interactivă, niciodată un `<li onClick>` sau
 * link imbricat. Pe tastatură: Tab focalizează, Enter/Space activează. Fără suprapunere de event-uri.
 *
 *   - cu URL            → `<Link>` spre ruta „deschide" (serverul marchează citit + redirecționează
 *                          atomic — un singur click; fără al doilea request Inertia concurent, care
 *                          anula navigarea)
 *   - fără URL + neread → `<button>` (marchează citit — aici chiar asta e acțiunea completă)
 *   - fără URL + read   → `<div>` static (nu mai e nimic de făcut pe el)
 */
function NotificationCard({
    notification: n,
    onMarkRead,
}: {
    notification: NotificationItem;
    onMarkRead: (id: string) => void;
}) {
    const t = useTranslations();

    // Iconiță per-tip (decorativă, aria-hidden) — semnal vizual complementar punctului „necitit".
    const TypeIcon = (n.type && TYPE_ICONS[n.type]) || Bell;

    const baseClass = `block w-full rounded-xl border px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
        n.read
            ? 'border-sidebar-border/70 bg-card dark:border-sidebar-border'
            : 'border-primary/30 bg-primary/5 hover:bg-primary/10'
    }`;

    const inner = (
        <div className="flex items-start gap-3">
            <span
                className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <TypeIcon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 font-medium">
                    {!n.read && <span className="size-2 shrink-0 rounded-full bg-primary" aria-hidden="true" />}
                    <span className="truncate">{n.title}</span>
                </p>
                {n.body && <p className="mt-0.5 text-sm text-muted-foreground">{n.body}</p>}
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">{n.at}</span>
        </div>
    );

    if (n.url) {
        const ariaLabel = n.read
            ? `${t('cabinet.notif_open')}: ${n.title}`
            : `${n.title} — ${t('cabinet.notif_unread')}, ${t('cabinet.notif_open').toLowerCase()}`;

        return (
            <li>
                <Link href={open(n.id).url} className={baseClass} aria-label={ariaLabel}>
                    {inner}
                </Link>
            </li>
        );
    }

    if (!n.read) {
        return (
            <li>
                <button
                    type="button"
                    onClick={() => onMarkRead(n.id)}
                    className={baseClass}
                    aria-label={`${t('cabinet.notif_mark_read')}: ${n.title}`}
                >
                    {inner}
                </button>
            </li>
        );
    }

    return (
        <li className={baseClass}>
            {inner}
        </li>
    );
}

NotificationsPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_notifications', href: '#' },
    ],
};
