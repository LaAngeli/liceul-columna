import { Form } from '@inertiajs/react';
import { CalendarRange, FileText, Lock, Paperclip } from 'lucide-react';
import { useState } from 'react';
import { localIso } from '@/components/cabinet/catalog/homework-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { gradeLabel } from '@/components/cabinet/student-profile/helpers';
import type { GradeItem } from '@/components/cabinet/student-profile/helpers';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
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

export interface SemesterAveragesMatrix {
    terms: number[];
    rows: { subject: string; values: Record<number, number | null> }[];
    general: Record<number, number | null>;
}

/** Registrul absențelor: contoare pe discipline + fiecare absență cu contextul ei complet. */
export interface AbsenceRegisterData {
    subjects: { id: number; name: string; total: number; unmotivated: number }[];
    absences: {
        id: number;
        date: string;
        weekday: string;
        subjectId: number;
        subject: string;
        teacher: string | null;
        motivated: boolean;
        recordedAt: string | null;
        deadline: string | null;
        deadlinePassed: boolean;
        locked: boolean;
    }[];
    motivated: number;
    unmotivated: number;
}

export interface MotivationItem {
    id: number;
    reason: string;
    period: string;
    status: 'pending' | 'approved' | 'rejected';
    statusLabel: string;
    isException: boolean;
    submittedAt: string | null;
    submittedBy: string | null;
    reviewDeadline: string | null;
    reviewOverdue: boolean;
    decidedAt: string | null;
    decidedBy: string | null;
    note: string | null;
    documentUrl: string | null;
    absencesTotal: number;
    absencesUnmotivated: number;
}

