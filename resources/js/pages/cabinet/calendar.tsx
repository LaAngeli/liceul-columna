import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useLocale, useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface CalendarEvent {
    id: string;
    source: string;
    category: string;
    color: string;
    title: string;
    date: string;
    allDay: boolean;
    startTime: string | null;
    endTime: string | null;
    deepLink: string | null;
    editable: boolean;
    meta: Record<string, unknown>;
}

interface Child {
    id: number;
    name: string;
}

interface Props {
    events: CalendarEvent[];
    children: Child[];
    month: string;
    selectedStudent: number | null;
}

const COLORS: Record<string, { dot: string; chip: string }> = {
    success: {
        dot: 'bg-emerald-500',
        chip: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    },
    accent: {
        dot: 'bg-sky-500',
        chip: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    },
    danger: {
        dot: 'bg-red-500',
        chip: 'bg-red-500/12 text-red-600 dark:text-red-400',
    },
    warning: {
        dot: 'bg-amber-500',
        chip: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
    },
    event: {
        dot: 'bg-violet-500',
        chip: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
    },
    neutral: {
        dot: 'bg-slate-400',
        chip: 'bg-slate-400/12 text-slate-600 dark:text-slate-300',
    },
    muted: { dot: 'bg-slate-300', chip: 'bg-muted text-muted-foreground' },
    info: {
        dot: 'bg-cyan-500',
        chip: 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-300',
    },
};

