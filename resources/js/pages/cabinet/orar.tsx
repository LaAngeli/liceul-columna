import { Head } from '@inertiajs/react';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { MyDay, WeeklySchedule } from '@/components/cabinet/catalog/schedule-views';
import type { LessonsSchedule, TimetableData } from '@/components/cabinet/catalog/schedule-views';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    timetable: TimetableData | null;
    lessonsSchedule: LessonsSchedule | null;
    /** Doar în secțiunea „Ziua mea" (planificatorul fuzionează lecțiile cu temele zilei). */
    homework: HomeworkItem[] | null;
}

/** Modulul „Orar": „Ziua mea" (lecțiile + temele zilei) · orarul săptămânal al clasei. */
export default function ScheduleModulePage({ module, timetable, lessonsSchedule, homework }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cabinet.nav_schedule')} />
            <ModuleShell
                url="/cabinet/orar"
                title={t('cabinet.nav_schedule')}
                hint={t('cabinet.catalog_schedule_hint')}
                module={module}
                sections={[
                    { value: 'zi', label: t('cabinet.my_day') },
                    { value: 'saptamana', label: t('cabinet.catalog_sec_week') },
                ]}
            >
                {module.section === 'zi' && (
                    <MyDay timetable={timetable} lessonsSchedule={lessonsSchedule} homework={homework ?? []} />
                )}
                {module.section === 'saptamana' && (
                    <WeeklySchedule timetable={timetable} lessonsSchedule={lessonsSchedule} />
                )}
            </ModuleShell>
        </>
    );
}

ScheduleModulePage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_schedule', href: '#' },
    ],
};
