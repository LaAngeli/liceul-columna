import { Form } from '@inertiajs/react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { gradeLabel, motivationStatusClass  } from '@/components/cabinet/student-profile/helpers';
import type {GradeItem} from '@/components/cabinet/student-profile/helpers';
import { SkeletonGrid, SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/i18n';
import { motivation } from '@/routes/cabinet';

interface SubjectGrades {
    subject: string;
    average: number | null;
    mc?: number | null;
    summative?: number | null;
    items: GradeItem[];
}

interface MotivationItem {
    id: number;
    reason: string;
    period: string;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    isException: boolean;
    documentUrl: string | null;
}

/**
 * Tab Situație — note + absențe + motivări (formular & listă).
 * Prop-urile defer (subjects, absencesBySubject, motivations) sosesc progresiv; skeleton până atunci.
 */
export function SituationTab({
    studentId,
    subjects,
    absencesBySubject,
    absencesMotivated,
    absencesUnmotivated,
    motivations,
    canRequestMotivation,
    onContestGrade,
}: {
    studentId: number;
    subjects?: SubjectGrades[];
    absencesBySubject?: { subject: string; count: number }[];
    absencesMotivated: number;
    absencesUnmotivated: number;
    motivations?: MotivationItem[];
    canRequestMotivation: boolean;
    /** Familia poate porni o contestație direct din chip-ul notei (pre-completează cererea). */
    onContestGrade?: (gradeId: number) => void;
}) {
    const t = useTranslations();

    return (
        <div className="flex flex-col gap-6">
            {/* === NOTE === */}
            <section>
                <SectionHeading title={t('cabinet.grades_by_subject')} />
                <details className="mb-3 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                    <summary className="cursor-pointer font-medium text-muted-foreground">
                        ℹ️ {t('cabinet.info_procedure')}
                    </summary>
                    <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_grades')}</p>
                </details>

                {subjects === undefined ? (
                    <SkeletonTable rows={6} />
                ) : subjects.length === 0 ? (
                    <EmptyState title={t('cabinet.no_grades')} />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-2 font-medium">{t('cabinet.subject')}</th>
                                    <th scope="col" className="px-4 py-2 font-medium">{t('cabinet.grades')}</th>
                                    <th scope="col" className="px-4 py-2 text-right font-medium">{t('cabinet.average')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((s) => (
                                    <tr key={s.subject} className="border-t">
                                        <th scope="row" className="px-4 py-3 text-left font-medium">{s.subject}</th>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {s.items.map((item, i) => {
                                                    // min-h-7: chip-ul e și țintă tactilă („Contestă") — 20px era sub minimul WCAG pe mobil.
                                                    const chipClass = `inline-flex min-h-7 min-w-7 items-center justify-center rounded-md px-2 py-0.5 text-xs font-semibold ${
                                                        item.isSummative
                                                            ? 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/40 dark:text-amber-300'
                                                            : 'bg-primary/10 text-primary'
                                                    }`;
                                                    const tooltip = [item.typeLabel, item.date].filter(Boolean).join(' · ');
                                                    const gradeId = item.id;

                                                    // Familia poate contesta direct din chip — cererea se
                                                    // deschide cu nota deja selectată (zero re-tastare).
                                                    return onContestGrade && gradeId != null ? (
                                                        <button
                                                            key={i}
                                                            type="button"
                                                            onClick={() => onContestGrade(gradeId)}
                                                            title={[tooltip, t('cabinet.grade_contest_hint')].filter(Boolean).join(' · ')}
                                                            aria-label={`${t('cabinet.grade_contest_hint')}: ${s.subject} — ${gradeLabel(item)}`}
                                                            className={`${chipClass} cursor-pointer transition-shadow hover:ring-2 hover:ring-primary/40`}
                                                        >
                                                            {gradeLabel(item)}
                                                        </button>
                                                    ) : (
                                                        <span key={i} className={chipClass} title={tooltip || undefined}>
                                                            {gradeLabel(item)}
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="font-semibold">{s.average ?? '—'}</div>
                                            {s.mc != null && s.summative != null && (
                                                <div className="mt-0.5 text-[11px] font-normal text-muted-foreground">
                                                    {t('cabinet.avg_current')} {s.mc} · {t('cabinet.avg_summative')} {s.summative}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {subjects !== undefined && subjects.length > 0 && (
                    <p className="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        <span className="inline-block h-2.5 w-2.5 rounded-sm bg-amber-500/40 ring-1 ring-amber-500/40" />
                        {t('cabinet.summative_legend')}
                    </p>
                )}
                {onContestGrade && subjects !== undefined && subjects.length > 0 && (
                    <p className="mt-1 text-[11px] text-muted-foreground">{t('cabinet.grade_contest_legend')}</p>
                )}
            </section>

            {/* === ABSENȚE === */}
            <section>
                <SectionHeading
                    title={t('cabinet.absences_by_subject')}
                    actions={
                        <>
                            <Badge variant="default" className="bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/15 dark:text-emerald-400">
                                {absencesMotivated} {t('cabinet.motivated')}
                            </Badge>
                            <Badge variant="destructive">
                                {absencesUnmotivated} {t('cabinet.unmotivated')}
                            </Badge>
                        </>
                    }
                />
                <details className="mb-3 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                    <summary className="cursor-pointer font-medium text-muted-foreground">
                        ℹ️ {t('cabinet.info_procedure')}
                    </summary>
                    <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_absences')}</p>
                </details>

                {absencesBySubject === undefined ? (
                    <SkeletonGrid count={6} columns={3} />
                ) : absencesBySubject.length === 0 ? (
                    <EmptyState title={t('cabinet.no_absences')} />
                ) : (
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {absencesBySubject.map((a) => (
                            <div key={a.subject} className="flex items-center justify-between rounded-lg border bg-card px-4 py-2">
                                <span className="truncate text-sm">{a.subject}</span>
                                <Badge variant="destructive">{a.count}</Badge>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {/* === MOTIVĂRI === */}
            {(canRequestMotivation || (motivations && motivations.length > 0)) && (
                <section>
                    <SectionHeading title={t('cabinet.motivations_title')} />
                    <div className="grid gap-4 lg:grid-cols-2">
                        {canRequestMotivation && <MotivationForm studentId={studentId} />}

                        <div className="overflow-hidden rounded-xl border">
                            {motivations === undefined ? (
                                <SkeletonTable rows={3} />
                            ) : motivations.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    {t('cabinet.motivation_none')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {motivations.map((m) => (
                                        <li key={m.id} className="flex items-start justify-between gap-3 px-4 py-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">{m.period}</p>
                                                <p className="truncate text-xs text-muted-foreground">{m.reason}</p>
                                                {m.isException && (
                                                    <span className="mt-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                                        {t('cabinet.motivation_exception')}
                                                    </span>
                                                )}
                                                {m.documentUrl && (
                                                    <a
                                                        href={m.documentUrl}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                                    >
                                                        📎 {t('cabinet.motivation_document_view')}
                                                    </a>
                                                )}
                                            </div>
                                            <span
                                                className={`shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold ${motivationStatusClass(m.status)}`}
                                            >
                                                {t(`cabinet.motivation_status_${m.status}`, m.statusLabel)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </section>
            )}
        </div>
    );
}

/** Formularul de motivare (familia) — extras pentru claritate. */
function MotivationForm({ studentId }: { studentId: number }) {
    const t = useTranslations();

    return (
        <Form
            {...motivation.form(studentId)}
            resetOnSuccess
            className="rounded-xl border bg-card p-4 shadow-sm"
        >
            {({ processing, errors, recentlySuccessful }) => (
                <div className="flex flex-col gap-3">
                    <p className="text-sm font-medium">{t('cabinet.motivation_new')}</p>

                    <div className="grid gap-1.5">
                        <label htmlFor="reason" className="text-xs text-muted-foreground">
                            {t('cabinet.motivation_reason')}
                        </label>
                        <textarea
                            id="reason"
                            name="reason"
                            required
                            rows={3}
                            placeholder={t('cabinet.motivation_reason_ph')}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        {errors.reason && <p className="text-xs text-destructive">{errors.reason}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <label htmlFor="period_start" className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_from')}
                            </label>
                            <input
                                id="period_start"
                                name="period_start"
                                type="date"
                                required
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            {errors.period_start && <p className="text-xs text-destructive">{errors.period_start}</p>}
                        </div>
                        <div className="grid gap-1.5">
                            <label htmlFor="period_end" className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_to')}
                            </label>
                            <input
                                id="period_end"
                                name="period_end"
                                type="date"
                                required
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            {errors.period_end && <p className="text-xs text-destructive">{errors.period_end}</p>}
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <label htmlFor="document" className="text-xs text-muted-foreground">
                            {t('cabinet.motivation_document')}
                        </label>
                        <input
                            id="document"
                            name="document"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                        />
                        {errors.document && <p className="text-xs text-destructive">{errors.document}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        {processing ? t('cabinet.motivation_sending') : t('cabinet.motivation_submit')}
                    </button>
                    {recentlySuccessful && (
                        <p className="rounded-md bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            {t('cabinet.motivation_sent')}
                        </p>
                    )}
                </div>
            )}
        </Form>
    );
}
