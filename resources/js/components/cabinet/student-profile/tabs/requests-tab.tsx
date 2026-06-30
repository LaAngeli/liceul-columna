import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { motivationStatusClass } from '@/components/cabinet/student-profile/helpers';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';
import { store as requestRoute } from '@/routes/cabinet/requests';

interface DocumentRequestItem {
    id: number;
    type: string;
    date: string | null;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    pdfUrl: string | null;
}

interface CorigentaExamItem {
    id: number;
    subject: string;
    season: string;
    scheduledOn: string | null;
    commission: string | null;
    sessionType: string | null;
    passed: boolean | null;
}

/**
 * Tab Cereri — cereri tipice (formular + listă + PDF) + calendar lichidare corigență.
 */
export function RequestsTab({
    studentId,
    documentRequests,
    corigentaExams,
    requestTypes,
    canRequestMotivation,
}: {
    studentId: number;
    documentRequests?: DocumentRequestItem[];
    corigentaExams?: CorigentaExamItem[];
    requestTypes: Record<string, string>;
    canRequestMotivation: boolean;
}) {
    const t = useTranslations();
    const [requestType, setRequestType] = useState('');

    return (
        <div className="flex flex-col gap-6">
            {/* === CALENDAR LICHIDARE CORIGENȚĂ === */}
            {corigentaExams !== undefined && corigentaExams.length > 0 && (
                <section className="rounded-xl border bg-card p-4 shadow-sm">
                    <SectionHeading title={t('cabinet.corigenta_title')} className="mb-2" />
                    <ul className="divide-y">
                        {corigentaExams.map((e) => (
                            <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">{e.subject}</span>
                                    {/* Badge rezultat — apare DOAR după ce comisia înregistrează rezultatul (passed !== null). */}
                                    {e.passed !== null && (
                                        <span
                                            className={`rounded-md px-2 py-0.5 text-xs font-semibold ${
                                                e.passed
                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                    : 'bg-red-500/10 text-red-700 dark:text-red-400'
                                            }`}
                                        >
                                            {e.passed ? t('cabinet.corigenta_passed') : t('cabinet.corigenta_failed')}
                                        </span>
                                    )}
                                </div>
                                <span className="text-xs text-muted-foreground">
                                    {e.sessionType ? `${e.sessionType} · ` : ''}
                                    {e.season}
                                    {e.scheduledOn
                                        ? ` · ${e.scheduledOn}`
                                        : ` · ${t('cabinet.corigenta_unscheduled')}`}
                                    {e.commission ? ` · ${e.commission}` : ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {/* === CERERI TIPICE === */}
            {(canRequestMotivation || (documentRequests && documentRequests.length > 0)) && (
                <section>
                    <SectionHeading title={t('cabinet.requests_title')} />
                    <details className="mb-3 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                        <summary className="cursor-pointer font-medium text-muted-foreground">ℹ️ {t('cabinet.info_procedure')}</summary>
                        <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_requests')}</p>
                    </details>

                    <div className="grid gap-4 lg:grid-cols-2">
                        {canRequestMotivation && (
                            <Form
                                {...requestRoute.form(studentId)}
                                resetOnSuccess
                                onSuccess={() => setRequestType('')}
                                className="rounded-xl border bg-card p-4 shadow-sm"
                            >
                                {({ processing, errors, recentlySuccessful }) => (
                                    <div className="flex flex-col gap-3">
                                        <p className="text-sm font-medium">{t('cabinet.requests_new')}</p>

                                        <div className="grid gap-1.5">
                                            <label htmlFor="req_type" className="text-xs text-muted-foreground">
                                                {t('cabinet.requests_type')}
                                            </label>
                                            <select
                                                id="req_type"
                                                name="type"
                                                required
                                                value={requestType}
                                                onChange={(e) => setRequestType(e.target.value)}
                                                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="">—</option>
                                                {Object.entries(requestTypes).map(([value, label]) => (
                                                    <option key={value} value={value}>
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
                                        </div>

                                        {requestType === 'invoire' && (
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="req_start" className="text-xs text-muted-foreground">
                                                        {t('cabinet.motivation_from')}
                                                    </label>
                                                    <input
                                                        id="req_start"
                                                        name="period_start"
                                                        type="date"
                                                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    />
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="req_end" className="text-xs text-muted-foreground">
                                                        {t('cabinet.motivation_to')}
                                                    </label>
                                                    <input
                                                        id="req_end"
                                                        name="period_end"
                                                        type="date"
                                                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid gap-1.5">
                                            <label htmlFor="req_details" className="text-xs text-muted-foreground">
                                                {t('cabinet.requests_details')}
                                            </label>
                                            <textarea
                                                id="req_details"
                                                name="details"
                                                rows={3}
                                                placeholder={t('cabinet.requests_details_ph')}
                                                maxLength={1500}
                                                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            />
                                            {errors.details && <p className="text-xs text-destructive">{errors.details}</p>}
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing || requestType === ''}
                                            className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                                        >
                                            {processing ? t('cabinet.motivation_sending') : t('cabinet.requests_submit')}
                                        </button>
                                        {recentlySuccessful && (
                                            <p className="rounded-md bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                                {t('cabinet.requests_sent')}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </Form>
                        )}

                        <div className="overflow-hidden rounded-xl border">
                            {documentRequests === undefined ? (
                                <SkeletonTable rows={3} />
                            ) : documentRequests.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    {t('cabinet.requests_none')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {documentRequests.map((r) => (
                                        <li key={r.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{r.type}</p>
                                                <p className="text-xs text-muted-foreground">{r.date}</p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span
                                                    className={`rounded-md px-2 py-0.5 text-xs font-semibold ${motivationStatusClass(r.status)}`}
                                                >
                                                    {t(`cabinet.motivation_status_${r.status}`, r.statusLabel)}
                                                </span>
                                                {r.pdfUrl && (
                                                    <a
                                                        href={r.pdfUrl}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-primary hover:underline"
                                                    >
                                                        PDF
                                                    </a>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </section>
            )}

            {/* Caz „totul gol" (fără cereri ȘI fără corigență) */}
            {(documentRequests === undefined && corigentaExams === undefined) === false &&
                !canRequestMotivation &&
                (!documentRequests || documentRequests.length === 0) &&
                (!corigentaExams || corigentaExams.length === 0) && (
                    <EmptyState title={t('cabinet.requests_none')} />
                )}
        </div>
    );
}
