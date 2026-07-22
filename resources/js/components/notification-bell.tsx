import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { notifications } from '@/routes/cabinet';

/**
 * Clopoțelul de notificări cu badge-ul de necitite — sursă unică pentru navbar (mobil: stânga,
 * desktop: dreapta) și pentru meniul mobil deschis, ca numărul și comportamentul să fie identice
 * oriunde apare. Țintă tactilă 44px pe mobil, compact (36px) pe desktop.
 */
export function NotificationBell({ className }: { className?: string }) {
    const t = useTranslations();
    const unread = Number(usePage().props.notificationsUnread ?? 0);

    return (
        <Link
            href={notifications.url()}
            aria-label={t('cabinet.notif_title')}
            title={t('cabinet.notif_title')}
            className={cn(
                'relative inline-flex size-11 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground md:size-9',
                className,
            )}
        >
            <Bell className="size-[18px]" />
            {unread > 0 && (
                <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white md:-top-0.5 md:-right-0.5">
                    {unread > 99 ? '99+' : unread}
                </span>
            )}
        </Link>
    );
}
