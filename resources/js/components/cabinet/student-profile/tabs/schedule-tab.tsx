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
    date: string;
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

            {/* === TEME === */}
            <section>
                <SectionHeading title={t('cabinet.homework')} />
                {homework === undefined ? (
                    <SkeletonTable rows={3} />
                ) : homework.length === 0 ? (
                    <EmptyState title={t('cabinet.no_homework')} />
                ) : (
                    <div className="flex flex-col gap-3">
                        {homework.map((h) => (
                            <article key={h.id} className="rounded-xl border bg-card p-4 shadow-sm">
                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                                    <span className="text-xs text-muted-foreground">{h.date}</span>
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
                                {h.links.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {h.links.map((link, i) =>
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
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}
