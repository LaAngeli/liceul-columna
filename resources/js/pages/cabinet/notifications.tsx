import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArrowDownWideNarrow,
    ArrowUpNarrowWide,
    Bell,
    BellOff,
    BookOpen,
    CalendarDays,
    Flag,
    GraduationCap,
    Inbox,
    MailCheck,
    Megaphone,
    MessageSquare,
    SearchX,
    SquarePen,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { notifications as notificationsIndex } from '@/routes/cabinet';
import { open, read, readAll, settings as notificationSettings } from '@/routes/cabinet/notifications';

interface NotificationItem {
    id: string;
    type: string | null;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string | null;
    month: string | null;
    archivedAt: string | null;
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

interface ArchiveFilters {
    q: string;
    tip: string | null;
    de_la: string | null;
    pana_la: string | null;
    sort: 'recente' | 'vechi';
}

interface Props {
    tab: 'recente' | 'arhiva';
    counts: { active: number; archived: number };
    archiveDays: number;
    unreadTotal: number;
    notifications: NotificationItem[];
    archive?: {
        items: NotificationItem[];
        page: number;
        lastPage: number;
        total: number;
        prev: string | null;
        next: string | null;
    };
    archiveFilters?: ArchiveFilters;
    archiveTypes?: Record<string, string>;
}

export default function NotificationsPage({
    tab,
    counts,
    archiveDays,
    unreadTotal,
    notifications,
    archive,
    archiveFilters,
    archiveTypes,
}: Props) {
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

    const tabClass = (active: boolean) =>
        `inline-flex min-h-11 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-h-9 ${
            active
                ? 'bg-primary text-primary-foreground'
                : 'border border-sidebar-border/70 text-muted-foreground hover:bg-muted dark:border-sidebar-border'
        }`;

    return (
        <>
            <Head title={t('cabinet.notif_title')} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-semibold">{t('cabinet.notif_title')}</h1>
                    <Link
                        href={notificationSettings().url}
                        className="ml-auto inline-flex min-h-11 items-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted md:min-h-0 dark:border-sidebar-border"
                    >
                        {t('cabinet.notif_settings')}
                    </Link>
                    {tab === 'recente' && hasUnread && (
                        <button
                            type="button"
                            onClick={markAll}
                            className="inline-flex min-h-11 items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 md:min-h-0"
                        >
                            {t('cabinet.notif_mark_all')}
                        </button>
                    )}
                </div>

                {/* Filele Recente / Arhivă — nimic nu se șterge: istoricul complet e mereu la un click. */}
                <nav className="flex flex-wrap items-center gap-2" aria-label={t('cabinet.notif_title')}>
                    <Link href={notificationsIndex().url} className={tabClass(tab === 'recente')} preserveScroll>
                        <Bell className="size-4" aria-hidden="true" />
                        {t('cabinet.notif_tab_recent')}
                        <span className="tabular-nums text-xs opacity-80">{counts.active}</span>
                    </Link>
                    <Link
                        href={notificationsIndex({ query: { tab: 'arhiva' } }).url}
                        className={tabClass(tab === 'arhiva')}
                        preserveScroll
                    >
                        <Archive className="size-4" aria-hidden="true" />
                        {t('cabinet.notif_tab_archive')}
                        <span className="tabular-nums text-xs opacity-80">{counts.archived}</span>
                    </Link>
                </nav>

                {tab === 'recente' ? (
                    notifications.length === 0 ? (
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
                    )
                ) : (
                    <ArchiveTab
                        archive={archive}
                        filters={archiveFilters}
                        types={archiveTypes ?? {}}
                        archiveDays={archiveDays}
                    />
                )}
            </div>
        </>
    );
}

/**
 * Fila „Arhivă": istoric complet, doar de consultare — căutare, filtre pe tip + perioadă, sortare
 * cronologică în ambele sensuri, grupare pe luni și paginare. Filtrele navighează server-side
 * (starea trăiește în URL — partajabilă, back-friendly), cu debounce pe căutare.
 */
function ArchiveTab({
    archive,
    filters,
    types,
    archiveDays,
}: {
    archive?: Props['archive'];
    filters?: ArchiveFilters;
    types: Record<string, string>;
    archiveDays: number;
}) {
    const t = useTranslations();
    const current: ArchiveFilters = filters ?? { q: '', tip: null, de_la: null, pana_la: null, sort: 'recente' };
    const [search, setSearch] = useState(current.q);
    const searchRef = useRef(current.q);

    // Căutare cu debounce 400ms: navigăm doar când textul chiar s-a schimbat față de server.
    useEffect(() => {
        if (search === searchRef.current) {
            return;
        }

        const handle = setTimeout(() => {
            searchRef.current = search;
            applyFilters({ q: search });
        }, 400);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function applyFilters(overrides: Partial<ArchiveFilters>) {
        const next = { ...current, ...overrides };
        const query: Record<string, string> = { tab: 'arhiva' };

        if (next.q.trim() !== '') {
            query.q = next.q.trim();
        }

        if (next.tip) {
            query.tip = next.tip;
        }

        if (next.de_la) {
            query.de_la = next.de_la;
        }

        if (next.pana_la) {
            query.pana_la = next.pana_la;
        }

        if (next.sort === 'vechi') {
            query.sort = 'vechi';
        }

        router.get(notificationsIndex().url, query, { preserveState: true, preserveScroll: true, replace: true });
    }

    const hasFilters = current.q !== '' || current.tip !== null || current.de_la !== null || current.pana_la !== null;
    const items = archive?.items ?? [];

    const inputClass =
        'min-h-11 rounded-md border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-h-9 dark:border-sidebar-border';

    return (
        <section className="flex flex-col gap-4" aria-label={t('cabinet.notif_tab_archive')}>
            <p className="text-sm text-muted-foreground">
                {t('cabinet.notif_archive_hint').replace(':zile', String(archiveDays))}
            </p>

            {/* Bara de investigare a arhivei — căutare + tip + interval + sens cronologic. */}
            <div className="flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('cabinet.notif_search')}
                    aria-label={t('cabinet.notif_search')}
                    className={`${inputClass} w-full sm:w-64`}
                />
                <select
                    value={current.tip ?? ''}
                    onChange={(e) => applyFilters({ tip: e.target.value === '' ? null : e.target.value })}
                    aria-label={t('cabinet.notif_filter_type')}
                    className={inputClass}
                >
                    <option value="">{t('cabinet.notif_filter_all_types')}</option>
                    {Object.entries(types).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
                <label className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    {t('cabinet.notif_from')}
                    <input
                        type="date"
                        value={current.de_la ?? ''}
                        onChange={(e) => applyFilters({ de_la: e.target.value === '' ? null : e.target.value })}
                        className={inputClass}
                    />
                </label>
                <label className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    {t('cabinet.notif_until')}
                    <input
                        type="date"
                        value={current.pana_la ?? ''}
                        onChange={(e) => applyFilters({ pana_la: e.target.value === '' ? null : e.target.value })}
                        className={inputClass}
                    />
                </label>
                <button
                    type="button"
                    onClick={() => applyFilters({ sort: current.sort === 'vechi' ? 'recente' : 'vechi' })}
                    className="inline-flex min-h-11 items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted md:min-h-9 dark:border-sidebar-border"
                    aria-pressed={current.sort === 'vechi'}
                >
                    {current.sort === 'vechi' ? (
                        <ArrowUpNarrowWide className="size-4" aria-hidden="true" />
                    ) : (
                        <ArrowDownWideNarrow className="size-4" aria-hidden="true" />
                    )}
                    {current.sort === 'vechi' ? t('cabinet.notif_sort_old') : t('cabinet.notif_sort_new')}
                </button>
                {hasFilters && (
                    <button
                        type="button"
                        onClick={() => {
                            setSearch('');
                            searchRef.current = '';
                            router.get(
                                notificationsIndex().url,
                                { tab: 'arhiva' },
                                { preserveState: true, preserveScroll: true, replace: true },
                            );
                        }}
                        className="text-sm font-medium text-primary hover:underline"
                    >
                        {t('cabinet.notif_reset')}
                    </button>
                )}
            </div>

            {items.length === 0 ? (
                <EmptyState
                    icon={hasFilters ? SearchX : Archive}
                    title={hasFilters ? t('cabinet.notif_archive_empty_filtered') : t('cabinet.notif_archive_empty')}
                />
            ) : (
                <div className="flex flex-col gap-2">
                    {items.map((n, index) => {
                        const previous = index > 0 ? items[index - 1] : null;
                        const showMonth = n.month !== null && n.month !== previous?.month;

                        return (
                            <div key={n.id} className="flex flex-col gap-2">
                                {showMonth && (
                                    <h2 className="mt-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground first:mt-0">
                                        {n.month}
                                    </h2>
                                )}
                                <ul>
                                    <NotificationCard notification={n} archived />
                                </ul>
                            </div>
                        );
                    })}
                </div>
            )}

            {archive !== undefined && archive.lastPage > 1 && (
                <nav className="flex items-center justify-between gap-3" aria-label={t('cabinet.notif_tab_archive')}>
                    {archive.prev !== null ? (
                        <Link
                            href={archive.prev}
                            preserveScroll
                            preserveState
                            className="inline-flex min-h-11 items-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted md:min-h-9 dark:border-sidebar-border"
                        >
                            {t('cabinet.notif_prev')}
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-sm tabular-nums text-muted-foreground">
                        {archive.page} / {archive.lastPage}
                    </span>
                    {archive.next !== null ? (
                        <Link
                            href={archive.next}
                            preserveScroll
                            preserveState
                            className="inline-flex min-h-11 items-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted md:min-h-9 dark:border-sidebar-border"
                        >
                            {t('cabinet.notif_next')}
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
        </section>
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
 *
 * Varianta „archived": vizual estompat + ștampila arhivării — istoricul se distinge dintr-o privire
 * de lista activă. Nu există nicio acțiune de ștergere, nicăieri.
 */
function NotificationCard({
    notification: n,
    onMarkRead,
    archived = false,
}: {
    notification: NotificationItem;
    onMarkRead?: (id: string) => void;
    archived?: boolean;
}) {
    const t = useTranslations();

    // Iconiță per-tip (decorativă, aria-hidden) — semnal vizual complementar punctului „necitit".
    const TypeIcon = (n.type && TYPE_ICONS[n.type]) || Bell;

    const baseClass = `block w-full rounded-xl border px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
        archived
            ? 'border-dashed border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border'
            : n.read
              ? 'border-sidebar-border/70 bg-card dark:border-sidebar-border'
              : 'border-primary/30 bg-primary/5 hover:bg-primary/10'
    }`;

    const inner = (
        <div className="flex items-start gap-3">
            <span
                className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg ${
                    archived ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary'
                }`}
                aria-hidden="true"
            >
                <TypeIcon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <p className={`flex items-center gap-2 font-medium ${archived ? 'text-muted-foreground' : ''}`}>
                    {!n.read && !archived && (
                        <span className="size-2 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                    )}
                    <span className="truncate">{n.title}</span>
                </p>
                {n.body && <p className="mt-0.5 text-sm text-muted-foreground">{n.body}</p>}
                {archived && n.archivedAt && (
                    <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <Archive className="size-3" aria-hidden="true" />
                        {t('cabinet.notif_archived_on')} {n.archivedAt}
                    </p>
                )}
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">{n.at}</span>
        </div>
    );

    if (n.url) {
        const ariaLabel = n.read
            ? `${t('cabinet.notif_open')}: ${n.title}`
            : `${n.title} — ${t('cabinet.notif_unread')}, ${t('cabinet.notif_open').toLowerCase()}`;

        return (
            <li className="list-none">
                <Link href={open(n.id).url} className={baseClass} aria-label={ariaLabel}>
                    {inner}
                </Link>
            </li>
        );
    }

    if (!n.read && onMarkRead) {
        return (
            <li className="list-none">
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

    return <li className={`list-none ${baseClass}`}>{inner}</li>;
}

NotificationsPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_notifications', href: '#' },
    ],
};
