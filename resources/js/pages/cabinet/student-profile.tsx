import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { messages as messageRoutes, motivation, notifications as notificationRoutes } from '@/routes/cabinet';
import { store as requestRoute } from '@/routes/cabinet/requests';

interface StudentSummary {
    id: number;
    name: string;
    class: string | null;
    grades_count: number;
    absences_count: number;
    average: number | null;
}

interface GradeItem {
    value: string | null;
    calificativ: string | null;
    date: string | null;
    term: number | null;
}

interface SubjectGrades {
    subject: string;
    average: number | null;
    items: GradeItem[];
}

interface TranscriptSubject {
    subject: string;
    sem1: string | null;
    sem2: string | null;
    annual: string | null;
}

interface TranscriptLevel {
    grade_level: number;
    subjects: TranscriptSubject[];
}

interface HomeworkItem {
    id: number;
    date: string;
    subject: string;
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
}

interface StudentStatus {
    status: 'promovat' | 'corigent' | 'amanat' | null;
    label: string | null;
    failingSubjects: string[];
}

interface StatusAck {
    needed: boolean;
    acknowledged: boolean;
    acknowledgedAt: string | null;
    canAcknowledge: boolean;
}

interface MotivationItem {
    id: number;
    reason: string;
    period: string;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    documentUrl: string | null;
}

interface DocumentRequestItem {
    id: number;
    type: string;
    date: string | null;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    pdfUrl: string | null;
}

type Trend = 'up' | 'stable' | 'down' | null;

interface DynamicsSubject {
    subject: string;
    points: { level: number; value: number }[];
    trend: Trend;
}

interface Dynamics {
    general: { level: number; average: number }[];
    subjects: DynamicsSubject[];
    current: {
        average: number | null;
        historyAverage: number | null;
        previousYearSameTerm: number | null;
        trend: Trend;
        alert: boolean;
    };
}

interface TimetableCell {
    subject: string;
    teacher: string | null;
    room: string | null;
}

interface TimetableData {
    days: { value: number; label: string; short: string }[];
    maxLesson: number;
    grid: Record<string, TimetableCell>;
}

interface DeferralRisk {
    subject: string;
    absences: number;
    scheduled: number;
}

interface Props {
    student: StudentSummary;
    subjects: SubjectGrades[];
    absencesBySubject: { subject: string; count: number }[];
    absencesTotal: number;
    absencesMotivated: number;
    absencesUnmotivated: number;
    transcript: TranscriptLevel[];
    homework: HomeworkItem[];
    status: StudentStatus;
    statusAck: StatusAck;
    dynamics: Dynamics;
    timetable: TimetableData | null;
    deferralRisk: DeferralRisk[];
    motivations: MotivationItem[];
    documentRequests: DocumentRequestItem[];
    requestTypes: Record<string, string>;
    canRequestMotivation: boolean;
}

function gradeLabel(item: GradeItem): string {
    if (item.value !== null) {
        return String(Number(item.value));
    }
    return item.calificativ ?? '—';
}

function isUrl(value: string): boolean {
    return /^https?:\/\//i.test(value);
}

function motivationStatusClass(status: string): string {
    if (status === 'approved') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }
    if (status === 'rejected') {
        return 'bg-destructive/10 text-destructive';
    }
    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
}

function trendSymbol(trend: Trend): { symbol: string; cls: string } | null {
    if (trend === 'up') {
        return { symbol: '▲', cls: 'text-emerald-600 dark:text-emerald-400' };
    }
    if (trend === 'down') {
        return { symbol: '▼', cls: 'text-destructive' };
    }
    if (trend === 'stable') {
        return { symbol: '▬', cls: 'text-muted-foreground' };
    }
    return null;
}

