import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CalendarDays, GraduationCap, MessageSquare, Sparkles } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { StandingCard } from '@/components/cabinet/standing-card';
import type { CockpitChild } from '@/components/cabinet/standing-card';
import { pluralKey, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

interface CockpitAlerts {
    unread_messages: number;
    unread_notifications: number;
    at_risk: number;
    at_risk_student_id: number | null;
}

interface DashboardProps {
    cabinet: {
        children: CockpitChild[];
        self: CockpitChild | null;
        alerts: CockpitAlerts;
    };
}

/** Micro-label structural (eyebrow) — dispozitivul unic de secționare al cockpitului. */
function Eyebrow({ children }: { children: React.ReactNode }) {
    return <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{children}</p>;
}

/**
 * Pastilă de atenție — reper inline, scanabil (nu card-număr uriaș). Dacă are `href`, întreaga
 * pastilă e un Link Inertia (a11y). Cele trei tonuri urmează paleta de brand: navy (info),
 * destructive (risc), verde (totul în regulă).
 */
function AttentionPill({
    icon: Icon,
    count,
    label,
    tone,
    href,
}: {
    icon: LucideIcon;
    count?: number;
    label: string;
    tone: 'info' | 'danger' | 'ok';
    href?: string;
}) {
    const toneClass = {
        info: 'border-primary/25 bg-primary/5 text-primary hover:bg-primary/10',
        danger: 'border-destructive/30 bg-destructive/10 text-destructive hover:bg-destructive/15',
        ok: 'border-brand-green/30 bg-brand-green/10 text-foreground',
    }[tone];

    const iconClass = { info: 'text-primary', danger: 'text-destructive', ok: 'text-brand-green' }[tone];

    const inner = (
        <>
            <Icon className={cn('size-4 shrink-0', iconClass)} aria-hidden="true" />
            {count !== undefined && <span className="font-bold tabular-nums">{count}</span>}
            <span className="font-medium">{label}</span>
        </>
    );

    const className = cn(
        // `min-h-11` pe mobil: pastilele sunt linkuri, deci au nevoie de 44px de țintă tactilă.
        'inline-flex min-h-11 items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors md:min-h-0',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        toneClass,
    );

    if (href) {
        return (
            <Link href={href} className={className} aria-label={count !== undefined ? `${count} ${label}` : label}>
                {inner}
            </Link>
        );
    }

    return <div className={className}>{inner}</div>;
}

function AttentionRail({ alerts }: { alerts: CockpitAlerts }) {
    const t = useTranslations();
    const hasAny = alerts.at_risk > 0 || alerts.unread_messages > 0 || alerts.unread_notifications > 0;

    if (!hasAny) {
        return <AttentionPill icon={Sparkles} label={t('cabinet.cockpit_no_alerts')} tone="ok" />;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {alerts.at_risk > 0 && (
                <AttentionPill
                    icon={AlertTriangle}
                    count={alerts.at_risk}
                    label={t('cabinet.cockpit_at_risk')}
                    tone="danger"
                    href={alerts.at_risk_student_id !== null ? `/cabinet/elev/${alerts.at_risk_student_id}?tab=overview` : undefined}
                />
            )}
            {alerts.unread_messages > 0 && (
                <AttentionPill
                    icon={MessageSquare}
                    count={alerts.unread_messages}
                    label={t(pluralKey('cabinet.cockpit_unread_messages', alerts.unread_messages))}
                    tone="info"
                    href="/cabinet/mesaje"
                />
            )}
            {alerts.unread_notifications > 0 && (
                <AttentionPill
                    icon={Bell}
                    count={alerts.unread_notifications}
                    label={t(pluralKey('cabinet.cockpit_unread_notifications', alerts.unread_notifications))}
                    tone="info"
                    href="/cabinet/notificari"
                />
            )}
        </div>
    );
}

/** Scurtătură de navigare — tile compact spre o destinație-cheie a cabinetului. */
function QuickAction({ icon: Icon, label, href }: { icon: LucideIcon; label: string; href: string }) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex items-center gap-3 rounded-xl border bg-card p-4 text-card-foreground shadow-sm',
                'transition-[border-color,box-shadow] duration-200',
                'hover:border-primary hover:shadow-md',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'motion-reduce:transition-none',
            )}
        >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                <Icon className="size-4.5" aria-hidden="true" />
            </span>
            <span className="font-medium">{label}</span>
        </Link>
    );
}

// Fiecare secțiune intră în cascadă la încărcare (motion-safe → inert sub prefers-reduced-motion).
const ENTER = 'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-3 motion-safe:duration-500 motion-safe:fill-mode-both';

export default function Dashboard({ cabinet }: DashboardProps) {
    const { auth } = usePage().props;
    const t = useTranslations();
    const hasCockpit = cabinet.children.length > 0 || cabinet.self !== null;

    return (
        <>
            <Head title={t('action.cabinet')} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 sm:p-6">
                {/* Salut — fără badge de rol (rolul e deja afișat în navbar → redundant aici). */}
                <header className={cn('min-w-0', ENTER)}>
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                        {t('dashboard.welcome')}, {auth.user.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
                </header>

                {/* Rândul de atenție */}
                {hasCockpit && (
                    <section className={cn('motion-safe:[animation-delay:75ms]', ENTER)} aria-labelledby="attention-heading">
                        <Eyebrow>
                            <span id="attention-heading">{t('cabinet.cockpit_alerts')}</span>
                        </Eyebrow>
                        <AttentionRail alerts={cabinet.alerts} />
                    </section>
                )}

                {/* Cardul propriu (elev) */}
                {cabinet.self && (
                    <section className={cn('motion-safe:[animation-delay:150ms]', ENTER)}>
                        <Eyebrow>{t('cabinet.cockpit_my_dashboard')}</Eyebrow>
                        <div className="max-w-2xl">
                            <StandingCard student={cabinet.self} />
                        </div>
                    </section>
                )}

                {/* Cardurile copiilor (părinte) */}
                {cabinet.children.length > 0 && (
                    <section className={cn('motion-safe:[animation-delay:150ms]', ENTER)}>
                        <Eyebrow>{t('cabinet.cockpit_my_children')}</Eyebrow>
                        <div className="grid gap-4 xl:grid-cols-2">
                            {cabinet.children.map((child) => (
                                <StandingCard key={child.id} student={child} />
                            ))}
                        </div>
                    </section>
                )}

                {/* Scurtături */}
                {hasCockpit && (
                    <section className={cn('motion-safe:[animation-delay:225ms]', ENTER)}>
                        <Eyebrow>{t('cabinet.cockpit_quick_actions')}</Eyebrow>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <QuickAction icon={CalendarDays} label={t('ccal.title')} href="/cabinet/calendar" />
                            <QuickAction icon={MessageSquare} label={t('cabinet.nav_messages')} href="/cabinet/mesaje" />
                            <QuickAction icon={Bell} label={t('cabinet.nav_notifications')} href="/cabinet/notificari" />
                        </div>
                    </section>
                )}

                {/* Cont fără elevi asociați */}
                {!hasCockpit && (
                    <EmptyState icon={GraduationCap} title={t('dashboard.no_profile')} className="flex-1" />
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'action.cabinet',
            href: dashboard(),
        },
    ],
};
