import { Form } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, BellRing, Info, ShieldCheck, TrendingDown } from 'lucide-react';
import { useState } from 'react';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { StatCard } from '@/components/cabinet/stat-card';
import { sparklinePoints, trendSymbol } from '@/components/cabinet/student-profile/helpers';
import type { Trend } from '@/components/cabinet/student-profile/helpers';
import { StudentStatusBadge } from '@/components/cabinet/student-status-badge';
import type { StudentStatusValue } from '@/components/cabinet/student-status-badge';
import { useTranslations } from '@/lib/i18n';

interface StudentStatus {
    status: 'promovat' | 'corigent' | 'repetent' | 'amanat' | null;
    label: string | null;
    failingSubjects: string[];
    official: boolean;
    orderReference: string | null;
}

interface StatusAck {
    needed: boolean;
    acknowledged: boolean;
    acknowledgedAt: string | null;
    // Cine a confirmat — părintele distinge confirmarea proprie de cea făcută de elev (#37).
    acknowledgedBy: string | null;
    canAcknowledge: boolean;
}

interface DeferralRisk {
    subject: string;
    absences: number;
    scheduled: number;
}

interface Dynamics {
    general: { level: number; average: number }[];
    subjects: { subject: string; points: { level: number; value: number }[]; trend: Trend }[];
    current: {
        average: number | null;
        historyAverage: number | null;
        previousYearSameTerm: number | null;
        trend: Trend;
        alert: boolean;
    };
}

/**
 * Tab Prezentare — ecran-rezumat al situației elevului:
 *  • alerte: confirmare statut, risc amânare, scădere dinamică
 *  • snapshot: medie curentă + tendință + sparkline, absențe motivate/nemotivate
 */
