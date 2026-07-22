import { Form } from '@inertiajs/react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { gradeLabel, motivationStatusClass } from '@/components/cabinet/student-profile/helpers';
import type { GradeItem } from '@/components/cabinet/student-profile/helpers';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/lib/i18n';
import { motivation } from '@/routes/cabinet';

/**
 * Vederile PARTAJATE ale situației (note + absențe + motivări) — folosite de modulele „Note" și
 * „Absențe" din meniu ȘI de tabul „Situație" al fișei elevului. O singură implementare.
 */

export interface SubjectGrades {
    subject: string;
    average: number | null;
    mc?: number | null;
    summative?: number | null;
    items: GradeItem[];
}

export interface MotivationItem {
    id: number;
    reason: string;
    period: string;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    isException: boolean;
    documentUrl: string | null;
    note: string | null;
}

export interface SemesterAveragesMatrix {
    terms: number[];
    rows: { subject: string; values: Record<number, number | null> }[];
    general: Record<number, number | null>;
}

/** Tabelul notelor pe discipline (chips + MS cu componente) + legendele. */
export function GradesTable({
    subjects,
    onContestGrade,
}: {
    subjects: SubjectGrades[];
    onContestGrade?: (gradeId: number) => void;
}) {
    const t = useTranslations();

    if (subjects.length === 0) {
        return <EmptyState title={t('cabinet.no_grades')} />;
    }

    return (
        <>
            <div className="overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-2.5 py-2 font-medium sm:px-4">{t('cabinet.subject')}</th>
                            <th scope="col" className="px-2.5 py-2 font-medium sm:px-4">{t('cabinet.grades')}</th>
                            <th scope="col" className="px-2.5 py-2 text-right font-medium sm:px-4">{t('cabinet.average')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {subjects.map((s) => (
                            <tr key={s.subject} className="border-t">
                                <th scope="row" className="px-2.5 py-3 text-left font-medium sm:px-4">{s.subject}</th>
                                <td className="px-2.5 py-3 sm:px-4">
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
                                <td className="px-2.5 py-3 text-right sm:px-4">
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
            <p className="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <span className="inline-block h-2.5 w-2.5 rounded-sm bg-amber-500/40 ring-1 ring-amber-500/40" />
                {t('cabinet.summative_legend')}
            </p>
            {onContestGrade && (
                <p className="mt-1 text-[11px] text-muted-foreground">{t('cabinet.grade_contest_legend')}</p>
            )}
        </>
    );
}

/** Matricea mediilor semestriale ale anului curent: disciplină × semestru + media generală. */
export function SemesterAveragesTable({ averages }: { averages: SemesterAveragesMatrix }) {
    const t = useTranslations();

    if (averages.rows.length === 0) {
        return <EmptyState title={t('cabinet.no_grades')} />;
    }

    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-2.5 py-2 font-medium sm:px-4">{t('cabinet.subject')}</th>
                        {averages.terms.map((n) => (
                            <th key={n} scope="col" className="px-2.5 py-2 text-center font-medium sm:px-4">
                                {t(n === 1 ? 'cabinet.sem1' : 'cabinet.sem2')}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {averages.rows.map((row) => (
                        <tr key={row.subject} className="border-t">
                            <th scope="row" className="px-2.5 py-2.5 text-left font-medium sm:px-4">{row.subject}</th>
                            {averages.terms.map((n) => (
                                <td key={n} className="px-2.5 py-2.5 text-center font-semibold sm:px-4">
                                    {row.values[n] ?? '—'}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t bg-primary/5">
                        <th scope="row" className="px-2.5 py-2.5 text-left font-semibold text-primary sm:px-4">
                            {t('cabinet.cockpit_general_average')}
                        </th>
                        {averages.terms.map((n) => (
                            <td key={n} className="px-2.5 py-2.5 text-center font-bold text-primary sm:px-4">
                                {averages.general[n] ?? '—'}
                            </td>
                        ))}
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

/** Badge-urile de total absențe (motivate/nemotivate) — antetul secțiunii de absențe. */
export function AbsenceTotals({ motivated, unmotivated }: { motivated: number; unmotivated: number }) {
    const t = useTranslations();

    return (
        <>
            <Badge variant="default" className="bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/15 dark:text-emerald-400">
                {motivated} {t('cabinet.motivated')}
            </Badge>
            <Badge variant="destructive">
                {unmotivated} {t('cabinet.unmotivated')}
            </Badge>
        </>
    );
}

/** Grila absențelor numărate pe disciplină. */
export function AbsencesBySubjectGrid({ absences }: { absences: { subject: string; count: number }[] }) {
    const t = useTranslations();

    if (absences.length === 0) {
        return <EmptyState title={t('cabinet.no_absences')} />;
    }

    return (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {absences.map((a) => (
                <div key={a.subject} className="flex items-center justify-between rounded-lg border bg-card px-4 py-2">
                    <span className="truncate text-sm">{a.subject}</span>
                    <Badge variant="destructive">{a.count}</Badge>
                </div>
            ))}
        </div>
    );
}

/** Panoul de motivări: formularul familiei + istoricul cererilor cu status. */
export function MotivationsPanel({
    studentId,
    motivations,
    canRequest,
}: {
    studentId: number;
    motivations: MotivationItem[];
    canRequest: boolean;
}) {
    const t = useTranslations();

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {canRequest && <MotivationForm studentId={studentId} />}

            <div className="overflow-hidden rounded-xl border">
                {motivations.length === 0 ? (
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
                                    {m.note && (
                                        <p className="mt-1.5 rounded-md bg-muted/60 px-2 py-1.5 text-xs text-muted-foreground">
                                            <span className="font-semibold">{t('cabinet.motivation_note')}:</span>{' '}
                                            {m.note}
                                        </p>
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
    );
}

/** Formularul de motivare (familia). */
export function MotivationForm({ studentId }: { studentId: number }) {
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
