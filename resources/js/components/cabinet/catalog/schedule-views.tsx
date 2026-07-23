import { CalendarDays, Columns3 } from 'lucide-react';
import { useState } from 'react';
import { localIso } from '@/components/cabinet/catalog/homework-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Vederile PARTAJATE ale orarului — modulul „Orar" din meniu ȘI tabul „Orar & teme" al fișei
 * elevului. Consumă payload-ul NORMALIZAT de pe server (App\Support\WeeklySchedule):
 * sloturi cu interval orar + celule deja segmentate (disciplină / profesor / grupă / sală) —
 * clientul doar randează, nu mai parsează text.
 */

export interface WeeklySegment {
    subject: string;
    teacher: string | null;
    group: string | null;
}

export interface WeeklyCell {
    segments: WeeklySegment[];
    room: string | null;
    raw: string;
}

export interface WeeklySlot {
    number: number | null;
    time: string | null;
    label: string;
    kind: 'lesson' | 'activity';
    /** Activitate identică pe toate zilele („Plimbări, jocuri") → o singură bandă (colspan). */
    uniform: WeeklyCell | null;
    cells: Record<number, WeeklyCell | null>;
}

export interface WeeklyData {
    source: 'published' | 'structured';
    label: string | null;
    days: { value: number; label: string; short: string }[];
    slots: WeeklySlot[];
}

/** Ziua ISO curentă (1 = luni … 7 = duminică). */
function todayIsoWeekday(): number {
    const dow = new Date().getDay();

    return dow === 0 ? 7 : dow;
}

/** Coloana de timp a unui slot: numărul lecției + intervalul orar (ce există din ele). */
function SlotTime({ slot, align = 'start' }: { slot: WeeklySlot; align?: 'start' | 'end' }) {
    return (
        <div className={cn('flex flex-col gap-0.5', align === 'end' ? 'items-end' : 'items-start')}>
            {slot.number !== null && (
                <span className="inline-flex size-5 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                    {slot.number}
                </span>
            )}
            {slot.time && <span className="text-[11px] whitespace-nowrap text-muted-foreground tabular-nums">{slot.time}</span>}
        </div>
    );
}

/** Conținutul unei celule: segmentele (disciplină / profesor / grupă) + sala — ierarhie vizuală clară. */
function CellContent({ cell, muted = false }: { cell: WeeklyCell; muted?: boolean }) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-1" title={cell.raw}>
            {/* Grupele paralele: separate printr-o linie orizontală (într-o coloană îngustă
                citește mai bine decât bara verticală). */}
            <div className={cn('flex flex-col', cell.segments.length > 1 && 'divide-y divide-border/70')}>
                {cell.segments.map((seg, i) => (
                    <div key={i} className={cn('min-w-0', cell.segments.length > 1 && (i === 0 ? 'pb-1' : 'pt-1'))}>
                        <div className={cn('text-[13px] leading-snug font-medium', muted && 'font-normal text-muted-foreground italic')}>
                            {seg.subject}
                            {seg.group && (
                                <span className="ml-1 rounded bg-primary/10 px-1 py-px align-middle text-[10px] font-semibold text-primary">
                                    {seg.group}
                                </span>
                            )}
                        </div>
                        {seg.teacher && <div className="truncate text-[11px] text-muted-foreground">{seg.teacher}</div>}
                    </div>
                ))}
            </div>
            {cell.room && (
                <span className="w-fit rounded bg-muted px-1 py-px text-[10px] text-muted-foreground">
                    {t('cabinet.sched_room')} {cell.room}
                </span>
            )}
        </div>
    );
}

/**
 * Vederea TABEL (desktop) — GRILĂ de casete, nu tabel clasic: `table-fixed` (coloane egale, textele
 * lungi se sparg în loc să lățească ziua) + `border-separate` cu spațiere, astfel încât FIECARE
 * celulă e propria casetă. Înălțimile se aliniază automat pe rând (celula ESTE cardul), deci un
 * slot cu două grupe nu mai dezechilibrează rândul — exact ce făcea grila veche să pară haotică.
 */