export function OverviewTab({
    studentId,
    studentAverage,
    absencesTotal,
    absencesMotivated,
    absencesUnmotivated,
    status,
    statusAck,
    deferralRisk,
    dynamics,
    onShowDetails,
    onOpenSection,
}: {
    studentId: number;
    studentAverage: number | null;
    absencesTotal: number;
    absencesMotivated: number;
    absencesUnmotivated: number;
    status: StudentStatus;
    statusAck: StatusAck;
    deferralRisk?: { risks: DeferralRisk[]; undetermined: string[]; noTimetable: boolean };
    dynamics?: Dynamics;
    /** Comută la tabul „Situație" (unde se află notele/mediile), ca părintele să vadă ÎNAINTE de confirmare. */
    onShowDetails: () => void;
    /** Deschide tabul „Situație" derulat la secțiunea indicată — tile-urile snapshotului sunt apăsabile. */
    onOpenSection?: (section: 'note' | 'absente' | 'motivari') => void;
}) {
    const t = useTranslations();
    const tr = dynamics ? trendSymbol(dynamics.current.trend) : null;
    // Golul de calcul (orar incomplet) TREBUIE să deschidă secțiunea de la sine: altfel se afișa
    // doar când elevul avea deja o altă alertă — adică exact invers față de scopul lui, fiindcă
    // tocmai elevul fără alte semnale e cel despre care nu putem spune nimic.
    const cannotCompute = (deferralRisk?.undetermined.length ?? 0) > 0 || (deferralRisk?.noTimetable ?? false);
    const hasAlerts =
        statusAck.needed || (deferralRisk?.risks.length ?? 0) > 0 || (dynamics?.current.alert ?? false) || cannotCompute;
    // Audit § confirmare statut #9 — anti-click-accidental: butonul de confirmare (înregistrare cu dată
    // în jurnalul de audit) e activ DOAR după ce părintele bifează explicit „Am citit situația de mai sus".
    const [confirmReady, setConfirmReady] = useState(false);

    return (
        <div className="flex flex-col gap-6">
            {/* === ALERTE === */}
            {hasAlerts && (
                <section aria-labelledby="overview-alerts" className="flex flex-col gap-3">
                    <SectionHeading title={t('cabinet.cockpit_alerts')} className="mb-0" />

                    {/* Confirmare „am luat cunoștință" de statut — arată CE se confirmă (statut concret +
                        discipline restante + ordin) și un link la note/medii, ca să nu confirmi la orbește. */}
                    {statusAck.needed && (
                        <div className="rounded-xl border border-amber-500/50 bg-amber-500/10 p-4">
                            <div className="flex items-start gap-3">
                                <ShieldCheck className="mt-0.5 size-5 text-amber-700 dark:text-amber-300" aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                        {t('cabinet.status_ack_title')}
                                    </h3>

                                    {/* CE se confirmă — statutul concret + disciplinele restante */}
                                    <div className="mt-2 rounded-lg border border-amber-500/30 bg-card/60 p-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-medium text-muted-foreground">
                                                {t('cabinet.status_ack_situation')}:
                                            </span>
                                            <StudentStatusBadge status={status.status as StudentStatusValue} />
                                        </div>
                                        {status.failingSubjects.length > 0 && (
                                            <p className="mt-1.5 text-sm text-amber-800/90 dark:text-amber-300/90">
                                                <span className="font-medium">{t('cabinet.status_ack_failing')}:</span>{' '}
                                                {status.failingSubjects.join(', ')}
                                            </p>
                                        )}
                                        {status.official && status.orderReference && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {t('cabinet.status_ack_order')}: {status.orderReference}
                                            </p>
                                        )}
                                        <button
                                            type="button"
                                            onClick={onShowDetails}
                                            className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                        >
                                            {t('cabinet.status_ack_review')}
                                            <ArrowRight className="size-4" aria-hidden="true" />
                                        </button>
                                    </div>

                                    <p className="mt-2 text-sm text-amber-800/90 dark:text-amber-300/90">
                                        {t('cabinet.status_ack_text')}
                                    </p>
                                    {statusAck.acknowledged ? (
                                        <p className="mt-2 inline-flex items-center gap-1.5 rounded-md bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            ✓ {t('cabinet.status_ack_done')} {statusAck.acknowledgedAt}
                                            {statusAck.acknowledgedBy && ` — ${statusAck.acknowledgedBy}`}
                                        </p>
                                    ) : statusAck.canAcknowledge ? (
                                        <Form action={`/cabinet/elev/${studentId}/confirm-statut`} method="post" className="mt-3 flex flex-col gap-2.5">
                                            {({ processing }) => (
                                                <>
                                                    <label className="inline-flex cursor-pointer items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={confirmReady}
                                                            onChange={(e) => setConfirmReady(e.target.checked)}
                                                            className="mt-0.5 size-4 rounded border-input accent-amber-600 focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                        <span>{t('cabinet.status_ack_checkbox')}</span>
                                                    </label>
                                                    <button
                                                        type="submit"
                                                        disabled={processing || !confirmReady}
                                                        className="inline-flex h-10 w-fit items-center justify-center rounded-md bg-amber-600 px-4 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60"
                                                    >
                                                        {t('cabinet.status_ack_confirm')}
                                                    </button>
                                                </>
                                            )}
                                        </Form>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Risc amânare */}
                    {deferralRisk && deferralRisk.risks.length > 0 && (
                        <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
                            <div className="flex items-start gap-3">
                                <AlertTriangle className="mt-0.5 size-5 text-amber-700 dark:text-amber-300" aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-semibold text-amber-700 dark:text-amber-300">{t('cabinet.deferral_title')}</h3>
                                    <p className="mt-1 text-sm text-amber-800/90 dark:text-amber-300/90">{t('cabinet.deferral_hint')}</p>
                                    <p className="mt-1 text-xs text-amber-700/80 dark:text-amber-300/70">{t('cabinet.deferral_not_status')}</p>
                                    <ul className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                        {deferralRisk.risks.map((r) => (
                                            <li key={r.subject} className="rounded-lg border border-amber-500/40 bg-card px-3 py-2 text-sm">
                                                <span className="font-medium">{r.subject}</span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {r.absences} / {r.scheduled} {t('cabinet.deferral_lessons')}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Discipline la care riscul NU se poate calcula (orar structurat incomplet).
                        Deliberat sobru, nu alarmant: nu spune că elevul are o problemă, spune că
                        sistemul nu poate răspunde. Tăcerea de dinainte se citea drept „totul e în
                        regulă", ceea ce e o afirmație pe care datele nu o susțin. */}
                    {deferralRisk && cannotCompute && (
                        <div className="rounded-xl border border-border bg-muted/40 p-4">
                            <div className="flex items-start gap-3">
                                <Info className="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-sm font-semibold">{t('cabinet.deferral_unknown_title')}</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">{deferralRisk.noTimetable ? t('cabinet.deferral_no_timetable') : t('cabinet.deferral_unknown_hint')}</p>
                                    {deferralRisk.undetermined.length > 0 && (
                                        <p className="mt-2 text-sm">{deferralRisk.undetermined.join(', ')}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Scădere dinamică */}
                    {dynamics?.current.alert && (
                        <div className="flex items-start gap-3 rounded-lg bg-destructive/10 p-4 text-sm font-medium text-destructive">
                            <TrendingDown className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                            <span>{t('cabinet.dynamics_alert')}</span>
                        </div>
                    )}
                </section>
            )}

            {/* === SNAPSHOT === */}
            <section aria-labelledby="overview-snapshot">
                <SectionHeading title={t('cabinet.dynamics_title')} description={t('cabinet.tab_overview_intro')} />
                {/* Tile-urile duc la secțiunea indicată din „Situație": media → note; toate cele
                    trei numărători de absențe → tabelul de absențe (acolo trăiește defalcarea
                    motivate/nemotivate; cererile de motivare au tile-ul lor în cockpit). */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        icon={BellRing}
                        label={t('cabinet.average_general')}
                        value={dynamics?.current.average ?? studentAverage ?? '—'}
                        trend={dynamics?.current.trend ?? null}
                        trendLabel={tr ? t(`cabinet.dynamics_trend_${dynamics?.current.trend}`) : undefined}
                        onPress={onOpenSection ? () => onOpenSection('note') : undefined}
                    />
                    <StatCard
                        label={t('cabinet.absences')}
                        value={absencesTotal}
                        onPress={onOpenSection ? () => onOpenSection('absente') : undefined}
                    />
                    <StatCard
                        label={t('cabinet.motivated')}
                        value={absencesMotivated}
                        onPress={onOpenSection ? () => onOpenSection('absente') : undefined}
                    />
                    <StatCard
                        label={t('cabinet.unmotivated')}
                        value={absencesUnmotivated}
                        onPress={onOpenSection ? () => onOpenSection('absente') : undefined}
                    />
                </div>

                {/* Comparație sem. curent vs anul trecut (spec §2.3) — apare doar dacă există date
                    în academic_records pentru treapta anterioară (altfel previousYearSameTerm e null). */}
                {dynamics?.current.previousYearSameTerm != null && (
                    <div className="mt-3 flex flex-wrap items-center gap-2 rounded-lg border bg-card px-4 py-2 text-sm">
                        <span className="text-muted-foreground">{t('cabinet.dynamics_vs_last_year')}:</span>
                        <span className="font-semibold">{dynamics.current.previousYearSameTerm}</span>
                        {dynamics.current.average != null && (() => {
                            const delta = Number((dynamics.current.average - dynamics.current.previousYearSameTerm).toFixed(2));
                            const cls = delta > 0
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : delta < 0
                                    ? 'text-destructive'
                                    : 'text-muted-foreground';

                            return (
                                <span className={`text-xs ${cls}`}>
                                    ({delta > 0 ? '+' : ''}{delta})
                                </span>
                            );
                        })()}
                    </div>
                )}

                {/* Sparkline evoluție multi-an (din dynamics) */}
                {dynamics && dynamics.general.length > 1 && (
                    <div className="mt-4 rounded-xl border bg-card p-4 shadow-sm">
                        <p className="mb-1 text-xs text-muted-foreground">{t('cabinet.dynamics_general')}</p>
                        <svg
                            viewBox="0 0 200 48"
                            className="h-12 w-full text-primary"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <polyline
                                points={sparklinePoints(
                                    [
                                        ...dynamics.general.map((g) => g.average),
                                        ...(dynamics.current.average !== null ? [dynamics.current.average] : []),
                                    ],
                                    200,
                                    44,
                                )}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                vectorEffect="non-scaling-stroke"
                            />
                        </svg>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {dynamics.general.map((g) => (
                                <span key={g.level} className="rounded-md bg-muted px-2 py-0.5 text-xs">
                                    {t('cabinet.class')} {g.level}: <span className="font-semibold">{g.average}</span>
                                </span>
                            ))}
                        </div>
                    </div>
                )}
            </section>
        </div>
    );
}
