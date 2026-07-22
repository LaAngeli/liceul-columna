import { Head } from '@inertiajs/react';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { AbsenceTotals, AbsencesBySubjectGrid, MotivationsPanel } from '@/components/cabinet/catalog/situation-views';
import type { MotivationItem } from '@/components/cabinet/catalog/situation-views';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    absencesBySubject: { subject: string; count: number }[] | null;
    absencesMotivated: number;
    absencesUnmotivated: number;
    motivations: MotivationItem[] | null;
    canRequestMotivation: boolean;
}

/** Modulul „Absențe": registrul pe discipline · motivările familiei (formular + istoric). */
export default function AbsencesModulePage({
    module,
    absencesBySubject,
    absencesMotivated,
    absencesUnmotivated,
    motivations,
    canRequestMotivation,
}: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cabinet.nav_absences')} />
            <ModuleShell
                url="/cabinet/absente"
                title={t('cabinet.nav_absences')}
                hint={t('cabinet.catalog_absences_hint')}
                module={module}
                sections={[
                    { value: 'registru', label: t('cabinet.catalog_sec_register') },
                    { value: 'motivari', label: t('cabinet.catalog_sec_motivations') },
                ]}
            >
                {module.section === 'registru' && absencesBySubject !== null && (
                    <section>
                        <SectionHeading
                            title={t('cabinet.absences_by_subject')}
                            actions={<AbsenceTotals motivated={absencesMotivated} unmotivated={absencesUnmotivated} />}
                        />
                        <AbsencesBySubjectGrid absences={absencesBySubject} />
                    </section>
                )}

                {module.section === 'motivari' && motivations !== null && module.currentId !== null && (
                    <MotivationsPanel
                        studentId={module.currentId}
                        motivations={motivations}
                        canRequest={canRequestMotivation}
                    />
                )}
            </ModuleShell>
        </>
    );
}

AbsencesModulePage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_absences', href: '#' },
    ],
};
