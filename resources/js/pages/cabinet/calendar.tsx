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

type View = 'month' | 'week' | 'day' | 'agenda';

const COLORS: Record<
    string,
    { dot: string; rail: string; chip: string; text: string }
> = {
    success: {
        dot: 'bg-emerald-500',
        rail: 'bg-emerald-500',
        chip: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
        text: 'text-emerald-600 dark:text-emerald-400',
    },
    accent: {
        dot: 'bg-sky-500',
        rail: 'bg-sky-500',
        chip: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
        text: 'text-sky-600 dark:text-sky-400',
    },
    danger: {
        dot: 'bg-red-500',
        rail: 'bg-red-500',
        chip: 'bg-red-500/12 text-red-600 dark:text-red-400',
        text: 'text-red-600 dark:text-red-400',
    },
    warning: {
        dot: 'bg-amber-500',
        rail: 'bg-amber-500',
        chip: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
        text: 'text-amber-600 dark:text-amber-400',
    },
    event: {
        dot: 'bg-violet-500',
        rail: 'bg-violet-500',
        chip: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
        text: 'text-violet-600 dark:text-violet-400',
    },
    neutral: {
        dot: 'bg-slate-400',
        rail: 'bg-slate-400',
        chip: 'bg-slate-400/12 text-slate-600 dark:text-slate-300',
        text: 'text-slate-600 dark:text-slate-400',
    },
    muted: {
        dot: 'bg-slate-300',
        rail: 'bg-slate-300',
        chip: 'bg-muted text-muted-foreground',
        text: 'text-muted-foreground',
    },
    info: {
        dot: 'bg-cyan-500',
        rail: 'bg-cyan-500',
        chip: 'bg-cyan-500/12 text-cyan-700 dark:text-cyan-300',
        text: 'text-cyan-600 dark:text-cyan-400',
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

function addDays(date: Date, delta: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + delta);

    return next;
}

function mondayOf(date: Date): Date {
    const offset = (date.getDay() + 6) % 7;

    return addDays(
        new Date(date.getFullYear(), date.getMonth(), date.getDate()),
        -offset,
    );
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function sortEvents(list: CalendarEvent[]): CalendarEvent[] {
    return [...list].sort((a, b) => {
        const ga = a.startTime ? 1 : 0;
        const gb = b.startTime ? 1 : 0;

        if (ga !== gb) {
            return ga - gb;
        }

        if (a.startTime && b.startTime) {
            return a.startTime.localeCompare(b.startTime);
        }

        return a.category.localeCompare(b.category);
    });
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

    const today = new Date();
    const todayStr = ymd(today);

    const [view, setView] = useState<View>('month');
    const [activeCats, setActiveCats] = useState<Set<string>>(new Set());
    const [selected, setSelected] = useState<CalendarEvent | null>(null);
    const [cursor, setCursor] = useState<Date>(() => {
        const [y, m] = month.split('-').map(Number);

        return today.getFullYear() === y && today.getMonth() === m - 1
            ? today
            : new Date(y, m - 1, 1);
    });

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

    const legend = useMemo(() => {
        const seen = new Map<string, string>();

        for (const e of events) {
            if (!seen.has(e.category)) {
                seen.set(e.category, e.color);
            }
        }

        return [...seen.entries()];
    }, [events]);

    function loadMonth(date: Date) {
        const ym = `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

        if (ym !== month) {
            router.get(
                '/cabinet/calendar',
                { month: ym, student: selectedStudent ?? undefined },
                {
                    only: ['events', 'month', 'selectedStudent'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }
    }

    function moveTo(date: Date) {
        setCursor(date);
        loadMonth(date);
    }

    function shift(dir: number) {
        if (view === 'month' || view === 'agenda') {
            moveTo(new Date(cursor.getFullYear(), cursor.getMonth() + dir, 1));
        } else if (view === 'week') {
            moveTo(addDays(cursor, dir * 7));
        } else {
            moveTo(addDays(cursor, dir));
        }
    }

    function selectChild(id: number | null) {
        router.get(
            '/cabinet/calendar',
            { month, student: id ?? undefined },
            {
                only: ['events', 'month', 'selectedStudent'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
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

    function openDay(dateStr: string) {
        setCursor(parseYmd(dateStr));
        setView('day');
    }

    const weekdays = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(localeTag, { weekday: 'short' });

        return Array.from({ length: 7 }, (_, i) =>
            fmt.format(new Date(2024, 0, 1 + i)),
        );
    }, [localeTag]);

    function periodTitle(): string {
        if (view === 'day') {
            return capitalize(
                cursor.toLocaleDateString(localeTag, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                }),
            );
        }

        if (view === 'week') {
            const start = mondayOf(cursor);
            const end = addDays(start, 6);
            const startStr = start.toLocaleDateString(localeTag, {
                day: 'numeric',
                month: 'short',
            });
            const endStr = end.toLocaleDateString(localeTag, {
                day: 'numeric',
                month: 'short',
            });

            return `${startStr} – ${endStr}`;
        }

        return capitalize(
            new Date(
                cursor.getFullYear(),
                cursor.getMonth(),
                1,
            ).toLocaleDateString(localeTag, { month: 'long', year: 'numeric' }),
        );
    }

    const views: { key: View; label: string }[] = [
        { key: 'month', label: t('ccal.month_view') },
        { key: 'week', label: t('ccal.week_view') },
        { key: 'day', label: t('ccal.day_view') },
        { key: 'agenda', label: t('ccal.agenda_view') },
    ];

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
                                selectChild(
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

                {/* Bara de instrumente */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            aria-label={t('ccal.prev')}
                            onClick={() => shift(-1)}
                            className="flex size-9 items-center justify-center rounded-md border border-sidebar-border/70 text-muted-foreground hover:bg-muted dark:border-sidebar-border"
                        >
                            ‹
                        </button>
                        <button
                            type="button"
                            onClick={() => moveTo(new Date())}
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        >
                            {t('ccal.today')}
                        </button>
                        <button
                            type="button"
                            aria-label={t('ccal.next')}
                            onClick={() => shift(1)}
                            className="flex size-9 items-center justify-center rounded-md border border-sidebar-border/70 text-muted-foreground hover:bg-muted dark:border-sidebar-border"
                        >
                            ›
                        </button>
                        <span className="ml-2 text-base font-semibold capitalize">
                            {periodTitle()}
                        </span>
                    </div>
                    <div className="inline-flex overflow-hidden rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        {views.map((v, i) => (
                            <button
                                key={v.key}
                                type="button"
                                onClick={() => setView(v.key)}
                                className={`px-3 py-1.5 text-sm font-medium transition-colors ${i > 0 ? 'border-l border-sidebar-border/70 dark:border-sidebar-border' : ''} ${
                                    view === v.key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted'
                                }`}
                            >
                                {v.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Vederea activă */}
                {view === 'month' && (
                    <MonthGrid
                        cursor={cursor}
                        weekdays={weekdays}
                        byDay={byDay}
                        todayStr={todayStr}
                        onDay={openDay}
                        onEvent={setSelected}
                    />
                )}
                {view === 'week' && (
                    <WeekBoard
                        cursor={cursor}
                        byDay={byDay}
                        todayStr={todayStr}
                        localeTag={localeTag}
                        onDay={openDay}
                        onEvent={setSelected}
                        emptyLabel={t('ccal.nothing_planned')}
                    />
                )}
                {view === 'day' && (
                    <DayAgenda
                        cursor={cursor}
                        byDay={byDay}
                        onEvent={setSelected}
                        emptyLabel={t('ccal.day_no_events')}
                        allDayLabel={t('ccal.all_day')}
                        categoryLabel={(c) => t(`ccal.cat_${c}`)}
                    />
                )}
                {view === 'agenda' && (
                    <AgendaList
                        byDay={byDay}
                        month={month}
                        localeTag={localeTag}
                        todayStr={todayStr}
                        onEvent={setSelected}
                        emptyLabel={t('ccal.no_events')}
                        categoryLabel={(c) => t(`ccal.cat_${c}`)}
                    />
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

            {selected && (
                <EventDetail
                    event={selected}
                    localeTag={localeTag}
                    onClose={() => setSelected(null)}
                    openLabel={t('ccal.open')}
                    closeLabel={t('ccal.close')}
                    allDayLabel={t('ccal.all_day')}
                    categoryLabel={t(`ccal.cat_${selected.category}`)}
                />
            )}
        </>
    );
}

function MonthGrid({
    cursor,
    weekdays,
    byDay,
    todayStr,
    onDay,
    onEvent,
}: {
    cursor: Date;
    weekdays: string[];
    byDay: Map<string, CalendarEvent[]>;
    todayStr: string;
    onDay: (date: string) => void;
    onEvent: (event: CalendarEvent) => void;
}) {
    const cells = useMemo(() => {
        const year = cursor.getFullYear();
        const monthIndex = cursor.getMonth();
        const lead = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
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
    }, [cursor]);

    return (
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
                                className="min-h-20 rounded-md bg-muted/20"
                            />
                        );
                    }

                    const dayNum = Number(cell.slice(-2));
                    const dayEvents = byDay.get(cell) ?? [];
                    const isToday = cell === todayStr;

                    return (
                        <button
                            type="button"
                            key={i}
                            onClick={() => onDay(cell)}
                            className={`min-h-20 rounded-md border bg-card p-1 text-left transition-colors hover:border-primary/40 ${
                                isToday
                                    ? 'border-[#9bc31e] ring-1 ring-[#9bc31e]'
                                    : 'border-sidebar-border/70 dark:border-sidebar-border'
                            }`}
                        >
                            <span
                                className={`inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold ${
                                    isToday
                                        ? 'bg-[#9bc31e] text-[#1d1d1c]'
                                        : 'text-muted-foreground'
                                }`}
                            >
                                {dayNum}
                            </span>
                            <span className="mt-0.5 flex flex-col gap-0.5">
                                {dayEvents.slice(0, 3).map((e) => (
                                    <span
                                        key={e.id}
                                        role="button"
                                        tabIndex={0}
                                        onClick={(ev) => {
                                            ev.stopPropagation();
                                            onEvent(e);
                                        }}
                                        onKeyDown={(ev) => {
                                            if (ev.key === 'Enter') {
                                                ev.stopPropagation();
                                                onEvent(e);
                                            }
                                        }}
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
                                {dayEvents.length > 3 && (
                                    <span className="px-1 text-[10px] text-muted-foreground">
                                        +{dayEvents.length - 3}
                                    </span>
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function WeekBoard({
    cursor,
    byDay,
    todayStr,
    localeTag,
    onDay,
    onEvent,
    emptyLabel,
}: {
    cursor: Date;
    byDay: Map<string, CalendarEvent[]>;
    todayStr: string;
    localeTag: string;
    onDay: (date: string) => void;
    onEvent: (event: CalendarEvent) => void;
    emptyLabel: string;
}) {
    const days = useMemo(() => {
        const start = mondayOf(cursor);

        return Array.from({ length: 7 }, (_, i) => addDays(start, i));
    }, [cursor]);

    return (
        <div className="grid gap-2 md:grid-cols-7">
            {days.map((day) => {
                const key = ymd(day);
                const dayEvents = sortEvents(byDay.get(key) ?? []);
                const isToday = key === todayStr;

                return (
                    <div
                        key={key}
                        className={`rounded-lg border bg-card p-2 dark:border-sidebar-border ${isToday ? 'border-[#9bc31e]' : 'border-sidebar-border/70'}`}
                    >
                        <button
                            type="button"
                            onClick={() => onDay(key)}
                            className="mb-1.5 flex w-full items-baseline justify-between"
                        >
                            <span className="text-xs font-medium text-muted-foreground capitalize">
                                {day.toLocaleDateString(localeTag, {
                                    weekday: 'short',
                                })}
                            </span>
                            <span
                                className={`text-sm font-semibold ${isToday ? 'text-[#6f8f15] dark:text-[#9bc31e]' : ''}`}
                            >
                                {day.getDate()}
                            </span>
                        </button>
                        <div className="flex flex-col gap-1">
                            {dayEvents.length === 0 ? (
                                <span className="py-1 text-[11px] text-muted-foreground/50">
                                    {emptyLabel}
                                </span>
                            ) : (
                                dayEvents.map((e) => (
                                    <button
                                        type="button"
                                        key={e.id}
                                        onClick={() => onEvent(e)}
                                        className={`flex items-center gap-1.5 rounded px-1.5 py-1 text-left text-xs ${colorFor(e.color).chip}`}
                                    >
                                        <span
                                            className={`size-1.5 shrink-0 rounded-full ${colorFor(e.color).dot}`}
                                        />
                                        {e.startTime && (
                                            <span className="shrink-0 font-medium tabular-nums">
                                                {e.startTime}
                                            </span>
                                        )}
                                        <span className="truncate">
                                            {e.title}
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function DayAgenda({
    cursor,
    byDay,
    onEvent,
    emptyLabel,
    allDayLabel,
    categoryLabel,
}: {
    cursor: Date;
    byDay: Map<string, CalendarEvent[]>;
    onEvent: (event: CalendarEvent) => void;
    emptyLabel: string;
    allDayLabel: string;
    categoryLabel: (category: string) => string;
}) {
    const dayEvents = sortEvents(byDay.get(ymd(cursor)) ?? []);

    if (dayEvents.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                {emptyLabel}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            {dayEvents.map((e) => (
                <EventRow
                    key={e.id}
                    event={e}
                    onClick={() => onEvent(e)}
                    timeLabel={e.startTime ?? allDayLabel}
                    categoryLabel={categoryLabel(e.category)}
                />
            ))}
        </div>
    );
}

function AgendaList({
    byDay,
    month,
    localeTag,
    todayStr,
    onEvent,
    emptyLabel,
    categoryLabel,
}: {
    byDay: Map<string, CalendarEvent[]>;
    month: string;
    localeTag: string;
    todayStr: string;
    onEvent: (event: CalendarEvent) => void;
    emptyLabel: string;
    categoryLabel: (category: string) => string;
}) {
    const days = useMemo(
        () => [...byDay.keys()].filter((d) => d.startsWith(month)).sort(),
        [byDay, month],
    );

    if (days.length === 0) {
        return (
            <p className="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                {emptyLabel}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {days.map((day) => (
                <div key={day}>
                    <h2
                        className={`mb-1.5 text-sm font-semibold capitalize ${day === todayStr ? 'text-[#6f8f15] dark:text-[#9bc31e]' : ''}`}
                    >
                        {capitalize(
                            parseYmd(day).toLocaleDateString(localeTag, {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                            }),
                        )}
                    </h2>
                    <div className="flex flex-col gap-2">
                        {sortEvents(byDay.get(day) ?? []).map((e) => (
                            <EventRow
                                key={e.id}
                                event={e}
                                onClick={() => onEvent(e)}
                                timeLabel={e.startTime ?? ''}
                                categoryLabel={categoryLabel(e.category)}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function EventRow({
    event,
    onClick,
    timeLabel,
    categoryLabel,
}: {
    event: CalendarEvent;
    onClick: () => void;
    timeLabel: string;
    categoryLabel: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex items-stretch gap-3 overflow-hidden rounded-lg border border-sidebar-border/70 bg-card text-left transition-colors hover:border-primary/40 dark:border-sidebar-border"
        >
            <span className={`w-1 shrink-0 ${colorFor(event.color).rail}`} />
            <span className="flex flex-1 flex-col py-2.5 pr-3">
                <span className="text-sm font-medium">{event.title}</span>
                <span className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                    <span className={colorFor(event.color).text}>
                        {categoryLabel}
                    </span>
                    {timeLabel && (
                        <span className="tabular-nums">· {timeLabel}</span>
                    )}
                </span>
            </span>
        </button>
    );
}

function EventDetail({
    event,
    localeTag,
    onClose,
    openLabel,
    closeLabel,
    allDayLabel,
    categoryLabel,
}: {
    event: CalendarEvent;
    localeTag: string;
    onClose: () => void;
    openLabel: string;
    closeLabel: string;
    allDayLabel: string;
    categoryLabel: string;
}) {
    const c = colorFor(event.color);
    const dateLabel = capitalize(
        parseYmd(event.date).toLocaleDateString(localeTag, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }),
    );

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                aria-label={closeLabel}
                onClick={onClose}
                className="absolute inset-0 bg-black/40"
            />
            <div className="relative z-10 w-full rounded-t-2xl border border-sidebar-border/70 bg-card p-5 shadow-xl sm:max-w-md sm:rounded-2xl dark:border-sidebar-border">
                <div className="flex items-start gap-3">
                    <span
                        className={`mt-1 size-3 shrink-0 rounded-full ${c.dot}`}
                    />
                    <div className="flex-1">
                        <p className={`text-xs font-medium ${c.text}`}>
                            {categoryLabel}
                        </p>
                        <h3 className="text-lg font-semibold">{event.title}</h3>
                        <p className="mt-0.5 text-sm text-muted-foreground capitalize">
                            {dateLabel}
                            {event.startTime
                                ? ` · ${event.startTime}`
                                : ` · ${allDayLabel}`}
                        </p>
                    </div>
                </div>
                <div className="mt-5 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        {closeLabel}
                    </button>
                    {event.deepLink && (
                        <Link
                            href={event.deepLink}
                            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            {openLabel}
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}

Calendar.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'ccal.title', href: '#' },
    ],
};
