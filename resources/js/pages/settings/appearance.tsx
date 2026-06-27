import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useTranslations } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('settings.appearance_head', 'Setări de aspect')} />

            <h1 className="sr-only">{t('settings.appearance_head', 'Setări de aspect')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.appearance_head', 'Setări de aspect')}
                    description={t('settings.appearance_desc', 'Actualizează aspectul interfeței contului tău')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'settings.appearance_head',
            href: editAppearance(),
        },
    ],
};
