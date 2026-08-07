import { Head } from '@inertiajs/react';
import { HomeworkBySubject } from '@/components/cabinet/catalog/homework-views';
import type { HomeworkItem } from '@/components/cabinet/catalog/homework-views';
import { ModuleShell } from '@/components/cabinet/catalog/module-shell';
import type { ModuleContext } from '@/components/cabinet/catalog/module-shell';
import { EmptyState } from '@/components/cabinet/empty-state';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';

interface Props {
    module: ModuleContext;
    homework: HomeworkItem[] | null;
}

/** Modulul „Teme": disciplinele clasei; în fiecare, toate temele ei, cea mai recentă prima. */
export default function HomeworkModulePage({ module, homework }: Props) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cabinet.nav_homework')} />
            <ModuleShell
                url="/cabinet/teme"
                title={t('cabinet.nav_homework')}
                hint={t('cabinet.catalog_homework_hint')}
                module={module}
            >
                {homework !== null &&
                    (homework.length === 0 ? (
                        <EmptyState title={t('cabinet.no_homework')} />
                    ) : (
                        <HomeworkBySubject homework={homework} />
                    ))}
            </ModuleShell>
        </>
    );
}

HomeworkModulePage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_homework', href: '#' },
    ],
};
