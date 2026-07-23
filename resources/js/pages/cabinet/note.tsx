import { Head } from '@inertiajs/react';
import { GradeBook, GradeEvolution } from '@/components/cabinet/catalog/gradebook-views';
import type { EvolutionData, GradeBookData } from '@/components/cabinet/catalog/gradebook-views';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    gradebook: GradeBookData | null;
    evolution: EvolutionData | null;
}

/**
 * Modulul „Note": catalogul semestrului (note pe discipline / cronologic, comutabile instant)
 * și evoluția rezultatelor (traseul multi-an + mediile semestriale).
 */
export default function GradesModulePage({ module, gradebook, evolution }: Props) {
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
                    { value: 'evolutie', label: t('cabinet.gb_sec_evolution') },
                ]}
            >
                {module.section === 'curente' && gradebook !== null && <GradeBook data={gradebook} />}
                {module.section === 'evolutie' && evolution !== null && <GradeEvolution evolution={evolution} />}
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
