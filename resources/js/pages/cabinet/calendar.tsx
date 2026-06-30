import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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

// Audit § calendar #4 — paletă brand-aliniată: cele 8 chei semantice ale backend-ului (succes/accent/
// danger/warning/event/neutral/muted/info) sunt mapate la PATRU familii din identitatea liceului
// Columna: navy `--brand-navy` (primar, evenimente formale), verde `--brand-green` (acțiuni pozitive
// și comunicare), destructive (absențe și termene-limită = atenție), muted (rutine și structură).
// Astfel calendarul nu mai „iese" din restul cabinetului (care folosește exclusiv tokenuri brand).
const COLORS: Record<
    string,
    { dot: string; rail: string; chip: string; text: string }
> = {
    // Homework — verde brand: „de făcut", acțiune pozitivă pentru elev.
    success: {
        dot: 'bg-brand-green',
        rail: 'bg-brand-green',
        chip: 'bg-brand-green/15 text-emerald-800 dark:text-emerald-200',
        text: 'text-emerald-800 dark:text-emerald-200',
    },
    // Assessment — navy primar: eveniment instituțional important.
    accent: {
        dot: 'bg-primary',
        rail: 'bg-primary',
        chip: 'bg-primary/10 text-primary',
        text: 'text-primary',
    },
    // Absence — destructive: stare problematică/semnal.
    danger: {
        dot: 'bg-destructive',
        rail: 'bg-destructive',
        chip: 'bg-destructive/10 text-destructive',
        text: 'text-destructive',
    },
    // Deadline — destructive (atenție/termen). Mapat la destructive pentru a păstra strict 4 familii brand
    // (auditul a cerut max ~4); pe rolul „urgență temporală", destructive funcționează ca semnal vizual.
    warning: {
        dot: 'bg-destructive/80',
        rail: 'bg-destructive/80',
        chip: 'bg-destructive/10 text-destructive',
        text: 'text-destructive',
    },
    // Event — navy primar: eveniment școlar general (ședințe, festivități).
    event: {
        dot: 'bg-primary',
        rail: 'bg-primary',
        chip: 'bg-primary/10 text-primary',
        text: 'text-primary',
    },
    // Schedule — muted: orar/rutină, fond informativ.
    neutral: {
        dot: 'bg-muted-foreground/60',
        rail: 'bg-muted-foreground/60',
        chip: 'bg-muted text-muted-foreground',
        text: 'text-muted-foreground',
    },
    // Structure — muted: structură an academic (ferme, vacanțe), informativ.
    muted: {
        dot: 'bg-muted-foreground/40',
        rail: 'bg-muted-foreground/40',
        chip: 'bg-muted text-muted-foreground',
        text: 'text-muted-foreground',
    },
    // Communication — verde brand: comunicări/anunțuri (acțiune relațională pozitivă).
    info: {
        dot: 'bg-brand-green',
        rail: 'bg-brand-green',
        chip: 'bg-brand-green/15 text-emerald-800 dark:text-emerald-200',
        text: 'text-emerald-800 dark:text-emerald-200',
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

    // Pe mobil (<md), grila lunară 7×6 e prea înghesuită — chip-urile cu `text-[10px]` devin ilizibile
    // la 360–390px. Deci pornim direct cu vederea „agendă", listă lizibilă pe o coloană.
    // Utilizatorul poate comuta înapoi la lună din toolbar dacă vrea explicit.
    const [view, setView] = useState<View>(() => {
        if (typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches) {
            return 'agenda';
        }

        return 'month';
    });
    // Semantică INVERSATĂ vs. versiunea veche `activeCats` (audit § calendar #2): pornim cu toate vizibile;
    // click pe un chip ASCUNDE acea categorie (model mental așteptat). Buton „Toate" resetează când !empty.
    const [hiddenCats, setHiddenCats] = useState<Set<string>>(new Set());
    // Indicator de încărcare pentru navigarea lunii / schimbarea copilului (audit § calendar #5): fără el,
    // evenimentele „dispar și reapar" silențios între request-uri Inertia.
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<CalendarEvent | null>(null);
    const [cursor, setCursor] = useState<Date>(() => {
        const [y, m] = month.split('-').map(Number);

        return today.getFullYear() === y && today.getMonth() === m - 1
            ? today
            : new Date(y, m - 1, 1);
    });

    const visibleEvents = useMemo(
        () => events.filter((e) => !hiddenCats.has(e.category)),
        [events, hiddenCats],
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
                    onStart: () => setLoading(true),
                    onFinish: () => setLoading(false),
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
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    function toggleCat(cat: string) {
        setHiddenCats((prev) => {
            const next = new Set(prev);

            if (next.has(cat)) {
                next.delete(cat);
            } else {
                next.add(cat);
            }

            return next;
        });
    }

    function showAllCats() {
        setHiddenCats(new Set());
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
                            <ChevronLeft className="size-4" aria-hidden="true" />
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
                            <ChevronRight className="size-4" aria-hidden="true" />
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

                {/* Vederea activă — wrapper cu aria-busy + dim subtil în timpul navigării request-ului. */}
                <div
                    aria-busy={loading}
                    className={`transition-opacity duration-150 ${loading ? 'pointer-events-none opacity-60' : ''}`}
                >
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
                </div>

                {/* Legendă / filtre — semantică inversată (vezi `hiddenCats`). Chip activ = vizibil, plin;
                    chip cu line-through = ascuns. „Toate" apare doar când ai ascuns ceva — reset rapid. */}
                {legend.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 border-t border-sidebar-border/70 pt-3 dark:border-sidebar-border">
                        <span className="text-xs font-medium text-muted-foreground">
                            {t('ccal.legend')}:
                        </span>
                        {legend.map(([cat, color]) => {
                            const isHidden = hiddenCats.has(cat);

                            return (
                                <button
                                    type="button"
                                    key={cat}
                                    onClick={() => toggleCat(cat)}
                                    aria-pressed={!isHidden}
                                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-all ${
                                        isHidden
                                            ? 'border-transparent text-muted-foreground line-through opacity-40 hover:opacity-60'
                                            : 'border-sidebar-border/70 dark:border-sidebar-border'
                                    }`}
                                >
                                    <span
                                        className={`size-2 rounded-full ${colorFor(color).dot}`}
                                    />
                                    {t(`ccal.cat_${cat}`)}
                                </button>
                            );
                        })}
                        {hiddenCats.size > 0 && (
                            <button
                                type="button"
                                onClick={showAllCats}
                                className="ml-1 inline-flex items-center rounded-full border border-primary/40 bg-primary/5 px-2.5 py-1 text-xs font-medium text-primary hover:bg-primary/10"
                            >
                                {t('ccal.show_all')}
                            </button>
                        )}
                    </div>
                )}
            </div>

            <EventDetail
                event={selected}
                localeTag={localeTag}
                open={selected !== null}
                onOpenChange={(o) => !o && setSelected(null)}
                openLabel={t('ccal.open')}
                closeLabel={t('ccal.close')}
                allDayLabel={t('ccal.all_day')}
                categoryLabel={selected !== null ? t(`ccal.cat_${selected.category}`) : ''}
            />
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
                        <div
                            role="button"
                            tabIndex={0}
                            key={i}
                            onClick={() => onDay(cell)}
                            onKeyDown={(ev) => {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    onDay(cell);
                                }
                            }}
                            className={`flex min-h-20 cursor-pointer flex-col rounded-md border bg-card p-1 text-left transition-colors hover:border-primary/40 ${
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
                                    <button
                                        type="button"
                                        key={e.id}
                                        onClick={(ev) => {
                                            ev.stopPropagation();
                                            onEvent(e);
                                        }}
                                        className={`flex items-center gap-1 truncate rounded px-1 py-0.5 text-left text-[10px] leading-tight ${colorFor(e.color).chip}`}
                                    >
                                        <span
                                            className={`size-1.5 shrink-0 rounded-full ${colorFor(e.color).dot}`}
                                        />
                                        <span className="truncate">
                                            {e.title}
                                        </span>
                                    </button>
                                ))}
                                {dayEvents.length > 3 && (
                                    // Audit § calendar #15 — afordanță vizibilă: era `<span>` mut. Acum buton
                                    // cu text-primary + underline pe hover; click stop-propagation → deschide ziua.
                                    <button
                                        type="button"
                                        onClick={(ev) => {
                                            ev.stopPropagation();
                                            onDay(cell);
                                        }}
                                        aria-label={`+${dayEvents.length - 3}`}
                                        className="self-start px-1 text-[10px] font-medium text-primary underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                    >
                                        +{dayEvents.length - 3}
                                    </button>
                                )}
                            </span>
                        </div>
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

/**
 * Detaliile unui eveniment afișate într-un Dialog Radix (audit § calendar #3): aduce automat suport
 * pentru Escape + focus-trap + focus-return + scroll-lock — toate cerute de pattern-ul ARIA dialog,
 * pe care implementarea hand-rolled anterioară nu le avea.
 */
function EventDetail({
    event,
    localeTag,
    open,
    onOpenChange,
    openLabel,
    closeLabel,
    allDayLabel,
    categoryLabel,
}: {
    event: CalendarEvent | null;
    localeTag: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    openLabel: string;
    closeLabel: string;
    allDayLabel: string;
    categoryLabel: string;
}) {
    if (event === null) {
        return null;
    }

    const c = colorFor(event.color);
    const dateLabel = capitalize(
        parseYmd(event.date).toLocaleDateString(localeTag, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="flex items-start gap-3">
                        <span className={`mt-1 size-3 shrink-0 rounded-full ${c.dot}`} aria-hidden="true" />
                        <div className="flex-1">
                            <p className={`text-xs font-medium ${c.text}`}>{categoryLabel}</p>
                            <DialogTitle className="text-lg font-semibold">{event.title}</DialogTitle>
                            <p className="mt-0.5 text-sm capitalize text-muted-foreground">
                                {dateLabel}
                                {event.startTime ? ` · ${event.startTime}` : ` · ${allDayLabel}`}
                            </p>
                        </div>
                    </div>
                </DialogHeader>
                <DialogFooter>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
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
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

Calendar.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'ccal.title', href: '#' },
    ],
};