function WeekTable({ weekly }: { weekly: WeeklyData }) {
    const t = useTranslations();
    const today = todayIsoWeekday();

    return (
        <div className="overflow-x-auto">
            {/* min-w: sub ~52rem coloanele ar deveni ilizibile — pe mobil vederea implicită e „Pe zile". */}
            <table className="w-full min-w-[52rem] table-fixed border-separate border-spacing-1 text-sm">
                <colgroup>
                    <col className="w-20" />
                    {weekly.days.map((d) => (
                        <col key={d.value} />
                    ))}
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" className="px-1 pb-1 text-right text-[11px] font-normal text-muted-foreground">
                            {t('cabinet.timetable_lesson')}
                        </th>
                        {weekly.days.map((d) => (
                            <th
                                key={d.value}
                                scope="col"
                                className={cn(
                                    'rounded-lg px-2 py-1.5 text-center text-[13px] font-semibold',
                                    d.value === today ? 'bg-primary/10 text-primary' : 'bg-muted/60 text-muted-foreground',
                                )}
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    {d.label}
                                    {d.value === today && <span className="size-1.5 rounded-full bg-primary" aria-hidden />}
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {weekly.slots.map((slot, i) => (
                        <tr key={i}>
                            <th scope="row" className="px-1 py-1 text-right align-top">
                                <SlotTime slot={slot} align="end" />
                            </th>
                            {slot.uniform !== null ? (
                                <td
                                    colSpan={weekly.days.length}
                                    className="rounded-lg bg-muted/40 px-3 py-1.5 text-center text-[12px] text-muted-foreground italic"
                                    title={slot.uniform.raw}
                                >
                                    {slot.uniform.raw}
                                </td>
                            ) : (
                                weekly.days.map((d) => {
                                    const cell = slot.cells[d.value] ?? null;

                                    // Celula goală rămâne GOALĂ (fundal discret) — „—" adăuga zgomot
                                    // pe fiecare fereastră liberă din săptămână.
                                    if (cell === null) {
                                        return <td key={d.value} className="rounded-lg bg-muted/15" />;
                                    }

                                    return (
                                        <td
                                            key={d.value}
                                            className={cn(
                                                'rounded-lg border px-2.5 py-2 align-top',
                                                slot.kind === 'activity' ? 'border-transparent bg-muted/40' : 'border-border/60 bg-card',
                                                d.value === today && slot.kind !== 'activity' && 'border-primary/30 bg-primary/[0.04]',
                                            )}
                                        >
                                            <CellContent cell={cell} muted={slot.kind === 'activity'} />
                                        </td>
                                    );
                                })
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/** Vederea PE ZILE (mobil / opțional desktop): selector de zi + programul zilei ca listă. */
function DayCards({ weekly }: { weekly: WeeklyData }) {
    const t = useTranslations();
    const today = todayIsoWeekday();
    const defaultDay = weekly.days.some((d) => d.value === today) ? today : (weekly.days[0]?.value ?? 1);
    const [selected, setSelected] = useState(defaultDay);

    const daySlots = weekly.slots
        .map((slot) => ({ slot, cell: slot.uniform ?? slot.cells[selected] ?? null }))
        .filter((entry) => entry.cell !== null);

    return (
        <div className="flex flex-col gap-3">
            {/* Selectorul de zi — „azi" marcat cu punct; ziua activă plină. */}
            <div className="flex gap-1.5 overflow-x-auto pb-1" role="group" aria-label={t('cabinet.timetable_title')}>
                {weekly.days.map((d) => {
                    const active = d.value === selected;

                    return (
                        <button
                            key={d.value}
                            type="button"
                            onClick={() => setSelected(d.value)}
                            aria-pressed={active}
                            className={cn(
                                'inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors md:h-9 md:min-h-0',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <span className="sm:hidden">{d.short}</span>
                            <span className="hidden sm:inline">{d.label}</span>
                            {d.value === today && (
                                <span className={cn('size-1.5 rounded-full', active ? 'bg-primary-foreground' : 'bg-primary')} aria-hidden />
                            )}
                        </button>
                    );
                })}
            </div>

            {daySlots.length === 0 ? (
                <p className="rounded-xl border bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                    {t('cabinet.day_plan_no_lessons')}
                </p>
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                    {daySlots.map(({ slot, cell }, i) => (
                        <li key={i} className={cn('flex items-start gap-3 px-3.5 py-2.5', slot.kind === 'activity' && 'bg-muted/20')}>
                            <div className="w-20 shrink-0 pt-0.5">
                                <SlotTime slot={slot} />
                            </div>
                            <div className="min-w-0 flex-1">{cell && <CellContent cell={cell} muted={slot.kind === 'activity'} />}</div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Orarul SĂPTĂMÂNAL: pe mobil — carduri pe zile (fără scroll orizontal); pe desktop — tabel,
 * cu comutator Tabel / Pe zile (unii preferă lista zilei chiar și pe ecran mare).
 */
export function WeeklyScheduleView({ weekly }: { weekly: WeeklyData | null }) {
    const t = useTranslations();
    const [mode, setMode] = useState<'table' | 'days'>('table');

    if (weekly === null || weekly.slots.length === 0) {
        return (
            <section>
                <SectionHeading title={t('cabinet.timetable_title')} />
                <EmptyState title={t('cabinet.no_timetable')} />
            </section>
        );
    }

    const modes = [
        { value: 'table' as const, label: t('cabinet.sched_view_table'), icon: Columns3 },
        { value: 'days' as const, label: t('cabinet.sched_view_days'), icon: CalendarDays },
    ];

    return (
        <section>
            <SectionHeading
                title={weekly.label || t('cabinet.timetable_title')}
                actions={
                    /* Comutatorul de vedere — doar pe desktop; sub lg vederea e mereu „pe zile". */
                    <div className="hidden rounded-lg border p-0.5 lg:flex" role="group" aria-label={t('cabinet.timetable_title')}>
                        {modes.map((m) => (
                            <button
                                key={m.value}
                                type="button"
                                onClick={() => setMode(m.value)}
                                aria-pressed={mode === m.value}
                                className={cn(
                                    'inline-flex h-7 cursor-pointer items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors',
                                    mode === m.value ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <m.icon className="size-3.5" aria-hidden />
                                {m.label}
                            </button>
                        ))}
                    </div>
                }
            />

            <div className="lg:hidden">
                <DayCards weekly={weekly} />
            </div>
            <div className="hidden lg:block">{mode === 'table' ? <WeekTable weekly={weekly} /> : <DayCards weekly={weekly} />}</div>
        </section>
    );
}

/**
 * „Ziua mea" — vederea zilnică a elevului: programul zilei (sloturile parse-ate ale serverului)
 * și temele DE PREDAT în acea zi, cu navigare ◀ Azi ▶.
 */
export function DayPlan({ weekly, homework }: { weekly: WeeklyData | null; homework: HomeworkItem[] }) {
    const t = useTranslations();
    const todayIso = localIso(new Date());
    const [selectedIso, setSelectedIso] = useState(todayIso);

    const selected = new Date(`${selectedIso}T12:00:00`);
    // JS: 0=duminică … 6=sâmbătă → orarul folosește 1=luni … 7=duminică.
    const weekday = selected.getDay() === 0 ? 7 : selected.getDay();

    function shiftDay(step: number) {
        const next = new Date(selected);
        next.setDate(next.getDate() + step);
        setSelectedIso(localIso(next));
    }

    // Eticheta zilei în limba interfeței — Intl e nativ, fără librării.
    const lang = document.documentElement.lang || 'ro';
    const dayLabel = new Intl.DateTimeFormat(lang, { weekday: 'long', day: 'numeric', month: 'long' }).format(selected);

    const daySlots = (weekly?.slots ?? [])
        .map((slot) => ({ slot, cell: slot.uniform ?? slot.cells[weekday] ?? null }))
        .filter((entry) => entry.cell !== null);

    const dueToday = homework.filter((h) => h.effectiveDate === selectedIso);

    return (
        <section className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-base font-semibold">
                    {t('cabinet.day_plan')}
                    {selectedIso === todayIso && (
                        <span className="rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary">
                            {t('cabinet.homework_today')}
                        </span>
                    )}
                </h3>
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => shiftDay(-1)}
                        aria-label={t('cabinet.day_plan_prev')}
                        className="inline-flex size-11 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted hover:text-foreground md:size-8"
                    >
                        ‹
                    </button>
                    <span className="min-w-40 text-center text-sm font-medium first-letter:uppercase">{dayLabel}</span>
                    <button
                        type="button"
                        onClick={() => shiftDay(1)}
                        aria-label={t('cabinet.day_plan_next')}
                        className="inline-flex size-11 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted hover:text-foreground md:size-8"
                    >
                        ›
                    </button>
                    {selectedIso !== todayIso && (
                        <button
                            type="button"
                            onClick={() => setSelectedIso(todayIso)}
                            className="ml-1 inline-flex h-8 items-center rounded-md border px-2.5 text-xs font-medium hover:bg-muted"
                        >
                            {t('cabinet.day_plan_today')}
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {/* Programul zilei */}
                <div>
                    <p className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t('cabinet.day_plan_lessons')}
                    </p>
                    {daySlots.length === 0 ? (
                        <p className="rounded-lg bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            {t('cabinet.day_plan_no_lessons')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {daySlots.map(({ slot, cell }, i) => (
                                <li
                                    key={i}
                                    className={cn('flex items-start gap-3 px-3 py-2 text-sm', slot.kind === 'activity' && 'bg-muted/20')}
                                >
                                    <span className="w-20 shrink-0 pt-0.5 text-xs text-muted-foreground tabular-nums">
                                        {slot.time ?? (slot.number !== null ? `${slot.number}.` : '')}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        {cell && <CellContent cell={cell} muted={slot.kind === 'activity'} />}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Temele „pentru" ziua aleasă */}
                <div>
                    <p className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t('cabinet.day_plan_homework')}
                    </p>
                    {dueToday.length === 0 ? (
                        <p className="rounded-lg bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            {t('cabinet.day_plan_no_homework')}
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {dueToday.map((h) => (
                                <li key={h.id} className="rounded-lg border px-3 py-2 text-sm">
                                    <span className="mr-2 rounded bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary">
                                        {h.subject}
                                    </span>
                                    <span className="font-medium">{h.topic ?? h.required ?? ''}</span>
                                    {h.topic && h.required && (
                                        <p className="mt-0.5 text-xs text-muted-foreground">{h.required}</p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </section>
    );
}
