import { Head, Link, router } from '@inertiajs/react';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { read, readAll, settings as notificationSettings } from '@/routes/cabinet/notifications';

interface NotificationItem {
    id: string;
    type: string | null;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    at: string | null;
}

interface Props {
    notifications: NotificationItem[];
}

export default function NotificationsPage({ notifications }: Props) {
    const t = useTranslations();
    const hasUnread = notifications.some((n) => !n.read);

    function markRead(id: string) {
        router.post(read(id).url, {}, { preserveScroll: true, preserveState: true });
    }

    function markAll() {
        router.post(readAll().url, {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <>
            <Head title={t('cabinet.notif_title')} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-semibold">{t('cabinet.notif_title')}</h1>
                    <Link
                        href={notificationSettings().url}
                        className="ml-auto inline-flex items-center rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        {t('cabinet.notif_settings')}
                    </Link>
                    {hasUnread && (
                        <button
                            type="button"
                            onClick={markAll}
                            className="inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            {t('cabinet.notif_mark_all')}
                        </button>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 px-4 py-10 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        {t('cabinet.notif_empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {notifications.map((n) => (
                            <li
                                key={n.id}
                                onClick={() => !n.read && markRead(n.id)}
                                className={`rounded-xl border px-4 py-3 ${
                                    n.read
                                        ? 'border-sidebar-border/70 bg-card dark:border-sidebar-border'
                                        : 'cursor-pointer border-primary/30 bg-primary/5'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 font-medium">
                                            {!n.read && <span className="size-2 shrink-0 rounded-full bg-primary" />}
                                            <span className="truncate">{n.title}</span>
                                        </p>
                                        {n.body && <p className="mt-0.5 text-sm text-muted-foreground">{n.body}</p>}
                                    </div>
                                    <span className="shrink-0 text-xs text-muted-foreground">{n.at}</span>
                                </div>
                                {n.url && (
                                    <Link
                                        href={n.url}
                                        className="mt-1.5 inline-block text-xs font-medium text-primary hover:underline"
                                    >
                                        {t('cabinet.notif_open')}
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

NotificationsPage.layout = {
    breadcrumbs: [
        { title: 'Cabinet', href: dashboard() },
        { title: 'Notificări', href: '#' },
    ],
};
