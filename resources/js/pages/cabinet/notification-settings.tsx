import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { update as settingsUpdate } from '@/routes/cabinet/notifications/settings';

interface Props {
    contacts: Record<string, string>;
    preferences: Record<string, string[]>;
    types: Record<string, string>;
    channels: Record<string, string>;
    // E activat de liceu fiecare canal? (cabinet/email mereu da; sociale după token din .env)
    channelStatus: Record<string, boolean>;
    // Adresa curentă a contului (null dacă utilizatorul se loghează cu username).
    email: string | null;
    // Emailul de login se poate SETA din cabinet doar prima dată (email gol); odată setat, schimbarea
    // trece prin secretariat — e identificatorul de conectare + destinația OTP 2FA (#37).
    emailEditable: boolean;
    locale: string;
    locales: Record<string, string>;
}

// Telegram/Viber au driver — dacă liceul activează token-ul în .env, devin funcționale automat.
const CONTACT_CHANNELS = ['telegram', 'viber'] as const;

export default function NotificationSettingsPage({ contacts, preferences, types, channels, channelStatus, email, emailEditable, locale, locales }: Props) {
    const t = useTranslations();

    const form = useForm<{
        notification_locale: string;
        email: string;
        contacts: Record<string, string>;
        preferences: Record<string, string[]>;
    }>({
        notification_locale: locale,
        email: email ?? '',
        contacts: {
            telegram: contacts.telegram ?? '',
            viber: contacts.viber ?? '',
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

                {/* Limba notificărilor */}
                <Card>
                    <CardContent>
                        <p className="text-sm font-medium">{t('cabinet.notif_language')}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">{t('cabinet.notif_language_hint')}</p>
                        <select
                            value={form.data.notification_locale}
                            onChange={(e) => form.setData('notification_locale', e.target.value)}
                            className="mt-3 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                        >
                            {Object.entries(locales).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </CardContent>
                </Card>

                {/* Contacte */}
                <Card>
                    <CardContent>
                        <p className="text-sm font-medium">{t('cabinet.notif_contacts')}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">{t('cabinet.notif_contacts_hint')}</p>
                        {/* Rând-label și rând-hint cu min-h fix pe toate câmpurile → input-urile sunt aliniate
                            vertical INDIFERENT dacă un canal are/nu are badge „NEACTIVAT" sau text explicativ.
                            (Grid `gap-1.5` + `grid-rows-[auto_auto_auto]` implicit — cele 3 rânduri sunt aliniate
                            între toate coloanele.) */}
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1.5 text-xs text-muted-foreground">
                                <span className="flex min-h-5 items-center gap-2">
                                    {t('cabinet.notif_email_account')}
                                </span>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder={t('cabinet.notif_email_add_placeholder')}
                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm read-only:cursor-not-allowed read-only:opacity-60"
                                    maxLength={255}
                                    autoComplete="email"
                                    readOnly={!emailEditable}
                                    aria-readonly={!emailEditable}
                                />
                                <span className="min-h-4 text-[11px] text-muted-foreground/80">
                                    {emailEditable ? t('cabinet.notif_email_add_hint') : t('cabinet.notif_email_locked_hint')}
                                </span>
                                {form.errors.email && (
                                    <span className="text-[11px] font-medium text-destructive" role="alert">
                                        {form.errors.email}
                                    </span>
                                )}
                            </label>
                            {CONTACT_CHANNELS.map((channel) => {
                                const configured = channelStatus[channel] ?? true;

                                return (
                                    <label key={channel} className="grid gap-1.5 text-xs text-muted-foreground">
                                        <span className="flex min-h-5 items-center gap-2">
                                            {channels[channel] ?? channel}
                                            {!configured && (
                                                <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                                    {t('cabinet.notif_channel_unconfigured_badge')}
                                                </span>
                                            )}
                                        </span>
                                        <input
                                            type="text"
                                            value={form.data.contacts[channel] ?? ''}
                                            onChange={(e) =>
                                                form.setData('contacts', { ...form.data.contacts, [channel]: e.target.value })
                                            }
                                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            maxLength={120}
                                        />
                                        <span className="min-h-4 text-[11px] text-muted-foreground/80">
                                            {!configured ? t('cabinet.notif_channel_unconfigured') : ' '}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Matrice tip × canal — accesibilă (scope row/col, label cu țintă tactilă ≥44px, aria-label tip · canal). */}
                <section className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="border-b border-sidebar-border/70 bg-muted/50 px-4 py-2 dark:border-sidebar-border">
                        <p className="text-sm font-medium">{t('cabinet.notif_matrix')}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">{t('cabinet.notif_matrix_hint')}</p>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th scope="col" className="px-2.5 py-2 font-medium sm:px-4">{t('cabinet.notif_type')}</th>
                                {Object.entries(channels).map(([value, label]) => {
                                    const configured = channelStatus[value] ?? true;

                                    return (
                                        <th
                                            key={value}
                                            scope="col"
                                            className={`px-1.5 py-2 text-center font-medium sm:px-3 ${configured ? '' : 'text-muted-foreground/70'}`}
                                            title={configured ? undefined : t('cabinet.notif_channel_unconfigured')}
                                        >
                                            {label}
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody>
                            {Object.entries(types).map(([typeValue, typeLabel]) => (
                                <tr key={typeValue} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                    <th scope="row" className="px-2.5 py-2 text-left font-medium sm:px-4">{typeLabel}</th>
                                    {Object.entries(channels).map(([channelValue, channelLabel]) => {
                                        const configured = channelStatus[channelValue] ?? true;

                                        return (
                                            <td key={channelValue} className="p-0 text-center">
                                                <label
                                                    className={`flex h-11 w-full items-center justify-center ${configured ? 'cursor-pointer hover:bg-muted/40' : 'cursor-not-allowed opacity-50'}`}
                                                    title={configured ? undefined : t('cabinet.notif_channel_unconfigured')}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={isOn(typeValue, channelValue)}
                                                        onChange={(e) => toggle(typeValue, channelValue, e.target.checked)}
                                                        aria-label={`${typeLabel} · ${channelLabel}`}
                                                        disabled={!configured}
                                                        className="size-5 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed"
                                                    />
                                                </label>
                                            </td>
                                        );
                                    })}
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
                        <span className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
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
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.notif_settings', href: '#' },
    ],
};