function colorFor(color: string) {
    return COLORS[color] ?? COLORS.muted;
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function ymd(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseYmd(value: string): Date {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(y, m - 1, d);
}

export default function Calendar({
    events,
    children: kids,
    month,
    selectedStudent,
}: Props) {
    const t = useTranslations();
    const locale = useLocale();
    const localeTag =
        locale === 'ru' ? 'ru-RU' : locale === 'en' ? 'en-US' : 'ro-RO';

    const [view, setView] = useState<'month' | 'agenda'>('month');
    const [activeCats, setActiveCats] = useState<Set<string>>(new Set());
    const [selectedDay, setSelectedDay] = useState<string | null>(null);

    const [year, monthIndex] = useMemo(() => {
        const [y, m] = month.split('-').map(Number);

        return [y, m - 1];
    }, [month]);

    const todayStr = ymd(new Date());

    const visibleEvents = useMemo(
        () =>
            events.filter(
                (e) => activeCats.size === 0 || activeCats.has(e.category),
            ),
        [events, activeCats],
    );

    const byDay = useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();

        for (const e of visibleEvents) {
            const list = map.get(e.date) ?? [];
            list.push(e);
            map.set(e.date, list);
        }

        return map;
    }, [visibleEvents]);

    // Legenda = categoriile distincte prezente în lună, cu culoarea lor.
    const legend = useMemo(() => {
        const seen = new Map<string, string>();

        for (const e of events) {
            if (!seen.has(e.category)) {
                seen.set(e.category, e.color);
            }
        }

        return [...seen.entries()];
    }, [events]);

    const monthName = new Date(year, monthIndex, 1).toLocaleDateString(
        localeTag,
        { month: 'long', year: 'numeric' },
    );

    const weekdays = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(localeTag, { weekday: 'short' });

        // 1 ian. 2024 = luni → generăm etichetele scurte cu luni pe prima poziție.
        return Array.from({ length: 7 }, (_, i) =>
            fmt.format(new Date(2024, 0, 1 + i)),
        );
    }, [localeTag]);

    const cells = useMemo(() => {
        const firstDay = new Date(year, monthIndex, 1);
        const lead = (firstDay.getDay() + 6) % 7;
        const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
        const list: (string | null)[] = [];

        for (let i = 0; i < lead; i++) {
            list.push(null);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            list.push(ymd(new Date(year, monthIndex, d)));
        }

        while (list.length % 7 !== 0) {
            list.push(null);
        }

        return list;
    }, [year, monthIndex]);

    function navigate(targetMonth: string, student: number | null) {
        router.get(
            '/cabinet/calendar',
            { month: targetMonth, student: student ?? undefined },
            {
                only: ['events', 'month', 'selectedStudent'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function shiftMonth(delta: number) {
        const base = new Date(year, monthIndex + delta, 1);
        setSelectedDay(null);
        navigate(
            `${base.getFullYear()}-${pad(base.getMonth() + 1)}`,
            selectedStudent,
        );
    }

    function goToday() {
        const now = new Date();
        setSelectedDay(null);
        navigate(
            `${now.getFullYear()}-${pad(now.getMonth() + 1)}`,
            selectedStudent,
        );
    }

    function toggleCat(cat: string) {
        setActiveCats((prev) => {
            const next = new Set(prev);

            if (next.has(cat)) {
                next.delete(cat);
            } else {
                next.add(cat);
            }

            return next;
        });
    }

    const detailDay = selectedDay ?? todayStr;
    const detailEvents = byDay.get(detailDay) ?? [];

    const agendaDays = useMemo(
        () => [...byDay.keys()].filter((d) => d.startsWith(month)).sort(),
        [byDay, month],
    );

    const tabClass = (active: boolean) =>
        `px-3 py-1.5 text-sm font-medium transition-colors ${active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}`;

    return (
        <>
            <Head title={t('ccal.title')} />
            <div className="flex flex-col gap-4 p-4">
                {/* Antet */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {t('ccal.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('ccal.subtitle')}
                        </p>
                    </div>
                    {kids.length > 1 && (
                        <select
                            value={selectedStudent ?? ''}
                            onChange={(e) =>
                                navigate(
                                    month,
                                    e.target.value
                                        ? Number(e.target.value)
                                        : null,
                                )
                            }
                            className="rounded-md border border-sidebar-border/70 bg-card px-3 py-2 text-sm dark:border-sidebar-border"
                        >
                            <option value="">{t('ccal.all_children')}</option>
                            {kids.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    )}
                </div>

                {/* Bara de control */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            aria-label={t('ccal.prev')}
                            onClick={() => shiftMonth(-1)}
                            className="flex size-9 items-center justify-center rounded-md border border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border"
                        >
                            ‹
                        </button>
                        <button
                            type="button"
                            aria-label={t('ccal.next')}
                            onClick={() => shiftMonth(1)}
                            className="flex size-9 items-center justify-center rounded-md border border-sidebar-border/70 hover:bg-muted dark:border-sidebar-border"
                        >
                            ›
                        </button>
                        <button
                            type="button"
                            onClick={goToday}
                            className="ml-1 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        >
                            {t('ccal.today')}
                        </button>
                        <span className="ml-2 text-base font-semibold capitalize">
                            {monthName}
                        </span>
                    </div>
                    <div className="inline-flex overflow-hidden rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        <button
                            type="button"
                            className={tabClass(view === 'month')}
                            onClick={() => setView('month')}
                        >
                            {t('ccal.month_view')}
                        </button>
                        <button
                            type="button"
                            className={`border-l border-sidebar-border/70 dark:border-sidebar-border ${tabClass(view === 'agenda')}`}
                            onClick={() => setView('agenda')}
                        >
                            {t('ccal.agenda_view')}
                        </button>
                    </div>
                </div>

                {events.length === 0 && (
                    <p className="rounded-lg border border-sidebar-border/70 bg-muted/30 p-6 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        {t('ccal.no_events')}
                    </p>
                )}

                {/* Vederea lunară */}
                {view === 'month' && events.length > 0 && (
                    <div>
                        <div className="grid grid-cols-7 gap-1 pb-1 text-center text-xs text-muted-foreground">
                            {weekdays.map((w, i) => (
                                <div key={i} className="py-1 capitalize">
                                    {w}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7 gap-1">
                            {cells.map((cell, i) => {
                                if (cell === null) {
                                    return (
                                        <div
                                            key={i}
                                            className="min-h-16 rounded-md bg-muted/20"
                                        />
                                    );
                                }

                                const dayNum = Number(cell.slice(-2));
                                const dayEvents = byDay.get(cell) ?? [];
                                const isToday = cell === todayStr;
                                const isSelected = cell === selectedDay;

                                return (
                                    <button
                                        type="button"
                                        key={i}
                                        onClick={() => setSelectedDay(cell)}
                                        className={`min-h-16 rounded-md border p-1 text-left transition-colors hover:bg-muted/50 ${
                                            isSelected
                                                ? 'border-primary ring-1 ring-primary'
                                                : isToday
                                                  ? 'border-primary/60'
                                                  : 'border-sidebar-border/70 dark:border-sidebar-border'
                                        }`}
                                    >
                                        <span
                                            className={`text-xs font-medium ${isToday ? 'text-primary' : 'text-muted-foreground'}`}
                                        >
                                            {dayNum}
                                        </span>
                                        <span className="mt-0.5 flex flex-col gap-0.5">
                                            {dayEvents.slice(0, 2).map((e) => (
                                                <span
                                                    key={e.id}
                                                    className={`flex items-center gap-1 truncate rounded px-1 py-0.5 text-[10px] leading-tight ${colorFor(e.color).chip}`}
                                                >
                                                    <span
                                                        className={`size-1.5 shrink-0 rounded-full ${colorFor(e.color).dot}`}
                                                    />
                                                    <span className="truncate">
                                                        {e.title}
                                                    </span>
                                                </span>
                                            ))}
                                            {dayEvents.length > 2 && (
                                                <span className="px-1 text-[10px] text-muted-foreground">
                                                    +{dayEvents.length - 2}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* Detaliul zilei selectate */}
                        <div className="mt-4 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-semibold capitalize">
                                {parseYmd(detailDay).toLocaleDateString(
                                    localeTag,
                                    {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    },
                                )}
                            </h2>
                            <div className="mt-2 flex flex-col gap-2">
                                {detailEvents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('ccal.day_no_events')}
                                    </p>
                                ) : (
                                    detailEvents.map((e) => (
                                        <EventRow
                                            key={e.id}
                                            event={e}
                                            openLabel={t('ccal.open')}
                                        />
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Vederea agendă */}
                {view === 'agenda' && events.length > 0 && (
                    <div className="flex flex-col gap-4">
                        {agendaDays.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('ccal.no_events')}
                            </p>
                        ) : (
                            agendaDays.map((day) => (
                                <div
                                    key={day}
                                    className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                                >
                                    <h2
                                        className={`text-sm font-semibold capitalize ${day === todayStr ? 'text-primary' : ''}`}
                                    >
                                        {parseYmd(day).toLocaleDateString(
                                            localeTag,
                                            {
                                                weekday: 'long',
                                                day: 'numeric',
                                                month: 'long',
                                            },
                                        )}
                                    </h2>
                                    <div className="mt-2 flex flex-col gap-2">
                                        {(byDay.get(day) ?? []).map((e) => (
                                            <EventRow
                                                key={e.id}
                                                event={e}
                                                openLabel={t('ccal.open')}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* Legendă / filtre */}
                {legend.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 border-t border-sidebar-border/70 pt-3 dark:border-sidebar-border">
                        <span className="text-xs font-medium text-muted-foreground">
                            {t('ccal.legend')}:
                        </span>
                        {legend.map(([cat, color]) => {
                            const active =
                                activeCats.size === 0 || activeCats.has(cat);

                            return (
                                <button
                                    type="button"
                                    key={cat}
                                    onClick={() => toggleCat(cat)}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-opacity ${
                                        active
                                            ? 'border-sidebar-border/70 dark:border-sidebar-border'
                                            : 'border-transparent opacity-40'
                                    }`}
                                >
                                    <span
                                        className={`size-2 rounded-full ${colorFor(color).dot}`}
                                    />
                                    {t(`ccal.cat_${cat}`)}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

function EventRow({
    event,
    openLabel,
}: {
    event: CalendarEvent;
    openLabel: string;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <span
                className={`size-2.5 shrink-0 rounded-full ${colorFor(event.color).dot}`}
            />
            <span className="text-sm">{event.title}</span>
            {event.startTime && (
                <span className="text-xs text-muted-foreground">
                    {event.startTime}
                </span>
            )}
            {event.deepLink && (
                <Link
                    href={event.deepLink}
                    className="ml-auto text-xs font-medium text-primary hover:underline"
                >
                    {openLabel}
                </Link>
            )}
        </div>
    );
}

Calendar.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'ccal.title', href: '#' },
    ],
};
