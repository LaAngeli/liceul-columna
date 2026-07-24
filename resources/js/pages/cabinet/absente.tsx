import { Head, router } from '@inertiajs/react';
import { AbsenceOverview } from '@/components/cabinet/catalog/absence-views';
import type { AbsenceOverviewData } from '@/components/cabinet/catalog/absence-views';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { MotivationsPanel } from '@/components/cabinet/catalog/situation-views';
import type { MotivationItem, MotivationWindow } from '@/components/cabinet/catalog/situation-views';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    overview: AbsenceOverviewData | null;
    motivations: MotivationItem[] | null;
    motivationWindow: MotivationWindow | null;
    canRequestMotivation: boolean;
}

/** Modulul „Absențe": situația pe semestru (sinteză + 3 vederi) · motivările familiei. */
export default function AbsencesModulePage({ module, overview, motivations, motivationWindow, canRequestMotivation }: Props) {
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
                {module.section === 'registru' && overview !== null && (
                    <AbsenceOverview
                        overview={overview}
                        // Alerta „termenul expiră" e singurul lucru rezolvabil din situație — butonul
                        // ei duce direct la formularul de motivare, nu doar la o altă filă.
                        onRequestMotivation={
                            canRequestMotivation
                                ? () =>
                                      router.get('/cabinet/absente', {
                                          copil: module.currentId ?? undefined,
                                          sectiune: 'motivari',
                                      })
                                : undefined
                        }
                    />
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
