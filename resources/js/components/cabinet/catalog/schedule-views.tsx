import { useState } from 'react';
import { localIso } from '@/components/cabinet/catalog/homework-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { useTranslations } from '@/lib/i18n';

/**
 * Vederile PARTAJATE ale orarului — folosite de modulul „Orar" din meniu ȘI de tabul
 * „Orar & teme" al fișei elevului. O singură implementare → același design peste tot.
 */

export interface TimetableCell {
    subject: string;
    teacher: string | null;
    room: string | null;
}

export interface TimetableData {
    days: { value: number; label: string; short: string }[];
    maxLesson: number;
    grid: Record<string, TimetableCell>;
}

/**
 * Orarul PUBLICAT al clasei (Schedule tip „orarul-lecțiilor") — tabel generic, exact forma
 * serverului: headers = ['', Luni…Vineri], rows[i][0] = eticheta lecției („Lecția 1 08.00 – 08.30"),
 * rows[i][zi] = disciplina.
 */
export interface LessonsSchedule {
    label: string;
    headers: string[];
    rows: string[][];
}

/**
 * Orarul SĂPTĂMÂNAL al clasei: orarul PUBLICAT (bogat — cu intervale orare) când există,
 * altfel orarul STRUCTURAT (grila pe lecții, cu săli) ca fallback. Aceeași regulă ca în tab:
 * publicatul e sursa mai bună și nu se dublează.
 */
