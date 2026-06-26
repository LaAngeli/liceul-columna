import { Head, useForm } from '@inertiajs/react';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { update as settingsUpdate } from '@/routes/cabinet/notifications/settings';

interface Props {
    contacts: Record<string, string>;
    preferences: Record<string, string[]>;
    types: Record<string, string>;
    channels: Record<string, string>;
    email: string | null;
}

const CONTACT_CHANNELS = ['telegram', 'viber', 'messenger', 'whatsapp'] as const;

export default function NotificationSettingsPage({ contacts, preferences, types, channels, email }: Props) {
    const t = useTranslations();

    const form = useForm<{
        contacts: Record<string, string>;
        preferences: Record<string, string[]>;
    }>({
        contacts: {
            telegram: contacts.telegram ?? '',
            viber: contacts.viber ?? '',
            messenger: contacts.messenger ?? '',
            whatsapp: contacts.whatsapp ?? '',
        },
        preferences: { ...preferences },
    });

    function isOn(type: string, channel: string): boolean {
        return (form.data.preferences[type] ?? []).includes(channel);
    }

    function toggle(type: string, channel: string, checked: boolean) {
        const current = form.data.preferences[type] ?? [];
        const next = checked ? Array.from(new Set([...current, channel])) : current.filter((c) => c !== channel);
        form.setData('preferences', { ...form.data.preferences, [type]: next });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(settingsUpdate().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={t('cabinet.notif_settings')} />
            <form onSubmit={submit} className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{t('cabinet.notif_settings')}</h1>

                {/* Contacte */}
                <section className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                    <p className="text-sm font-medium">{t('cabinet.notif_contacts')}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{t('cabinet.notif_contacts_hint')}</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <label className="grid gap-1.5 text-xs text-muted-foreground">
                            {t('cabinet.notif_email_account')}
                            <input
                                type="text"
                                value={email ?? '—'}
                                disabled
                                className="rounded-md border border-input bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                            />
                        </label>
                        {CONTACT_CHANNELS.map((channel) => (
                            <label key={channel} className="grid gap-1.5 text-xs text-muted-foreground">
                                {channels[channel] ?? channel}
                                <input
                                    type="text"
                                    value={form.data.contacts[channel] ?? ''}
                                    onChange={(e) =>
                                        form.setData('contacts', { ...form.data.contacts, [channel]: e.target.value })
                                    }
                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    maxLength={120}
                                />
                            </label>
                        ))}
                    </div>
                </section>

                {/* Matrice tip × canal */}
                <section className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <p className="border-b border-sidebar-border/70 bg-muted/50 px-4 py-2 text-sm font-medium dark:border-sidebar-border">
                        {t('cabinet.notif_matrix')}
                    </p>
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th className="px-4 py-2 font-medium">{t('cabinet.notif_type')}</th>
                                {Object.entries(channels).map(([value, label]) => (
                                    <th key={value} className="px-3 py-2 text-center font-medium">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(types).map(([typeValue, typeLabel]) => (
                                <tr key={typeValue} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                    <td className="px-4 py-2 font-medium">{typeLabel}</td>
                                    {Object.keys(channels).map((channelValue) => (
                                        <td key={channelValue} className="px-3 py-2 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isOn(typeValue, channelValue)}
                                                onChange={(e) => toggle(typeValue, channelValue, e.target.checked)}
                                                className="size-4 rounded border-input"
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        {t('cabinet.notif_save')}
                    </button>
                    {form.recentlySuccessful && (
                        <span className="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                            {t('cabinet.notif_saved')}
                        </span>
                    )}
                </div>
            </form>
        </>
    );
}

NotificationSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Cabinet', href: dashboard() },
        { title: 'Setări notificări', href: '#' },
    ],
};
