import { Form, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { motivationStatusClass } from '@/components/cabinet/student-profile/helpers';
import { SkeletonTable } from '@/components/cabinet/student-profile/skeletons';
import { useTranslations } from '@/lib/i18n';
import { store as requestRoute, withdraw as withdrawRoute } from '@/routes/cabinet/requests';

interface DocumentRequestItem {
    id: number;
    type: string;
    date: string | null;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    pdfUrl: string | null;
    /** Justificativul atașat la depunere (dacă există). */
    attachmentUrl?: string | null;
    note: string | null;
    /** Nota contestată (snapshot din depunere) — doar la contestații. */
    grade?: string | null;
    /** Familia își poate retrage cererea cât e încă neprocesată. */
    canWithdraw?: boolean;
}

interface CorigentaExamItem {
    id: number;
    subject: string;
    season: string;
    scheduledOn: string | null;
    commission: string | null;
    sessionType: string | null;
    mark: string | null;
    passed: boolean | null;
}

/**
 * Tab Cereri — cereri tipice (formular + listă + PDF) + calendar lichidare corigență.
 * Contestația se depune CU nota vizată (select obligatoriu); un chip de notă din tabul Situație
 * poate pre-completa formularul (contestIntent).
 */
export function RequestsTab({
    studentId,
    documentRequests,
    documentRequestsTotal,
    corigentaExams,
    requestTypes,
    canRequestMotivation,
    contestableGrades,
    contestIntent,
}: {
    studentId: number;
    documentRequests?: DocumentRequestItem[];
    /** Totalul real — lista e plafonată la cele mai recente 15 (indicator de trunchiere). */
    documentRequestsTotal?: number;
    corigentaExams?: CorigentaExamItem[];
    requestTypes: Record<string, string>;
    canRequestMotivation: boolean;
    contestableGrades?: { id: number; label: string }[];
    /** Pre-selecția venită din chip-ul unei note (token crește la fiecare click — re-aplicabil). */
    contestIntent?: { gradeId: number; token: number } | null;
}) {
    const t = useTranslations();
    const [requestType, setRequestType] = useState('');
    const [gradeId, setGradeId] = useState('');

    useEffect(() => {
        if (contestIntent) {
            setRequestType('contestatie');
            setGradeId(String(contestIntent.gradeId));
        }
    }, [contestIntent]);

    // Placeholderele ghidează CE se scrie la fiecare tip — detaliile sunt obligatorii peste tot
    // (o cerere fără motiv/destinație/temă e neprocesabilă).
    const detailPlaceholders: Record<string, string> = {
        invoire: t('cabinet.requests_details_ph_invoire'),
        adeverinta: t('cabinet.requests_details_ph_adeverinta'),
        transfer: t('cabinet.requests_details_ph_transfer'),
        sedinta: t('cabinet.requests_details_ph_sedinta'),
        contestatie: t('cabinet.requests_details_contestation_ph'),
    };
    const today = new Date().toISOString().slice(0, 10);

    function withdrawRequest(id: number) {
        if (window.confirm(t('cabinet.requests_withdraw_confirm'))) {
            router.post(withdrawRoute(id).url, {}, { preserveScroll: true });
        }
    }

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
                                            {e.mark !== null ? ` · ${e.mark}` : ''}
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
                                onSuccess={() => {
                                    setRequestType('');
                                    setGradeId('');
                                }}
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

                                        {/* Contestația se depune CU nota vizată — disciplina, valoarea, data și
                                            profesorul vin din notă (zero re-tastare); serverul re-validează. */}
                                        {requestType === 'contestatie' && (
                                            <div className="grid gap-1.5">
                                                <label htmlFor="req_grade" className="text-xs text-muted-foreground">
                                                    {t('cabinet.requests_grade')}
                                                    <span className="text-destructive"> *</span>
                                                </label>
                                                <select
                                                    id="req_grade"
                                                    name="grade_id"
                                                    required
                                                    value={gradeId}
                                                    onChange={(e) => setGradeId(e.target.value)}
                                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                >
                                                    <option value="">—</option>
                                                    {(contestableGrades ?? []).map((g) => (
                                                        <option key={g.id} value={g.id}>
                                                            {g.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                {contestableGrades !== undefined && contestableGrades.length === 0 && (
                                                    <p className="text-xs text-muted-foreground">{t('cabinet.requests_grade_none')}</p>
                                                )}
                                                {errors.grade_id && <p className="text-xs text-destructive">{errors.grade_id}</p>}
                                            </div>
                                        )}

                                        {requestType === 'invoire' && (
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="req_start" className="text-xs text-muted-foreground">
                                                        {t('cabinet.motivation_from')}
                                                    </label>
                                                    {/* Învoirea e prospectivă — pentru trecut există motivarea absențelor. */}
                                                    <input
                                                        id="req_start"
                                                        name="period_start"
                                                        type="date"
                                                        required
                                                        min={today}
                                                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    />
                                                    {errors.period_start && (
                                                        <p className="text-xs text-destructive">{errors.period_start}</p>
                                                    )}
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="req_end" className="text-xs text-muted-foreground">
                                                        {t('cabinet.motivation_to')}
                                                    </label>
                                                    <input
                                                        id="req_end"
                                                        name="period_end"
                                                        type="date"
                                                        required
                                                        min={today}
                                                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                    />
                                                    {errors.period_end && (
                                                        <p className="text-xs text-destructive">{errors.period_end}</p>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid gap-1.5">
                                            <label htmlFor="req_details" className="text-xs text-muted-foreground">
                                                {t('cabinet.requests_details')}
                                                {requestType !== '' && <span className="text-destructive"> *</span>}
                                            </label>
                                            <textarea
                                                id="req_details"
                                                name="details"
                                                rows={3}
                                                required={requestType !== ''}
                                                placeholder={detailPlaceholders[requestType] ?? t('cabinet.requests_details_ph')}
                                                maxLength={1500}
                                                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            />
                                            {errors.details && <p className="text-xs text-destructive">{errors.details}</p>}
                                        </div>

                                        <div className="grid gap-1.5">
                                            <label htmlFor="req_attachment" className="text-xs text-muted-foreground">
                                                {t('cabinet.requests_attachment')}
                                            </label>
                                            <input
                                                id="req_attachment"
                                                name="attachment"
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                className="rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                            />
                                            {errors.attachment && <p className="text-xs text-destructive">{errors.attachment}</p>}
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
                                        <li key={r.id} className="px-4 py-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{r.type}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {[r.date, r.grade].filter(Boolean).join(' · ')}
                                                    </p>
                                                </div>
                                                {/* Țintele tactile ale acțiunilor ≥28px (min-h-7) — chip-urile de
                                                    20px erau sub minimul WCAG pe mobil; flex-wrap: pe ecrane
                                                    înguste acțiunile coboară pe rândul următor, fără overflow. */}
                                                <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                                    <span
                                                        className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold ${motivationStatusClass(r.status)}`}
                                                    >
                                                        {t(`cabinet.motivation_status_${r.status}`, r.statusLabel)}
                                                    </span>
                                                    {r.pdfUrl && (
                                                        <a
                                                            href={r.pdfUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex min-h-7 items-center rounded-md bg-muted px-2.5 text-xs font-medium text-primary hover:underline"
                                                        >
                                                            PDF
                                                        </a>
                                                    )}
                                                    {r.attachmentUrl && (
                                                        <a
                                                            href={r.attachmentUrl}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex min-h-7 items-center rounded-md bg-muted px-2.5 text-xs font-medium text-primary hover:underline"
                                                        >
                                                            📎 {t('cabinet.requests_attachment_view')}
                                                        </a>
                                                    )}
                                                    {r.canWithdraw && (
                                                        <button
                                                            type="button"
                                                            onClick={() => withdrawRequest(r.id)}
                                                            className="inline-flex min-h-7 items-center rounded-md px-2.5 text-xs font-medium text-destructive hover:bg-destructive/10"
                                                        >
                                                            {t('cabinet.requests_withdraw')}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                            {/* Comentariul secretariatului (procesare/respingere) — familia vede DE CE. */}
                                            {r.note && (
                                                <p className="mt-1.5 rounded-md bg-muted/50 px-2.5 py-1.5 text-xs text-muted-foreground">
                                                    <span className="font-medium">{t('cabinet.requests_note')}:</span> {r.note}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {/* Lista e plafonată la cele mai recente 15 — anunțăm dacă sunt mai
                                multe (paritate cu pagina Documente; deferred #37 închis). */}
                            {documentRequests !== undefined &&
                                documentRequestsTotal !== undefined &&
                                documentRequestsTotal > documentRequests.length && (
                                    <p className="border-t px-4 py-2 text-xs text-muted-foreground">
                                        {t('cabinet.documents_requests_truncated')
                                            .replace('{shown}', String(documentRequests.length))
                                            .replace('{total}', String(documentRequestsTotal))}
                                    </p>
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
