import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowUpRight,
    Bell,
    CalendarX,
    FileWarning,
    GraduationCap,
    MessageSquare,
    Sparkles,
    TrendingUp,
} from 'lucide-react';
import { AlertCard } from '@/components/cabinet/alert-card';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { StudentStatusBadge  } from '@/components/cabinet/student-status-badge';
import type {StudentStatusValue} from '@/components/cabinet/student-status-badge';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { pluralKey, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Trend = 'up' | 'stable' | 'down' | null;

interface LastGrade {
    value: string;
    subject: string;
    date: string;
}

interface CockpitChild {
    id: number;
    name: string;
    class: string | null;
    average: number | null;
    trend: Trend;
    statusValue: string | null;
    isAtRisk: boolean;
    lastGrade: LastGrade | null;
    recentAbsences: number;
    // Motivări depuse de familie, încă neaprobate de diriginte — afișate PER-COPIL (nu agregat).
    pendingMotivations: number;
}

interface CockpitAlerts {
    unread_messages: number;
    unread_notifications: number;
    at_risk: number;
    // Id-ul PRIMULUI copil cu risc corigent/amânat (pentru link spre tabul „Prezentare" cu confirmarea „am luat cunoștință").
    at_risk_student_id: number | null;
}

interface DashboardProps {
    cabinet: {
        children: CockpitChild[];
        self: CockpitChild | null;
        alerts: CockpitAlerts;
    };
}

function trendSymbol(trend: Trend): { symbol: string; cls: string; key: 'cockpit_trend_up' | 'cockpit_trend_down' | 'cockpit_trend_stable' } | null {
    if (trend === 'up') {
        return { symbol: '▲', cls: 'text-emerald-700 dark:text-emerald-300', key: 'cockpit_trend_up' };
    }

    if (trend === 'down') {
        return { symbol: '▼', cls: 'text-destructive', key: 'cockpit_trend_down' };
    }

    if (trend === 'stable') {
        return { symbol: '▬', cls: 'text-muted-foreground', key: 'cockpit_trend_stable' };
    }

    return null;
}

/**
 * Banda de alerte CROSS-COPIL — doar lucruri care îți cer atenția și nu aparțin unui singur copil:
 * mesaje/notificări necitite + copii cu risc corigent/amânat. (Motivările în așteptare NU sunt aici;
 * fiind status per-copil, apar pe cardul fiecărui copil — vezi `ChildCockpitCard`.)
 * Dacă toate-s zero, o singură stare „nimic de semnalat" în loc de carduri goale.
 */
function AlertStrip({ alerts }: { alerts: CockpitAlerts }) {
    const t = useTranslations();
    const hasAny = alerts.unread_messages > 0 || alerts.unread_notifications > 0 || alerts.at_risk > 0;

    if (!hasAny) {
        return (
            <EmptyState
                icon={Sparkles}
                title={t('cabinet.cockpit_no_alerts')}
                className="py-6"
            />
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {alerts.unread_messages > 0 && (
                <AlertCard
                    icon={MessageSquare}
                    label={t(pluralKey('cabinet.cockpit_unread_messages', alerts.unread_messages))}
                    value={alerts.unread_messages}
                    variant="info"
                    href="/cabinet/mesaje"
                />
            )}
            {alerts.unread_notifications > 0 && (
                <AlertCard
                    icon={Bell}
                    label={t(pluralKey('cabinet.cockpit_unread_notifications', alerts.unread_notifications))}
                    value={alerts.unread_notifications}
                    variant="info"
                    href="/cabinet/notificari"
                />
            )}
            {alerts.at_risk > 0 && (
                <AlertCard
                    icon={AlertTriangle}
                    label={t('cabinet.cockpit_at_risk')}
                    value={alerts.at_risk}
                    variant="danger"
                    href={
                        alerts.at_risk_student_id !== null
                            ? `/cabinet/elev/${alerts.at_risk_student_id}?tab=overview`
                            : undefined
                    }
                />
            )}
        </div>
    );
}

/**
 * Card-copil îmbogățit pentru cockpit: identitate + medie + tendință + status + „ce-i nou".
 * Întreg cardul e un Link spre profilul elevului (card-link pattern, a11y).
 */
function ChildCockpitCard({ student }: { student: CockpitChild }) {
    const t = useTranslations();
    const getInitials = useInitials();
    const trend = trendSymbol(student.trend);

    return (
        <Link
            href={`/cabinet/elev/${student.id}`}
            className={cn(
                'group flex flex-col gap-4 rounded-xl border bg-card p-5 text-card-foreground shadow-sm transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                student.isAtRisk && 'border-destructive/40',
            )}
            aria-label={`${student.name} — ${t('cabinet.cockpit_view_profile')}`}
        >
            {/* Identitate */}
            <div className="flex items-start gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary">
                    {getInitials(student.name)}
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-semibold">{student.name}</h3>
                    <p className="text-sm text-muted-foreground">
                        {student.class ?? t('cabinet.class_unassigned')}
                    </p>
                </div>
                <ArrowUpRight className="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
            </div>

            {/* Medie + tendință + status */}
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-baseline gap-1.5">
                    <TrendingUp className="size-4 self-center text-muted-foreground" aria-hidden="true" />
                    <span className="text-2xl font-semibold text-primary">{student.average ?? '—'}</span>
                    {trend && (
                        <span className={cn('text-sm font-semibold', trend.cls)} title={t(`cabinet.${trend.key}`)}>
                            {trend.symbol}
                        </span>
                    )}
                </div>
                {student.statusValue !== null && (
                    <StudentStatusBadge status={student.statusValue as StudentStatusValue} />
                )}
            </div>

            {/* Ce-i nou: ultima notă + absențe noi */}
            <div className="flex flex-col gap-1.5 rounded-lg bg-muted/40 p-3 text-sm">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {t('cabinet.cockpit_whats_new')}
                </p>
                {student.lastGrade ? (
                    <p>
                        <span className="text-muted-foreground">{t('cabinet.cockpit_last_grade')}: </span>
                        <span className="font-semibold text-primary">{student.lastGrade.value}</span>
                        <span className="text-muted-foreground"> · {student.lastGrade.subject}</span>
                        <span className="text-xs text-muted-foreground"> ({student.lastGrade.date})</span>
                    </p>
                ) : (
                    <p className="text-muted-foreground">{t('cabinet.cockpit_no_grades_yet')}</p>
                )}
                {student.recentAbsences > 0 && (
                    <p className="flex items-center gap-1.5">
                        <CalendarX className="size-3.5 text-destructive" aria-hidden="true" />
                        <span>
                            <Badge variant="destructive" className="px-1.5 py-0">
                                {student.recentAbsences}
                            </Badge>{' '}
                            <span className="text-muted-foreground">{t(pluralKey('cabinet.cockpit_recent_absences', student.recentAbsences))}</span>
                        </span>
                    </p>
                )}
                {student.pendingMotivations > 0 && (
                    <p className="flex items-center gap-1.5">
                        <FileWarning className="size-3.5 text-amber-700 dark:text-amber-300" aria-hidden="true" />
                        <span>
                            <Badge
                                variant="default"
                                className="bg-amber-500/15 px-1.5 py-0 text-amber-700 hover:bg-amber-500/15 dark:text-amber-300"
                            >
                                {student.pendingMotivations}
                            </Badge>{' '}
                            <span className="text-muted-foreground">{t(pluralKey('cabinet.cockpit_pending_motivations', student.pendingMotivations))}</span>
                        </span>
                    </p>
                )}
            </div>
        </Link>
    );
}

export default function Dashboard({ cabinet }: DashboardProps) {
    const { auth } = usePage().props;
    const t = useTranslations();
    const hasCockpit = cabinet.children.length > 0 || cabinet.self !== null;

    return (
        <>
            <Head title={t('action.cabinet')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Antet */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('dashboard.welcome')}, {auth.user.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
                    </div>
                    {auth.role && (
                        <Badge variant="secondary" className="text-sm font-semibold">
                            {t(`roles.${auth.role}`, auth.role)}
                        </Badge>
                    )}
                </div>

                {/* Banda de alerte */}
                {hasCockpit && (
                    <section aria-labelledby="alerts-heading">
                        <SectionHeading
                            title={t('cabinet.cockpit_alerts')}
                            className="mb-3"
                        />
                        <AlertStrip alerts={cabinet.alerts} />
                    </section>
                )}

                {/* Carduri cockpit */}
                {cabinet.self && (
                    <section>
                        <SectionHeading title={t('cabinet.cockpit_my_dashboard')} />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <ChildCockpitCard student={cabinet.self} />
                        </div>
                    </section>
                )}

                {cabinet.children.length > 0 && (
                    <section>
                        <SectionHeading title={t('cabinet.cockpit_my_children')} />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {cabinet.children.map((child) => (
                                <ChildCockpitCard key={child.id} student={child} />
                            ))}
                        </div>
                    </section>
                )}

                {/* Empty state (cont fără elevi asociați) */}
                {!hasCockpit && (
                    <EmptyState
                        icon={GraduationCap}
                        title={t('dashboard.no_profile')}
                        className="flex-1"
                    />
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