/** Construiește punctele unei polilinii SVG dintr-o serie de valori (scalată la min/max propriu). */
function sparklinePoints(values: number[], width: number, height: number): string {
    if (values.length === 0) {
        return '';
    }
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const step = values.length > 1 ? width / (values.length - 1) : 0;

    return values
        .map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * height;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

export default function StudentProfile({
    student,
    subjects,
    absencesBySubject,
    absencesTotal,
    absencesMotivated,
    absencesUnmotivated,
    transcript,
    homework,
    status,
    statusAck,
    dynamics,
    timetable,
    deferralRisk,
    motivations,
    documentRequests,
    requestTypes,
    canRequestMotivation,
}: Props) {
    const t = useTranslations();
    const [requestType, setRequestType] = useState('');

    return (
        <>
            <Head title={student.name} />
            <div className="flex flex-col gap-6 p-4">
                {/* Antet */}
                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <span className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-2xl font-semibold text-primary">
                        {student.name.charAt(0)}
                    </span>
                    <div>
                        <h1 className="text-xl font-semibold">{student.name}</h1>
                        <p className="text-sm text-muted-foreground">{student.class ?? t('cabinet.class_unassigned')}</p>
                        {status.status === 'corigent' && (
                            <span className="mt-1.5 inline-flex rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                {t('cabinet.status_corigent')} — {status.failingSubjects.join(', ')}
                            </span>
                        )}
                        {status.status === 'promovat' && (
                            <span className="mt-1.5 inline-flex rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {t('cabinet.status_promovat')}
                            </span>
                        )}
                        {status.status === 'amanat' && (
                            <span className="mt-1.5 inline-flex rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                {t('cabinet.status_amanat')}
                            </span>
                        )}
                    </div>
                    <Link
                        href={notificationRoutes.url()}
                        className="ml-auto inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        {t('cabinet.notif_title')}
                    </Link>
                    <Link
                        href={messageRoutes.url()}
                        className="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                    >
                        {t('cabinet.messages_title')}
                    </Link>
                    <div className="flex gap-6 text-center">
                        <div>
                            <div className="text-2xl font-semibold text-primary">{student.average ?? '—'}</div>
                            <p className="text-xs text-muted-foreground">{t('cabinet.average_general')}</p>
                        </div>
                        <div>
                            <div className="text-2xl font-semibold">{student.grades_count}</div>
                            <p className="text-xs text-muted-foreground">{t('cabinet.grades')}</p>
                        </div>
                        <div>
                            <div className="text-2xl font-semibold">{absencesTotal}</div>
                            <p className="text-xs text-muted-foreground">{t('cabinet.absences')}</p>
                        </div>
                    </div>
                </div>

                {/* Confirmare „am luat cunoștință" de statutul corigent/amânat (spec pct. 108–109) */}
                {statusAck.needed && (
                    <section className="rounded-xl border border-amber-500/50 bg-amber-500/10 p-4">
                        <h2 className="text-sm font-semibold text-amber-800 dark:text-amber-300">{t('cabinet.status_ack_title')}</h2>
                        <p className="mt-1 text-sm text-amber-800/90 dark:text-amber-300/90">{t('cabinet.status_ack_text')}</p>
                        {statusAck.acknowledged ? (
                            <p className="mt-2 inline-flex items-center gap-1.5 rounded-md bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                ✓ {t('cabinet.status_ack_done')} {statusAck.acknowledgedAt}
                            </p>
                        ) : statusAck.canAcknowledge ? (
                            <Form action={`/cabinet/elev/${student.id}/confirm-statut`} method="post" className="mt-3">
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60"
                                    >
                                        {t('cabinet.status_ack_confirm')}
                                    </button>
                                )}
                            </Form>
                        ) : null}
                    </section>
                )}

                {/* Note pe discipline */}
                <section>
                    <h2 className="mb-3 text-lg font-semibold">{t('cabinet.grades_by_subject')}</h2>
                    <details className="mb-3 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2 text-xs dark:border-sidebar-border">
                        <summary className="cursor-pointer font-medium text-muted-foreground">ℹ️ {t('cabinet.info_procedure')}</summary>
                        <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_grades')}</p>
                    </details>
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">{t('cabinet.subject')}</th>
                                    <th className="px-4 py-2 font-medium">{t('cabinet.grades')}</th>
                                    <th className="px-4 py-2 text-right font-medium">{t('cabinet.average')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subjects.map((s) => (
                                    <tr key={s.subject} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                        <td className="px-4 py-3 font-medium">{s.subject}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {s.items.map((item, i) => (
                                                    <span
                                                        key={i}
                                                        className="inline-flex min-w-7 items-center justify-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                                                        title={item.date ?? undefined}
                                                    >
                                                        {gradeLabel(item)}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold">{s.average ?? '—'}</td>
                                    </tr>
                                ))}
                                {subjects.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-6 text-center text-muted-foreground">
                                            {t('cabinet.no_grades')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* Absențe */}
                {absencesTotal > 0 && (
                    <section>
                        <div className="mb-3 flex flex-wrap items-center gap-3">
                            <h2 className="text-lg font-semibold">{t('cabinet.absences_by_subject')}</h2>
                            <span className="rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {absencesMotivated} {t('cabinet.motivated')}
                            </span>
                            <span className="rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                {absencesUnmotivated} {t('cabinet.unmotivated')}
                            </span>
                        </div>
                        <details className="mb-3 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2 text-xs dark:border-sidebar-border">
                            <summary className="cursor-pointer font-medium text-muted-foreground">ℹ️ {t('cabinet.info_procedure')}</summary>
                            <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_absences')}</p>
                        </details>
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {absencesBySubject.map((a) => (
                                <div
                                    key={a.subject}
                                    className="flex items-center justify-between rounded-lg border border-sidebar-border/70 bg-card px-4 py-2 dark:border-sidebar-border"
                                >
                                    <span className="truncate text-sm">{a.subject}</span>
                                    <span className="ml-2 rounded-md bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">{a.count}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Risc de amânare (≤1 notă + >50% absențe din lecțiile programate) */}
                {deferralRisk.length > 0 && (
                    <section className="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
                        <h2 className="mb-2 text-lg font-semibold text-amber-700 dark:text-amber-400">⚠️ {t('cabinet.deferral_title')}</h2>
                        <p className="mb-3 text-sm text-amber-800/90 dark:text-amber-300/90">{t('cabinet.deferral_hint')}</p>
                        <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {deferralRisk.map((r) => (
                                <li key={r.subject} className="rounded-lg border border-amber-500/40 bg-card px-4 py-2 text-sm">
                                    <span className="font-medium">{r.subject}</span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        {r.absences} / {r.scheduled} {t('cabinet.deferral_lessons')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {/* Orar structurat al clasei (navigabil) */}
                {timetable && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.timetable_title')}</h2>
                        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">{t('cabinet.timetable_lesson')}</th>
                                        {timetable.days.map((d) => (
                                            <th key={d.value} className="px-3 py-2 text-center font-medium" title={d.label}>
                                                <span className="hidden sm:inline">{d.label}</span>
                                                <span className="sm:hidden">{d.short}</span>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {Array.from({ length: timetable.maxLesson }, (_, i) => i + 1).map((num) => (
                                        <tr key={num} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                            <td className="px-3 py-2 font-semibold text-muted-foreground">{num}</td>
                                            {timetable.days.map((d) => {
                                                const cell = timetable.grid[`${d.value}-${num}`];
                                                return (
                                                    <td key={d.value} className="px-3 py-2 text-center align-top">
                                                        {cell ? (
                                                            <div className="flex flex-col">
                                                                <span className="font-medium">{cell.subject}</span>
                                                                {cell.teacher && <span className="text-xs text-muted-foreground">{cell.teacher}</span>}
                                                                {cell.room && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {t('cabinet.timetable_room')} {cell.room}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground/40">—</span>
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {/* Motivarea absențelor (familia depune, dirigintele validează) */}
                {(canRequestMotivation || motivations.length > 0) && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.motivations_title')}</h2>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {canRequestMotivation && (
                                <Form
                                    {...motivation.form(student.id)}
                                    resetOnSuccess
                                    className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
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
                                                    {errors.period_start && (
                                                        <p className="text-xs text-destructive">{errors.period_start}</p>
                                                    )}
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
                                                    {errors.period_end && (
                                                        <p className="text-xs text-destructive">{errors.period_end}</p>
                                                    )}
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
                                                className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                                            >
                                                {processing ? t('cabinet.motivation_sending') : t('cabinet.motivation_submit')}
                                            </button>
                                            {recentlySuccessful && (
                                                <p className="rounded-md bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    {t('cabinet.motivation_sent')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </Form>
                            )}

                            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                {motivations.length === 0 ? (
                                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                        {t('cabinet.motivation_none')}
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                        {motivations.map((m) => (
                                            <li key={m.id} className="flex items-start justify-between gap-3 px-4 py-3">
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium">{m.period}</p>
                                                    <p className="truncate text-xs text-muted-foreground">{m.reason}</p>
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

                {/* Cereri tipice (familia depune → PDF → secretariat, §4.3) */}
                {(canRequestMotivation || documentRequests.length > 0) && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.requests_title')}</h2>
                        <details className="mb-3 rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2 text-xs dark:border-sidebar-border">
                            <summary className="cursor-pointer font-medium text-muted-foreground">ℹ️ {t('cabinet.info_procedure')}</summary>
                            <p className="mt-1.5 text-muted-foreground">{t('cabinet.extract_requests')}</p>
                        </details>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {canRequestMotivation && (
                                <Form
                                    {...requestRoute.form(student.id)}
                                    resetOnSuccess
                                    onSuccess={() => setRequestType('')}
                                    className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
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
                                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
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
                                                className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                                            >
                                                {processing ? t('cabinet.motivation_sending') : t('cabinet.requests_submit')}
                                            </button>
                                            {recentlySuccessful && (
                                                <p className="rounded-md bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    {t('cabinet.requests_sent')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </Form>
                            )}

                            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                {documentRequests.length === 0 ? (
                                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                        {t('cabinet.requests_none')}
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
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

                {/* Foaie matricolă */}
                {transcript.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.transcript')}</h2>
                        <div className="flex flex-col gap-3">
                            {transcript.map((level, idx) => (
                                <details
                                    key={level.grade_level}
                                    open={idx === 0}
                                    className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <summary className="cursor-pointer bg-muted/50 px-4 py-2 text-sm font-medium">
                                        {t('cabinet.class')} {level.grade_level}
                                    </summary>
                                    <table className="w-full text-sm">
                                        <thead className="text-left text-muted-foreground">
                                            <tr className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                                <th className="px-4 py-2 font-medium">{t('cabinet.subject')}</th>
                                                <th className="px-4 py-2 text-center font-medium">{t('cabinet.sem1')}</th>
                                                <th className="px-4 py-2 text-center font-medium">{t('cabinet.sem2')}</th>
                                                <th className="px-4 py-2 text-center font-medium">{t('cabinet.annual')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {level.subjects.map((s) => (
                                                <tr key={s.subject} className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                                    <td className="px-4 py-2 font-medium">{s.subject}</td>
                                                    <td className="px-4 py-2 text-center">{s.sem1 ?? '—'}</td>
                                                    <td className="px-4 py-2 text-center">{s.sem2 ?? '—'}</td>
                                                    <td className="px-4 py-2 text-center font-semibold">{s.annual ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </details>
                            ))}
                        </div>
                    </section>
                )}

                {/* Dinamică & evoluție (spec §2.2/§2.3) — raportat la istoricul PROPRIU */}
                {(dynamics.general.length > 0 || dynamics.current.average !== null) && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.dynamics_title')}</h2>

                        {dynamics.current.alert && (
                            <p className="mb-3 rounded-lg bg-destructive/10 px-4 py-2 text-sm font-medium text-destructive">
                                {t('cabinet.dynamics_alert')}
                            </p>
                        )}

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                                <div className="flex flex-wrap items-end gap-5">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('cabinet.dynamics_current')}</p>
                                        <p className="text-2xl font-semibold text-primary">
                                            {dynamics.current.average ?? '—'}
                                            {(() => {
                                                const tr = trendSymbol(dynamics.current.trend);
                                                return tr ? <span className={`ml-1 text-base ${tr.cls}`}>{tr.symbol}</span> : null;
                                            })()}
                                        </p>
                                    </div>
                                    {dynamics.current.historyAverage !== null && (
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('cabinet.dynamics_history')}</p>
                                            <p className="text-lg font-medium">{dynamics.current.historyAverage}</p>
                                        </div>
                                    )}
                                    {dynamics.current.previousYearSameTerm !== null && (
                                        <div>
                                            <p className="text-xs text-muted-foreground">{t('cabinet.dynamics_vs_last_year')}</p>
                                            <p className="text-lg font-medium">{dynamics.current.previousYearSameTerm}</p>
                                        </div>
                                    )}
                                </div>

                                {dynamics.general.length > 1 && (
                                    <div className="mt-4">
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
                                                    {t('cabinet.class')} {g.level}:{' '}
                                                    <span className="font-semibold">{g.average}</span>
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {dynamics.subjects.length > 0 && (
                                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                    <p className="border-b border-sidebar-border/70 bg-muted/50 px-4 py-2 text-sm font-medium dark:border-sidebar-border">
                                        {t('cabinet.dynamics_by_subject')}
                                    </p>
                                    <ul className="max-h-72 divide-y divide-sidebar-border/70 overflow-y-auto dark:divide-sidebar-border">
                                        {dynamics.subjects.map((s) => {
                                            const tr = trendSymbol(s.trend);
                                            const last = s.points[s.points.length - 1];
                                            return (
                                                <li key={s.subject} className="flex items-center justify-between gap-3 px-4 py-2">
                                                    <span className="truncate text-sm">{s.subject}</span>
                                                    <span className="flex items-center gap-2">
                                                        <span className="text-sm font-semibold">{last ? last.value : '—'}</span>
                                                        {tr && (
                                                            <span
                                                                className={`text-xs ${tr.cls}`}
                                                                title={t(`cabinet.dynamics_trend_${s.trend}`)}
                                                            >
                                                                {tr.symbol}
                                                            </span>
                                                        )}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* Teme */}
                {homework.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold">{t('cabinet.homework')}</h2>
                        <div className="flex flex-col gap-3">
                            {homework.map((h) => (
                                <article
                                    key={h.id}
                                    className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                                >
                                    <div className="mb-1 flex flex-wrap items-center gap-2">
                                        <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                                        <span className="text-xs text-muted-foreground">{h.date}</span>
                                    </div>
                                    {h.topic && <p className="font-medium">{h.topic}</p>}
                                    {h.required && (
                                        <p className="mt-1 text-sm">
                                            <span className="text-muted-foreground">{t('cabinet.required')} </span>
                                            {h.required}
                                        </p>
                                    )}
                                    {h.optional && (
                                        <p className="mt-1 text-sm">
                                            <span className="text-muted-foreground">{t('cabinet.optional')} </span>
                                            {h.optional}
                                        </p>
                                    )}
                                    {h.links.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {h.links.map((link, i) =>
                                                isUrl(link) ? (
                                                    <a
                                                        key={i}
                                                        href={link}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="rounded-md bg-muted px-2 py-0.5 text-xs text-primary underline-offset-2 hover:underline"
                                                    >
                                                        {t('cabinet.link')} {i + 1}
                                                    </a>
                                                ) : (
                                                    <span key={i} className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                        {link}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

StudentProfile.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.profile', href: '#' },
    ],
};
