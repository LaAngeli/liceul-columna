import { Head } from '@inertiajs/react';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { GradesTable, SemesterAveragesTable } from '@/components/cabinet/catalog/situation-views';
import type { SemesterAveragesMatrix, SubjectGrades } from '@/components/cabinet/catalog/situation-views';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    subjects: SubjectGrades[] | null;
    averages: SemesterAveragesMatrix | null;
}

/** Modulul „Note": situația curentă (note + MS pe discipline) · mediile semestriale ale anului. */
export default function GradesModulePage({ module, subjects, averages }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cabinet.nav_grades')} />
            <ModuleShell
                url="/cabinet/note"
                title={t('cabinet.nav_grades')}
                hint={t('cabinet.catalog_grades_hint')}
                module={module}
                sections={[
                    { value: 'curente', label: t('cabinet.catalog_sec_current') },
                    { value: 'medii', label: t('cabinet.catalog_sec_averages') },
                ]}
            >
                {module.section === 'curente' && subjects !== null && <GradesTable subjects={subjects} />}
                {module.section === 'medii' && averages !== null && <SemesterAveragesTable averages={averages} />}
            </ModuleShell>
        </>
    );
}

GradesModulePage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_grades', href: '#' },
    ],
};