export function WeeklySchedule({
    timetable,
    lessonsSchedule,
}: {
    timetable: TimetableData | null;
    lessonsSchedule: LessonsSchedule | null;
}) {
    const t = useTranslations();

    if (lessonsSchedule && lessonsSchedule.rows.length > 0) {
        return (
            <section>
                <SectionHeading title={lessonsSchedule.label || t('cabinet.timetable_title')} />
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        {lessonsSchedule.headers.length > 0 && (
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    {lessonsSchedule.headers.map((h, i) => (
                                        <th key={i} scope="col" className="px-3 py-2 font-medium whitespace-nowrap">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                        )}
                        <tbody>
                            {lessonsSchedule.rows.map((r, i) => (
                                <tr key={i} className="border-t align-top">
                                    {r.map((cell, j) =>
                                        j === 0 ? (
                                            <th key={j} scope="row" className="px-3 py-2 text-left text-xs font-semibold whitespace-nowrap text-muted-foreground">
                                                {cell}
                                            </th>
                                        ) : (
                                            <td key={j} className="px-3 py-2">
                                                {cell || <span className="text-muted-foreground/40">—</span>}
                                            </td>
                                        ),
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        );
    }

    return (
        <section>
            <SectionHeading title={t('cabinet.timetable_title')} />
            {timetable === null ? (
                <EmptyState title={t('cabinet.no_timetable')} />
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th scope="col" className="px-3 py-2 font-medium">{t('cabinet.timetable_lesson')}</th>
                                {timetable.days.map((d) => (
                                    <th key={d.value} scope="col" className="px-3 py-2 text-center font-medium" title={d.label}>
                                        <span className="hidden sm:inline">{d.label}</span>
                                        <span className="sm:hidden">{d.short}</span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {Array.from({ length: timetable.maxLesson }, (_, i) => i + 1).map((num) => (
                                <tr key={num} className="border-t">
                                    <th scope="row" className="px-3 py-2 text-left font-semibold text-muted-foreground">{num}</th>
                                    {timetable.days.map((d) => {
                                        const cell = timetable.grid[`${d.value}-${num}`];

                                        return (
                                            <td key={d.value} className="px-3 py-2 text-center align-top">
                                                {cell ? (
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">{cell.subject}</span>
                                                        {cell.teacher && <span className="text-xs text-muted-foreground">{cell.teacher}</span>}
                                                        {cell.room && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {t('cabinet.timetable_room')} {cell.room}
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground/40">—</span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

/**
 * „Ziua mea" — vederea zilnică a elevului: lecțiile zilei (orar publicat sau structurat)
 * și temele DE PREDAT în acea zi, cu navigare ◀ Azi ▶. Compoziție pur client-side din
 * prop-urile existente (orarul e săptămânal, temele poartă data efectivă).
 */
export function MyDay({
    timetable,
    lessonsSchedule,
    homework,
}: {
    timetable: TimetableData | null;
    lessonsSchedule: LessonsSchedule | null;
    homework: HomeworkItem[];
}) {
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

    // Lecțiile zilei — două surse, în ordinea BOGĂȚIEI: (1) orarul PUBLICAT al clasei (tabel
    // Luni–Vineri, cu intervalele orare în eticheta lecției); (2) orarul STRUCTURAT (per lecție,
    // cu sală, dar fără ore) — fallback când clasa nu are orar publicat.
    type DayLesson = { key: string; time: string; subject: string; room: string | null };

    let lessons: DayLesson[] = [];

    if (lessonsSchedule && lessonsSchedule.rows.length > 0 && weekday >= 1 && weekday < lessonsSchedule.headers.length) {
        lessons = lessonsSchedule.rows
            .map((row, i) => {
                const subject = (row[weekday] ?? '').trim();
                // „Lecția 1 08.00 – 08.30" → doar intervalul orar; fallback pe eticheta întreagă.
                const interval = /(\d{1,2}[.:]\d{2})\s*[–—-]\s*(\d{1,2}[.:]\d{2})/.exec(row[0] ?? '');

                return {
                    key: `p-${i}`,
                    time: interval ? `${interval[1]}–${interval[2]}` : row[0] || `${i + 1}.`,
                    subject,
                    room: null,
                };
            })
            .filter((l) => l.subject !== '');
    } else if (timetable) {
        lessons = Array.from({ length: timetable.maxLesson }, (_, i) => i + 1)
            .map((num) => ({ num, cell: timetable.grid[`${weekday}-${num}`] }))
            .filter((l) => l.cell)
            .map((l) => ({ key: `s-${l.num}`, time: `${l.num}.`, subject: l.cell!.subject, room: l.cell!.room }));
    }

    const dueToday = homework.filter((h) => h.effectiveDate === selectedIso);

    return (
        <section className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-base font-semibold">
                    {t('cabinet.my_day')}
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
                        aria-label={t('cabinet.my_day_prev')}
                        className="inline-flex size-8 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        ‹
                    </button>
                    <span className="min-w-40 text-center text-sm font-medium first-letter:uppercase">{dayLabel}</span>
                    <button
                        type="button"
                        onClick={() => shiftDay(1)}
                        aria-label={t('cabinet.my_day_next')}
                        className="inline-flex size-8 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        ›
                    </button>
                    {selectedIso !== todayIso && (
                        <button
                            type="button"
                            onClick={() => setSelectedIso(todayIso)}
                            className="ml-1 inline-flex h-8 items-center rounded-md border px-2.5 text-xs font-medium hover:bg-muted"
                        >
                            {t('cabinet.my_day_today')}
                        </button>
                    )}
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {/* Lecțiile zilei */}
                <div>
                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {t('cabinet.my_day_lessons')}
                    </p>
                    {lessons.length === 0 ? (
                        <p className="rounded-lg bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            {t('cabinet.my_day_no_lessons')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {lessons.map((l) => (
                                <li key={l.key} className="flex items-center gap-3 px-3 py-2 text-sm">
                                    <span className="w-20 shrink-0 text-xs text-muted-foreground">{l.time}</span>
                                    <span className="min-w-0 flex-1 truncate font-medium" title={l.subject}>
                                        {l.subject}
                                    </span>
                                    {l.room && (
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {t('cabinet.timetable_room')} {l.room}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Temele „pentru" ziua aleasă */}
                <div>
                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {t('cabinet.my_day_homework')}
                    </p>
                    {dueToday.length === 0 ? (
                        <p className="rounded-lg bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            {t('cabinet.my_day_no_homework')}
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
