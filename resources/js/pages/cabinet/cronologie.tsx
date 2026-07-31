import { Head } from '@inertiajs/react';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { ActivityTimeline } from '@/components/cabinet/catalog/timeline-views';
import type { ActivityTimelineData } from '@/components/cabinet/catalog/timeline-views';
import { usePollReload } from '@/hooks/use-poll-reload';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

/** La fiecare minut ce s-a introdus proaspăt în catalog apare pe fir, fără reîncărcare manuală. */
const TIMELINE_POLL_MS = 60_000;

interface Props {
    module: ModuleContext;
    timeline: ActivityTimelineData | null;
}

/** Modulul „Cronologie": notele și absențele împreună, în ordinea producerii — firul zilelor. */
export default function TimelineModulePage({ module, timeline }: Props) {
    const t = useTranslations();

    // Firul se reîmprospătează singur (polling), dar doar când chiar are ce reîncărca — fără copil
    // selectat (familie fără elev asociat) nu pornim niciun interval.
    usePollReload('timeline', timeline !== null ? TIMELINE_POLL_MS : 0);

    return (
        <>
            <Head title={t('cabinet.nav_timeline')} />
            <ModuleShell
                url="/cabinet/cronologie"
                title={t('cabinet.nav_timeline')}
                hint={t('cabinet.catalog_timeline_hint')}
                module={module}
            >
                {timeline !== null && <ActivityTimeline timeline={timeline} />}
            </ModuleShell>
        </>
    );
}

TimelineModulePage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_timeline', href: '#' },
    ],
};
