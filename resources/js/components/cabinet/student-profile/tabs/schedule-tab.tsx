import { HomeworkByDay } from '@/components/cabinet/catalog/homework-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { MyDay, WeeklySchedule } from '@/components/cabinet/catalog/schedule-views';
import type { LessonsSchedule, TimetableData } from '@/components/cabinet/catalog/schedule-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';

/**
 * Tab „Orar & teme" — COMPUS din vederile partajate `catalog/schedule-views` + `catalog/homework-views`
 * (aceleași componente ca modulele „Orar"/„Teme" din meniu → design garantat identic).
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
            {/* === ZIUA MEA — lecțiile + temele „pentru ziua" aleasă, într-o singură vedere === */}
            {(timetable || (lessonsSchedule && lessonsSchedule.rows.length > 0) || (homework && homework.length > 0)) && (
                <MyDay timetable={timetable ?? null} lessonsSchedule={lessonsSchedule ?? null} homework={homework ?? []} />
            )}

            {/* === ORARUL SĂPTĂMÂNAL === publicatul când există, altfel structuratul (fallback). */}
            {timetable === undefined && lessonsSchedule === undefined ? (
                <section>
                    <SectionHeading title={t('cabinet.timetable_title')} />
                    <SkeletonTable rows={6} />
                </section>
            ) : (
                <WeeklySchedule timetable={timetable ?? null} lessonsSchedule={lessonsSchedule ?? null} />
            )}

            {/* === TEME — organizate CRONOLOGIC pe zile (timpul e axa modulului). === */}
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
