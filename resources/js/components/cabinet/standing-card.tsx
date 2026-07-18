import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { GradeDial } from '@/components/cabinet/grade-dial';
import { StudentStatusBadge } from '@/components/cabinet/student-status-badge';
import type { StudentStatusValue } from '@/components/cabinet/student-status-badge';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type Trend = 'up' | 'stable' | 'down' | null;

export interface LastGrade {
    value: string;
    subject: string;
    date: string;
}

export interface CockpitChild {
    id: number;
    name: string;
    class: string | null;
    average: number | null;
    trend: Trend;
    statusValue: string | null;
    isAtRisk: boolean;
    lastGrade: LastGrade | null;
    recentAbsences: number;
    pendingMotivations: number;
}

const TREND_VISUAL: Record<'up' | 'down' | 'stable', { symbol: string; cls: string; key: string }> = {
    up: { symbol: '▲', cls: 'text-emerald-700 dark:text-emerald-300', key: 'cabinet.cockpit_trend_up' },
    down: { symbol: '▼', cls: 'text-destructive', key: 'cabinet.cockpit_trend_down' },
    stable: { symbol: '▬', cls: 'text-muted-foreground', key: 'cabinet.cockpit_trend_stable' },
};

type Tone = 'navy' | 'danger' | 'amber' | 'neutral';

const TILE_BORDER: Record<Tone, string> = {
    navy: 'border-l-brand-navy',
    danger: 'border-l-destructive',
    amber: 'border-l-amber-500',
    neutral: 'border-l-border',
};

const TILE_VALUE: Record<Tone, string> = {
    navy: 'text-foreground',
    danger: 'text-destructive',
    amber: 'text-amber-700 dark:text-amber-300',
    neutral: 'text-foreground',
};

/**
 * Tile de statistică (varianta B) — valoare + etichetă, cu accent de culoare pe muchia stângă.
 * E un LINK către secțiunea exactă din profil (cerința beneficiarului: tile-urile duc la tipul
 * de informație indicat) — `relative z-10` îl ridică deasupra linkului „întins" al cardului.
 */
function StatTile({
    value,
    label,
    sub,
    tone,
    href,
    ariaLabel,
}: {
    value: string | number;
    label: string;
    sub?: string;
    tone: Tone;
    href: string;
    ariaLabel: string;
}) {
    return (
        <Link
            href={href}
            aria-label={ariaLabel}
            className={cn(
                'relative z-10 rounded-lg border border-l-[3px] bg-muted/30 p-2.5',
                'transition-[background-color,box-shadow] duration-150',
                'hover:bg-muted/70 hover:ring-1 hover:ring-primary/40',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                'motion-reduce:transition-none',
                TILE_BORDER[tone],
            )}
        >
            <div className={cn('text-xl font-semibold leading-tight tabular-nums', TILE_VALUE[tone])}>{value}</div>
            <div className="truncate text-[11px] leading-tight text-muted-foreground">{label}</div>
            {sub && <div className="mt-0.5 truncate text-[10px] text-muted-foreground/80">{sub}</div>}
        </Link>
    );
}

/**
 * Cardul-standing (combinație A+B): cadranul radial al mediei (spotlight) + rând de tile-uri de
 * statistici (ultima notă, absențe noi, motivări). Cardul rămâne clickabil ÎNTREG spre profil
 * prin linkul „întins" de pe numele elevului (pseudo-elementul `after` acoperă cardul — ancorele
 * nu se pot imbrica), iar fiecare tile e propriul link către secțiunea lui din tabul Situație.
 */
export function StandingCard({ student }: { student: CockpitChild }) {
    const t = useTranslations();
    const getInitials = useInitials();

    const trend = student.trend !== null ? TREND_VISUAL[student.trend] : null;
    const profileUrl = `/cabinet/elev/${student.id}`;
    const situationUrl = (section: 'note' | 'absente' | 'motivari') =>
        `${profileUrl}?tab=situation&sectiune=${section}`;

    return (
        <div
            className={cn(
                'group relative flex flex-col gap-5 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm',
                'transition-[box-shadow,border-color] duration-200',
                'hover:border-primary hover:shadow-md',
                'motion-reduce:transition-none',
                student.isAtRisk && 'border-destructive/40',
            )}
        >
            {/* Identitate — numele poartă linkul întins peste tot cardul */}
            <div className="flex items-center gap-3">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-base font-semibold text-primary ring-1 ring-primary/10">
                    {getInitials(student.name)}
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-semibold leading-tight">
                        <Link
                            href={profileUrl}
                            aria-label={`${student.name} — ${t('cabinet.cockpit_view_profile')}`}
                            className={cn(
                                'focus-visible:outline-none',
                                'after:absolute after:inset-0 after:rounded-2xl after:content-[""]',
                                'focus-visible:after:ring-2 focus-visible:after:ring-ring focus-visible:after:ring-offset-2',
                            )}
                        >
                            {student.name}
                        </Link>
                    </h3>
                    <p className="text-sm text-muted-foreground">{student.class ?? t('cabinet.class_unassigned')}</p>
                </div>
                {student.statusValue !== null && (
                    <StudentStatusBadge status={student.statusValue as StudentStatusValue} />
                )}
                <ArrowUpRight className="size-5 shrink-0 text-muted-foreground/60 transition-colors group-hover:text-primary" />
            </div>

            {/* Cadran radial (spotlight A) + tile-uri de statistici (B) */}
            <div className="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6">
                <div className="flex flex-col items-center gap-1.5 sm:shrink-0">
                    <GradeDial
                        average={student.average}
                        trendSymbol={trend?.symbol ?? null}
                        trendClass={trend?.cls}
                        trendTitle={trend ? t(trend.key) : undefined}
                    />
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {student.average === null ? t('cabinet.cockpit_no_average') : t('cabinet.cockpit_general_average')}
                    </span>
                </div>

                <div className="grid w-full flex-1 grid-cols-3 gap-2.5">
                    <StatTile
                        value={student.lastGrade ? student.lastGrade.value : '—'}
                        label={t('cabinet.cockpit_last_grade')}
                        sub={student.lastGrade ? student.lastGrade.subject : undefined}
                        tone="navy"
                        href={situationUrl('note')}
                        ariaLabel={`${student.name} — ${t('cabinet.cockpit_last_grade')}`}
                    />
                    <StatTile
                        value={student.recentAbsences}
                        label={t('cabinet.cockpit_tile_absences')}
                        tone={student.recentAbsences > 0 ? 'danger' : 'neutral'}
                        href={situationUrl('absente')}
                        ariaLabel={`${student.name} — ${t('cabinet.cockpit_tile_absences')}`}
                    />
                    <StatTile
                        value={student.pendingMotivations}
                        label={t('cabinet.cockpit_tile_motivations')}
                        tone={student.pendingMotivations > 0 ? 'amber' : 'neutral'}
                        href={situationUrl('motivari')}
                        ariaLabel={`${student.name} — ${t('cabinet.cockpit_tile_motivations')}`}
                    />
                </div>
            </div>
        </div>
    );
}
