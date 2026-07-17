import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';

interface TimetableCell {
    subject: string;
    teacher: string | null;
    room: string | null;
}

interface TimetableData {
    days: { value: number; label: string; short: string }[];
    maxLesson: number;
    grid: Record<string, TimetableCell>;
}

interface LessonsScheduleRow {
    lesson: number;
    start: string | null;
    end: string | null;
}

interface LessonsSchedule {
    rows: LessonsScheduleRow[];
}

interface HomeworkItem {
    id: number;
    /** Data atribuirii (d.m.Y). */
    date: string;
    /** Termenul de predare (d.m.Y) — null la temele legacy. */
    due: string | null;
    /** Cheia zilei efective (Y-m-d) — gruparea cronologică. */
    effectiveDate: string;
    /** Eticheta zilei, tradusă pe server („Vineri, 18 iulie"). */
    dayLabel: string;
    status: 'today' | 'upcoming' | 'past';
    subject: string;
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
}

/**
 * Tab „Orar & teme" — orar structurat al clasei + tabelul orelor („lecții" cu interval orar) + teme curente.
 */
export function ScheduleTab({
    timetable,
    lessonsSchedule,
    homework,
}: {
    timetable?: TimetableData | null;
    lessonsSchedule?: LessonsSchedule | null;
    homework?: HomeworkItem[];
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            {/* === ORAR STRUCTURAT === */}
            <section>
                <SectionHeading title={t('cabinet.timetable_title')} />
                {timetable === undefined ? (
                    <SkeletonTable rows={6} />
                ) : timetable === null ? (
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

            {/* === ORARUL LECȚIILOR (interval orar) === */}
            {lessonsSchedule && lessonsSchedule.rows.length > 0 && (
                <section>
                    <SectionHeading title={t('cabinet.timetable_lesson')} description={t('cabinet.timetable_room')} />
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-3 py-2 font-medium">{t('cabinet.timetable_lesson')}</th>
                                    <th scope="col" className="px-3 py-2 font-medium">{t('cabinet.motivation_from')}</th>
                                    <th scope="col" className="px-3 py-2 font-medium">{t('cabinet.motivation_to')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lessonsSchedule.rows.map((r) => (
                                    <tr key={r.lesson} className="border-t">
                                        <th scope="row" className="px-3 py-2 text-left font-semibold">{r.lesson}</th>
                                        <td className="px-3 py-2">{r.start ?? '—'}</td>
                                        <td className="px-3 py-2">{r.end ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {/* === TEME — organizate CRONOLOGIC pe zile (timpul e axa modulului): întâi ce
                 urmează (Astăzi / Mâine / zilele viitoare), apoi istoricul, pliat. === */}
            <section>
                <SectionHeading title={t('cabinet.homework')} />
                {homework === undefined ? (
                    <SkeletonTable rows={3} />
                ) : homework.length === 0 ? (
                    <EmptyState title={t('cabinet.no_homework')} />
                ) : (
                    <HomeworkByDay homework={homework} />
                )}
            </section>
        </div>
    );
}

/** Temele grupate pe ZILE după data efectivă (termen ?? atribuire); istoricul stă pliat. */
function HomeworkByDay({ homework }: { homework: HomeworkItem[] }) {
    const t = useTranslations();

    const upcoming = homework.filter((h) => h.status !== 'past');
    const past = homework.filter((h) => h.status === 'past');

    // „Mâine" se decide pe client (doar etichetă; gruparea folosește cheia serverului).
    // Data LOCALĂ, nu toISOString (UTC) — noaptea, UTC-ul ar aluneca cu o zi.
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowIso = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;

    const days: { key: string; label: string; isToday: boolean; items: HomeworkItem[] }[] = [];
    for (const h of upcoming) {
        const last = days[days.length - 1];
        if (last && last.key === h.effectiveDate) {
            last.items.push(h);
            continue;
        }
        days.push({
            key: h.effectiveDate,
            label:
                h.status === 'today'
                    ? t('cabinet.homework_today')
                    : h.effectiveDate === tomorrowIso
                      ? t('cabinet.homework_tomorrow')
                      : h.dayLabel,
            isToday: h.status === 'today',
            items: [h],
        });
    }

    return (
        <div className="flex flex-col gap-4">
            {days.length === 0 && (
                <p className="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    {t('cabinet.homework_none_upcoming')}
                </p>
            )}
            {days.map((day) => (
                <div key={day.key}>
                    <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                        {day.label}
                        {day.isToday && (
                            <span className="rounded-md bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                {t('cabinet.homework_due_badge')}
                            </span>
                        )}
                    </h4>
                    <div className="flex flex-col gap-3">
                        {day.items.map((h) => (
                            <HomeworkCard key={h.id} h={h} />
                        ))}
                    </div>
                </div>
            ))}

            {past.length > 0 && (
                <details className="rounded-xl border bg-muted/20 px-4 py-3">
                    <summary className="text-sm font-medium text-muted-foreground">
                        {t('cabinet.homework_past')} ({past.length})
                    </summary>
                    <div className="mt-3 flex flex-col gap-3">
                        {past.map((h) => (
                            <HomeworkCard key={h.id} h={h} muted />
                        ))}
                    </div>
                </details>
            )}
        </div>
    );
}

function HomeworkCard({ h, muted = false }: { h: HomeworkItem; muted?: boolean }) {
    const t = useTranslations();

    return (
        <article className={`rounded-xl border bg-card p-4 shadow-sm ${muted ? 'opacity-80' : ''}`}>
            <div className="mb-1 flex flex-wrap items-center gap-2">
                <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                {/* Termenul e informația principală; data atribuirii rămâne context secundar. */}
                {h.due && (
                    <span className="text-xs text-muted-foreground">
                        {t('cabinet.homework_due_on')} <span className="font-medium text-foreground">{h.due}</span>
                    </span>
                )}
                <span className="text-xs text-muted-foreground">
                    {t('cabinet.homework_assigned_on')} {h.date}
                </span>
            </div>
            {h.topic && <p className="font-medium">{h.topic}</p>}
            {h.required && (
                <p className="mt-1 text-sm">
                    <span className="text-muted-foreground">{t('cabinet.required')} </span>
                    {h.required}
                </p>
            )}
            {h.optional && (
                <p className="mt-1 text-sm">
                    <span className="text-muted-foreground">{t('cabinet.optional')} </span>
                    {h.optional}
                </p>
            )}
            {h.links.filter(Boolean).length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {h.links.filter(Boolean).map((link, i) =>
                        isUrl(link) ? (
                            <a
                                key={i}
                                href={link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="rounded-md bg-muted px-2 py-0.5 text-xs text-primary underline-offset-2 hover:underline"
                            >
                                {t('cabinet.link')} {i + 1}
                            </a>
                        ) : (
                            <span key={i} className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                {link}
                            </span>
                        ),
                    )}
                </div>
            )}
        </article>
    );
}
