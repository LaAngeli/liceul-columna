import { HomeworkByDay } from '@/components/cabinet/catalog/homework-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { MyDay, WeeklyScheduleView } from '@/components/cabinet/catalog/schedule-views';
import type { WeeklyData } from '@/components/cabinet/catalog/schedule-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';

/**
 * Tab „Orar & teme" — COMPUS din vederile partajate `catalog/schedule-views` + `catalog/homework-views`
 * (aceleași componente ca modulele „Orar"/„Teme" din meniu → design garantat identic).
 * `weekly` = payload-ul normalizat al serverului (publicat/structurat, o singură formă).
 */
export function ScheduleTab({
    weekly,
    homework,
}: {
    weekly?: WeeklyData | null;
    homework?: HomeworkItem[];
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            {/* === ZIUA MEA — programul + temele „pentru ziua" aleasă, într-o singură vedere === */}
            {((weekly && weekly.slots.length > 0) || (homework && homework.length > 0)) && (
                <MyDay weekly={weekly ?? null} homework={homework ?? []} />
            )}

            {/* === ORARUL SĂPTĂMÂNAL === (skeleton cât timp prop-ul defer e pe drum) */}
            {weekly === undefined ? (
                <section>
                    <SectionHeading title={t('cabinet.timetable_title')} />
                    <SkeletonTable rows={6} />
                </section>
            ) : (
                <WeeklyScheduleView weekly={weekly} />
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