export interface MotivationWindow {
    min: string;
    max: string;
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

/**
 * REGISTRUL absențelor: pastile de disciplină complet clickabile (cu contoare) + lista absențelor
 * disciplinei active, cu tot contextul (zi, profesor, statut, termen de motivare / consolidare).
 * Toate datele sunt deja încărcate → comutarea între discipline e INSTANT, pur client-side.
 */
export function AbsenceRegister({ register }: { register: AbsenceRegisterData }) {
    const t = useTranslations();
    const [activeSubject, setActiveSubject] = useState<number | 'all'>('all');

    if (register.absences.length === 0) {
        return <EmptyState title={t('cabinet.no_absences')} />;
    }

    const visible =
        activeSubject === 'all'
            ? register.absences
            : register.absences.filter((a) => a.subjectId === activeSubject);

    const pillClass = (active: boolean) =>
        cn(
            'inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            active
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
        );

    return (
        <div className="flex flex-col gap-3">
            {/* Selectorul de disciplină — rând orizontal scrollabil (multe discipline → scroll, nu wrap haotic). */}
            <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1" role="group" aria-label={t('cabinet.subject')}>
                <button
                    type="button"
                    onClick={() => setActiveSubject('all')}
                    aria-pressed={activeSubject === 'all'}
                    className={pillClass(activeSubject === 'all')}
                >
                    {t('cabinet.reg_all')}
                    <span
                        className={cn(
                            'inline-flex min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold',
                            activeSubject === 'all' ? 'bg-primary-foreground/20' : 'bg-muted',
                        )}
                    >
                        {register.absences.length}
                    </span>
                </button>
                {register.subjects.map((s) => {
                    const active = activeSubject === s.id;

                    return (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => setActiveSubject(s.id)}
                            aria-pressed={active}
                            className={pillClass(active)}
                        >
                            <span className="max-w-44 truncate">{s.name}</span>
                            <span
                                className={cn(
                                    'inline-flex min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold',
                                    active
                                        ? 'bg-primary-foreground/20'
                                        : s.unmotivated > 0
                                          ? 'bg-destructive/10 text-destructive'
                                          : 'bg-muted',
                                )}
                            >
                                {s.total}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Lista absențelor disciplinei active — cronologic descrescător. */}
            <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                {visible.map((a) => (
                    <li key={a.id} className="flex items-center gap-3 px-3.5 py-2.5">
                        <div className="w-21 shrink-0">
                            <div className="text-sm font-semibold">{a.date}</div>
                            <div className="text-[11px] text-muted-foreground first-letter:uppercase">{a.weekday}</div>
                        </div>
                        <div className="min-w-0 flex-1">
                            {activeSubject === 'all' && <div className="truncate text-sm font-medium">{a.subject}</div>}
                            {a.teacher && <div className="truncate text-xs text-muted-foreground">{a.teacher}</div>}
                            <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-muted-foreground">
                                {a.recordedAt && (
                                    <span>
                                        {t('cabinet.reg_recorded')}: {a.recordedAt}
                                    </span>
                                )}
                                {a.locked && (
                                    <span
                                        className="inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 font-medium text-amber-700 dark:text-amber-300"
                                        title={t('cabinet.reg_locked_hint')}
                                    >
                                        <Lock className="size-3" aria-hidden />
                                        {t('cabinet.reg_locked')}
                                    </span>
                                )}
                                {!a.locked && a.deadline && !a.deadlinePassed && (
                                    <span className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 font-medium">
                                        <CalendarRange className="size-3" aria-hidden />
                                        {t('cabinet.reg_deadline')}: {a.deadline}
                                    </span>
                                )}
                                {!a.locked && a.deadlinePassed && (
                                    <span className="inline-flex items-center gap-1 rounded bg-destructive/10 px-1.5 py-0.5 font-medium text-destructive">
                                        {t('cabinet.reg_deadline_passed')}
                                    </span>
                                )}
                            </div>
                        </div>
                        <span
                            className={cn(
                                'shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold',
                                a.motivated
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-destructive/10 text-destructive',
                            )}
                        >
                            {t(a.motivated ? 'cabinet.motivated' : 'cabinet.unmotivated')}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

const STATUS_ACCENT: Record<MotivationItem['status'], string> = {
    pending: 'border-l-amber-400',
    approved: 'border-l-emerald-500',
    rejected: 'border-l-destructive',
};

const STATUS_BADGE: Record<MotivationItem['status'], string> = {
    pending: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    approved: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    rejected: 'bg-destructive/10 text-destructive',
};

/** Panoul de motivări: formularul familiei + istoricul cererilor; cardul deschide fișa detaliată. */
export function MotivationsPanel({
    studentId,
    motivations,
    canRequest,
    window: motivationWindow,
}: {
    studentId: number;
    motivations: MotivationItem[];
    canRequest: boolean;
    window: MotivationWindow | null;
}) {
    const t = useTranslations();
    const [detail, setDetail] = useState<MotivationItem | null>(null);

    return (
        <div className={cn('grid items-start gap-4', canRequest && 'lg:grid-cols-5')}>
            {canRequest && (
                <div className="lg:col-span-2">
                    <MotivationForm studentId={studentId} window={motivationWindow} />
                </div>
            )}

            <div className={cn(canRequest && 'lg:col-span-3')}>
                {motivations.length === 0 ? (
                    <p className="rounded-xl border bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                        {t('cabinet.motivation_none')}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2.5">
                        {motivations.map((m) => (
                            <li key={m.id}>
                                {/* Cardul întreg e clickabil → fișa cererii (dialog cu cronologie). */}
                                <button
                                    type="button"
                                    onClick={() => setDetail(m)}
                                    className={cn(
                                        'w-full cursor-pointer rounded-xl border border-l-4 bg-card px-4 py-3 text-left shadow-sm transition-shadow',
                                        'hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                        STATUS_ACCENT[m.status],
                                    )}
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={cn('rounded-md px-2 py-0.5 text-xs font-semibold', STATUS_BADGE[m.status])}>
                                            {t(`cabinet.motivation_status_${m.status}`, m.statusLabel)}
                                        </span>
                                        {m.isException && (
                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                                {t('cabinet.motivation_exception')}
                                            </span>
                                        )}
                                        <span className="text-sm font-semibold">{m.period}</span>
                                        {m.documentUrl && <Paperclip className="size-3.5 text-muted-foreground" aria-hidden />}
                                    </div>
                                    <p className="mt-1 truncate text-sm text-muted-foreground">{m.reason}</p>
                                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                        {m.submittedAt && (
                                            <span>
                                                {t('cabinet.motivation_submitted_at')}: {m.submittedAt}
                                            </span>
                                        )}
                                        {m.status === 'pending' && m.reviewDeadline && (
                                            <span className={cn(m.reviewOverdue && 'font-medium text-destructive')}>
                                                {t('cabinet.motivation_review_deadline')}: {m.reviewDeadline}
                                            </span>
                                        )}
                                        {m.decidedBy && (
                                            <span>
                                                {t('cabinet.motivation_decided_at')}: {m.decidedAt} · {m.decidedBy}
                                            </span>
                                        )}
                                    </div>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <MotivationDetailDialog detail={detail} onClose={() => setDetail(null)} />
        </div>
    );
}

/** Fișa detaliată a unei cereri: statut, motiv complet, impact, justificativ + cronologia deciziei. */
function MotivationDetailDialog({ detail, onClose }: { detail: MotivationItem | null; onClose: () => void }) {
    const t = useTranslations();

    return (
        <Dialog open={detail !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                {detail && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex flex-wrap items-center gap-2">
                                {t('cabinet.motivation_detail_title')}
                                <span className={cn('rounded-md px-2 py-0.5 text-xs font-semibold', STATUS_BADGE[detail.status])}>
                                    {t(`cabinet.motivation_status_${detail.status}`, detail.statusLabel)}
                                </span>
                                {detail.isException && (
                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                        {t('cabinet.motivation_exception')}
                                    </span>
                                )}
                            </DialogTitle>
                            <DialogDescription className="font-medium text-foreground">{detail.period}</DialogDescription>
                        </DialogHeader>

                        <div className="flex flex-col gap-4 text-sm">
                            <p className="rounded-lg bg-muted/40 px-3 py-2">{detail.reason}</p>

                            {/* Impactul perioadei — aceleași cifre pe care le vede validatorul. */}
                            <p className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_impact')}: <span className="font-semibold text-foreground">{detail.absencesTotal}</span>
                                {' · '}
                                {t('cabinet.motivation_impact_open')}:{' '}
                                <span className={cn('font-semibold', detail.absencesUnmotivated > 0 ? 'text-destructive' : 'text-foreground')}>
                                    {detail.absencesUnmotivated}
                                </span>
                            </p>

                            {detail.documentUrl && (
                                <a
                                    href={detail.documentUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex w-fit items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium text-primary hover:bg-muted"
                                >
                                    <FileText className="size-3.5" aria-hidden />
                                    {t('cabinet.motivation_document_view')}
                                </a>
                            )}

                            {/* Cronologia cererii — depunere → validare → decizie. */}
                            <div>
                                <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {t('cabinet.motivation_timeline')}
                                </p>
                                <ol className="relative ml-1.5 flex flex-col gap-4 border-l pl-4">
                                    <li>
                                        <span className="absolute -left-[5px] mt-1.5 block size-2.5 rounded-full bg-primary" aria-hidden />
                                        <p className="font-medium">{t('cabinet.motivation_submitted_at')}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {detail.submittedAt}
                                            {detail.submittedBy && ` · ${detail.submittedBy}`}
                                        </p>
                                    </li>
                                    {detail.status === 'pending' ? (
                                        <li>
                                            <span className="absolute -left-[5px] mt-1.5 block size-2.5 rounded-full bg-amber-400" aria-hidden />
                                            <p className="font-medium">{t('cabinet.motivation_in_review')}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {t(detail.isException ? 'cabinet.motivation_in_review_exception' : 'cabinet.motivation_in_review_homeroom')}
                                            </p>
                                            {detail.reviewDeadline && (
                                                <p className={cn('text-xs', detail.reviewOverdue ? 'font-medium text-destructive' : 'text-muted-foreground')}>
                                                    {t('cabinet.motivation_review_deadline')}: {detail.reviewDeadline}
                                                    {detail.reviewOverdue && ` — ${t('cabinet.motivation_review_overdue')}`}
                                                </p>
                                            )}
                                        </li>
                                    ) : (
                                        <li>
                                            <span
                                                className={cn(
                                                    'absolute -left-[5px] mt-1.5 block size-2.5 rounded-full',
                                                    detail.status === 'approved' ? 'bg-emerald-500' : 'bg-destructive',
                                                )}
                                                aria-hidden
                                            />
                                            <p className="font-medium">{t(`cabinet.motivation_status_${detail.status}`, detail.statusLabel)}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {detail.decidedAt}
                                                {detail.decidedBy && ` · ${detail.decidedBy}`}
                                            </p>
                                            {detail.note && (
                                                <p className="mt-1.5 rounded-md bg-muted/60 px-2 py-1.5 text-xs text-muted-foreground">
                                                    <span className="font-semibold">{t('cabinet.motivation_note')}:</span> {detail.note}
                                                </p>
                                            )}
                                        </li>
                                    )}
                                </ol>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * Formularul de motivare (familia) — perioada ÎNTÂI (esența cererii), cu validare live:
 * sfârșitul nu poate preceda începutul (min dinamic + golire automată la invalidare), ambele
 * limitate la anul școlar curent și cel târziu azi (aceleași reguli ca pe server).
 */
export function MotivationForm({ studentId, window: motivationWindow }: { studentId: number; window: MotivationWindow | null }) {
    const t = useTranslations();
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const [reason, setReason] = useState('');
    const [endCleared, setEndCleared] = useState(false);

    const todayIso = localIso(new Date());
    const minDate = motivationWindow?.min;
    const maxDate = motivationWindow?.max ?? todayIso;

    function onStartChange(value: string) {
        setStart(value);

        // Începutul a sărit DUPĂ sfârșit → sfârșitul devine invalid: îl golim și explicăm
        // (nu-l lăsăm să plece spre server doar ca să se întoarcă eroare).
        if (value !== '' && end !== '' && end < value) {
            setEnd('');
            setEndCleared(true);
        }
    }

    return (
        <Form
            {...motivation.form(studentId)}
            resetOnSuccess
            onSuccess={() => {
                setStart('');
                setEnd('');
                setReason('');
                setEndCleared(false);
            }}
            className="rounded-xl border bg-card p-4 shadow-sm"
        >
            {({ processing, errors, recentlySuccessful }) => (
                <div className="flex flex-col gap-3.5">
                    <div className="flex items-baseline justify-between gap-2">
                        <p className="text-sm font-semibold">{t('cabinet.motivation_new')}</p>
                        <p className="text-[11px] text-muted-foreground">{t('cabinet.motivation_required_mark')}</p>
                    </div>

                    {/* 1. PERIOADA — esența cererii; restul câmpurilor o explică.
                        `min-w-0` pe fieldset: implicit are `min-inline-size: min-content`, iar
                        inputurile `type=date` au lățime intrinsecă mare → coloanele s-ar lăți peste
                        card. Coloanele sunt `flex flex-col` (NU grid): într-un grid, celulele se
                        întind la înălțimea rândului, iar coloana cu mai puține elemente își mărea
                        inputul și îl cobora — de aici dezalinierea celor două câmpuri. */}
                    <fieldset className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex min-w-0 flex-col gap-1.5">
                            <label htmlFor="period_start" className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_from')} *
                            </label>
                            <input
                                id="period_start"
                                name="period_start"
                                type="date"
                                required
                                value={start}
                                min={minDate}
                                max={maxDate}
                                onChange={(e) => onStartChange(e.target.value)}
                                className="h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                            />
                            {errors.period_start && <p className="text-xs text-destructive">{errors.period_start}</p>}
                        </div>
                        <div className="flex min-w-0 flex-col gap-1.5">
                            <label htmlFor="period_end" className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_to')} *
                            </label>
                            <input
                                id="period_end"
                                name="period_end"
                                type="date"
                                required
                                value={end}
                                // Sfârșitul pornește de la ÎNCEPUTUL ales — datele anterioare sunt
                                // dezactivate din calendar, nu respinse după trimitere.
                                min={start !== '' ? start : minDate}
                                max={maxDate}
                                disabled={start === ''}
                                title={start === '' ? t('cabinet.motivation_pick_start') : undefined}
                                onChange={(e) => {
                                    setEnd(e.target.value);
                                    setEndCleared(false);
                                }}
                                className="h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            {start === '' && <p className="text-[11px] text-muted-foreground">{t('cabinet.motivation_pick_start')}</p>}
                            {endCleared && (
                                <p className="text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                    {t('cabinet.motivation_end_cleared')}
                                </p>
                            )}
                            {errors.period_end && <p className="text-xs text-destructive">{errors.period_end}</p>}
                        </div>
                        <p className="text-[11px] text-muted-foreground sm:col-span-2">{t('cabinet.motivation_window_hint')}</p>
                    </fieldset>

                    {/* 2. MOTIVUL */}
                    <div className="grid gap-1.5">
                        <div className="flex items-baseline justify-between">
                            <label htmlFor="reason" className="text-xs text-muted-foreground">
                                {t('cabinet.motivation_reason')} *
                            </label>
                            <span className="text-[10px] text-muted-foreground tabular-nums">{reason.length}/1000</span>
                        </div>
                        <textarea
                            id="reason"
                            name="reason"
                            required
                            rows={3}
                            maxLength={1000}
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder={t('cabinet.motivation_reason_ph')}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        />
                        {errors.reason && <p className="text-xs text-destructive">{errors.reason}</p>}
                    </div>

                    {/* 3. DOVADA (opțională) */}
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
