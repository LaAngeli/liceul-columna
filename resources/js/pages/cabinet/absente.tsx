import { Head } from '@inertiajs/react';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { AbsenceRegister, AbsenceTotals, MotivationsPanel } from '@/components/cabinet/catalog/situation-views';
import type { AbsenceRegisterData, MotivationItem, MotivationWindow } from '@/components/cabinet/catalog/situation-views';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    register: AbsenceRegisterData | null;
    motivations: MotivationItem[] | null;
    motivationWindow: MotivationWindow | null;
    canRequestMotivation: boolean;
}

/** Modulul „Absențe": registrul detaliat pe discipline · motivările familiei (formular + istoric). */
export default function AbsencesModulePage({ module, register, motivations, motivationWindow, canRequestMotivation }: Props) {
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
                {module.section === 'registru' && register !== null && (
                    <section>
                        <SectionHeading
                            title={t('cabinet.reg_title')}
                            actions={<AbsenceTotals motivated={register.motivated} unmotivated={register.unmotivated} />}
                        />
                        <AbsenceRegister register={register} />
                    </section>
                )}

                {module.section === 'motivari' && motivations !== null && module.currentId !== null && (
                    <MotivationsPanel
                        studentId={module.currentId}
                        motivations={motivations}
                        canRequest={canRequestMotivation}
                        window={motivationWindow}
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
