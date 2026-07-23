import { CalendarClock, ChevronRight, ListOrdered, TriangleAlert, UserRound } from 'lucide-react';
import { useState } from 'react';
import { FilterPills } from '@/components/cabinet/catalog/filter-pills';
import { SemesterAveragesTable } from '@/components/cabinet/catalog/situation-views';
import type { SemesterAveragesMatrix } from '@/components/cabinet/catalog/situation-views';
import { EmptyState } from '@/components/cabinet/empty-state';
import { sparklinePoints, trendSymbol } from '@/components/cabinet/student-profile/helpers';
import type { Trend } from '@/components/cabinet/student-profile/helpers';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { pluralKey, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * CATALOGUL familiei — vederile modulului „Note" (elev + părinte).
 *
 * Ce rezolvă față de tabelul dinainte:
 *   • **data fiecărei note e VIZIBILĂ.** Stătea într-un `title`, adică într-un tooltip — pe telefon,
 *     unde nu există hover, era pur și simplu inaccesibilă. Acum fiecare notă e o casetă cu valoarea
 *     deasupra și ziua dedesubt.
 *   • **notele se citesc pe semestru.** Înainte chip-urile din Sem. I și Sem. II stăteau amestecate
 *     sub o medie care descria doar semestrul curent.
 *   • **evoluția e la vedere, nu după încă un click:** fiecare disciplină își poartă traiectoria
 *     (sparkline + tendință) chiar pe card.
 *
 * Publicul e format din NEspecialiști: nicio acțiune de operare, niciun jargon de catalog, iar
 * culoarea semnalează un singur lucru obiectiv — media sub pragul de promovare (§3).
 */

export interface GradeBookTerm {
    number: number;
    label: string;
    current: boolean;
}

/** Statistica unei discipline într-un semestru anume. */
export interface GradeBookSubjectTerm {
    /** Media semestrială OFICIALĂ (term_averages) — nu recalculată în UI. */
    average: number | null;
    /** Componentele MS (§1.3): media curentelor + sumativa semestrială. */
    mc: number | null;
    summative: number | null;
    count: number;
    /** Valorile numerice, cronologic ASC — baza sparkline-ului. */
    series: number[];
    trend: Trend;
    lastDate: string | null;
    /** Media sub pragul de promovare → disciplină restantă. */
    risk: boolean;
}

export interface GradeBookSubject {
    id: number;
    name: string;
    terms: Record<number, GradeBookSubjectTerm>;
    /** Profesorul disciplinei, din alocări (gol când alocarea e ambiguă — grupe). */
    teachers: string[];
}

export interface GradeBookEntry {
    id: number;
    subjectId: number;
    subject: string;
    term: number;
    /** Ce se afișează: nota sau calificativul (primar). */
    label: string;
    /** Ce se poate calcula: null la calificative. */
    value: number | null;
    date: string;
    iso: string;
    weekday: string;
    monthLabel: string;
    typeLabel: string;
    isSummative: boolean;
    /** Cine a consemnat nota — doar când nota însăși o poartă. */
    teacher: string | null;
    recordedAt: string | null;
}

export interface GradeBookSummary {
    average: number | null;
    trend: Trend;
    previousAverage: number | null;
    gradesCount: number;
    subjectsCount: number;
    riskCount: number;
    lastDate: string | null;
}

export interface GradeBookData {
    terms: GradeBookTerm[];
    currentTerm: number | null;
    subjects: GradeBookSubject[];
    grades: GradeBookEntry[];
    summary: Record<number, GradeBookSummary>;
}

/** Media sub prag e singurul lucru pe care interfața îl colorează — restul rămâne neutru. */
function averageClass(average: number | null, risk: boolean): string {
    if (average === null) {
        return 'text-muted-foreground';
    }

    return risk ? 'text-destructive' : 'text-foreground';
}

/** Traiectoria notelor dintr-un semestru, ca linie mică (fără bibliotecă de grafice). */
function Sparkline({ values, className }: { values: number[]; className?: string }) {
    if (values.length < 2) {
        return null;
    }

    return (
        <svg viewBox="0 0 100 24" width="100" height="24" preserveAspectRatio="none" className={cn('h-6 w-full', className)} aria-hidden>
            <polyline points={sparklinePoints(values, 100, 24)} fill="none" stroke="currentColor" strokeWidth="1.5" vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

/** Săgeata de tendință + eticheta ei citibilă de screen-reader. */
function TrendMark({ trend }: { trend: Trend }) {
    const t = useTranslations();
    const mark = trendSymbol(trend);

    if (mark === null || trend === null) {
        return null;
    }

    const label = t(`cabinet.dynamics_trend_${trend}`);

    return (
        <span className={cn('text-xs', mark.cls)} title={label}>
            <span aria-hidden>{mark.symbol}</span>
            <span className="sr-only">{label}</span>
        </span>
    );
}

/**
 * O notă, ca în catalogul de hârtie: valoarea mare, ZIUA dedesubt. Data e informația pe care
 * părintele o caută („când a luat-o?") — de aceea e text, nu tooltip.
 */
function GradeCell({ entry, className }: { entry: GradeBookEntry; className?: string }) {
    const t = useTranslations();
    // Ziua+luna ajung pentru orientare în interiorul semestrului; anul ar dubla lățimea casetei.
    const shortDate = entry.date.slice(0, 5);

    return (
        <span
            className={cn(
                'inline-flex min-h-11 min-w-11 flex-col items-center justify-center rounded-md px-1.5 py-1 leading-none',
                entry.isSummative
                    ? 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/40 dark:text-amber-300'
                    : 'bg-primary/10 text-primary',
                className,
            )}
            title={`${entry.typeLabel} · ${entry.date}${entry.teacher ? ` · ${entry.teacher}` : ''}`}
        >
            <span className="text-sm font-semibold">{entry.label}</span>
            <span className="mt-0.5 text-[10px] font-normal opacity-70">{shortDate}</span>
            <span className="sr-only">
                {entry.typeLabel}, {entry.date}
                {entry.isSummative ? `, ${t('cabinet.gb_summative')}` : ''}
            </span>
        </span>
    );
}

/** Catalogul complet: semestru → sinteză → vedere (discipline / cronologic). */
export function GradeBook({
    data,
    onContestGrade,
}: {
    data: GradeBookData;
    /** Familia poate porni o contestație din fișa disciplinei (pre-completează cererea). */
    onContestGrade?: (gradeId: number) => void;
}) {
    const t = useTranslations();
    const [term, setTerm] = useState<number>(() => data.currentTerm ?? data.terms[0]?.number ?? 1);
    const [view, setView] = useState<'subjects' | 'journal'>('subjects');
    // Doar ID-ul: la comutarea copilului lista se schimbă, iar un obiect memorat ar rămâne al
    // copilului anterior. Fișa se rezolvă la fiecare randare din datele curente.
    const [detailId, setDetailId] = useState<number | null>(null);

    if (data.terms.length === 0) {
        return <EmptyState title={t('cabinet.no_grades')} />;
    }

    const summary = data.summary[term] ?? null;
    const entries = data.grades.filter((g) => g.term === term);
    const subjects = data.subjects.filter((s) => s.terms[term] !== undefined);
    const detail = subjects.find((s) => s.id === detailId) ?? null;

    return (
        <div className="flex flex-col gap-4">
            {/* Semestrul — axa după care se citește tot restul. */}
            {data.terms.length > 1 && (
                <div className="flex flex-wrap gap-1.5" role="group" aria-label={t('cabinet.gb_term')}>
                    {data.terms.map((item) => (
                        <button
                            key={item.number}
                            type="button"
                            onClick={() => setTerm(item.number)}
                            aria-pressed={term === item.number}
                            className={cn(
                                'inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                term === item.number
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            {item.label}
                            {item.current && (
                                <span className="rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold">
                                    {t('cabinet.gb_term_current')}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}

            {summary !== null && <SummaryBand summary={summary} />}

            {entries.length === 0 && subjects.length === 0 ? (
                <EmptyState title={t('cabinet.gb_no_grades_term')} />
            ) : (
                <>
                    {/* Aceleași date, două citiri: „la ce cum stă" vs „ce s-a întâmplat recent".
                        Comutarea e pur client-side — datele ambelor vederi sunt deja aici. */}
                    <div className="flex justify-end">
                        <div className="flex rounded-lg border p-0.5" role="group" aria-label={t('cabinet.gb_view')}>
                            {[
                                { value: 'subjects' as const, label: t('cabinet.gb_view_subjects'), icon: ListOrdered },
                                { value: 'journal' as const, label: t('cabinet.gb_view_journal'), icon: CalendarClock },
                            ].map((mode) => (
                                <button
                                    key={mode.value}
                                    type="button"
                                    onClick={() => setView(mode.value)}
                                    aria-pressed={view === mode.value}
                                    className={cn(
                                        'inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-md px-3 text-xs font-medium transition-colors',
                                        view === mode.value ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <mode.icon className="size-3.5" aria-hidden />
                                    {mode.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {view === 'subjects' ? (
                        <SubjectCards subjects={subjects} entries={entries} term={term} onOpen={setDetailId} />
                    ) : (
                        <GradeJournal entries={entries} subjects={subjects} term={term} />
                    )}
                </>
            )}

            <SubjectDetailDialog
                subject={detail}
                term={term}
                entries={detail ? entries.filter((g) => g.subjectId === detail.id) : []}
                onClose={() => setDetailId(null)}
                onContestGrade={
                    onContestGrade &&
                    ((gradeId: number) => {
                        // Contestația mută utilizatorul pe tabul „Cereri" — fișa n-are ce căuta
                        // deschisă peste el.
                        setDetailId(null);
                        onContestGrade(gradeId);
                    })
                }
            />
        </div>
    );
}

/** Banda de sinteză: o cifră mare (media) + contoarele care o explică. */
function SummaryBand({ summary }: { summary: GradeBookSummary }) {
    const t = useTranslations();

    const stats = [
        { label: t(pluralKey('cabinet.gb_grades', summary.gradesCount)), value: summary.gradesCount },
        { label: t(pluralKey('cabinet.gb_subjects', summary.subjectsCount)), value: summary.subjectsCount },
    ];

    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs text-muted-foreground">{t('cabinet.cockpit_general_average')}</p>
                    <p className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold tracking-tight tabular-nums">{summary.average ?? '—'}</span>
                        <TrendMark trend={summary.trend} />
                    </p>
                    {summary.previousAverage !== null && (
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            {t('cabinet.gb_previous_term')}: {summary.previousAverage}
                        </p>
                    )}
                </div>

                <dl className="flex flex-wrap gap-x-5 gap-y-1.5">
                    {stats.map((s) => (
                        <div key={s.label}>
                            <dd className="text-lg font-semibold tabular-nums">{s.value}</dd>
                            <dt className="text-[11px] text-muted-foreground">{s.label}</dt>
                        </div>
                    ))}
                    {summary.lastDate && (
                        <div>
                            <dd className="text-lg font-semibold tabular-nums">{summary.lastDate}</dd>
                            <dt className="text-[11px] text-muted-foreground">{t('cabinet.gb_last_grade')}</dt>
                        </div>
                    )}
                </dl>
            </div>

            {/* Singura alertă a modulului: disciplinele sub pragul de promovare. */}
            {summary.riskCount > 0 && (
                <p className="mt-3 flex items-start gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-xs font-medium text-destructive">
                    <TriangleAlert className="mt-px size-3.5 shrink-0" aria-hidden />
                    {summary.riskCount} {t(pluralKey('cabinet.gb_risk_subjects', summary.riskCount))}
                </p>
            )}
        </div>
    );
}

/** Cardurile disciplinelor: media, traiectoria și notele semestrului, la vedere. */
function SubjectCards({
    subjects,
    entries,
    term,
    onOpen,
}: {
    subjects: GradeBookSubject[];
    entries: GradeBookEntry[];
    term: number;
    onOpen: (id: number) => void;
}) {
    const t = useTranslations();

    if (subjects.length === 0) {
        return <EmptyState title={t('cabinet.gb_no_grades_term')} />;
    }

    return (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {subjects.map((subject) => {
                const stats = subject.terms[term];
                const items = entries.filter((g) => g.subjectId === subject.id);

                return (
                    <li key={subject.id}>
                        <button
                            type="button"
                            onClick={() => onOpen(subject.id)}
                            aria-label={`${t('cabinet.gb_open_subject')}: ${subject.name}`}
                            className={cn(
                                'flex h-full w-full cursor-pointer flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-left shadow-sm transition-shadow',
                                'hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                stats.risk && 'border-destructive/40',
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{subject.name}</p>
                                    {subject.teachers.length > 0 && (
                                        <p className="truncate text-xs text-muted-foreground">{subject.teachers.join(', ')}</p>
                                    )}
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className={cn('text-2xl leading-none font-bold tabular-nums', averageClass(stats.average, stats.risk))}>
                                        {stats.average ?? '—'}
                                    </p>
                                    <p className="mt-1 flex items-center justify-end gap-1 text-[11px] text-muted-foreground">
                                        <TrendMark trend={stats.trend} />
                                        {stats.count} {t(pluralKey('cabinet.gb_grades', stats.count))}
                                    </p>
                                </div>
                            </div>

                            {stats.series.length >= 3 && (
                                <Sparkline values={stats.series} className={stats.risk ? 'text-destructive/60' : 'text-primary/60'} />
                            )}

                            {/* Notele semestrului, cu data pe fiecare. */}
                            <div className="flex flex-wrap gap-1">
                                {items.map((entry) => (
                                    <GradeCell key={entry.id} entry={entry} />
                                ))}
                            </div>

                            {/* Componentele mediei — transparența §1.3, doar când există sumativă. */}
                            {stats.mc !== null && stats.summative !== null && (
                                <p className="text-[11px] text-muted-foreground">
                                    {t('cabinet.avg_current')} {stats.mc} · {t('cabinet.avg_summative')} {stats.summative}
                                </p>
                            )}

                            <p className="mt-auto flex items-center gap-0.5 pt-1 text-[11px] font-medium text-primary">
                                {t('cabinet.gb_open_subject')}
                                <ChevronRight className="size-3.5" aria-hidden />
                            </p>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/** Jurnalul: toate notele semestrului, cronologic, grupate pe lună, filtrabile pe disciplină. */
function GradeJournal({ entries, subjects, term }: { entries: GradeBookEntry[]; subjects: GradeBookSubject[]; term: number }) {
    const t = useTranslations();
    const [subjectFilter, setSubjectFilter] = useState<number | 'all'>('all');

    const visible = subjectFilter === 'all' ? entries : entries.filter((g) => g.subjectId === subjectFilter);

    // Lista vine deja sortată de server (dată DESC) → grupurile se formează liniar.
    const months: { label: string; items: GradeBookEntry[] }[] = [];

    for (const entry of visible) {
        const last = months[months.length - 1];

        if (last && last.label === entry.monthLabel) {
            last.items.push(entry);
        } else {
            months.push({ label: entry.monthLabel, items: [entry] });
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <FilterPills
                options={subjects.map((s) => ({ key: s.id, label: s.name, count: s.terms[term]?.count ?? 0 }))}
                active={subjectFilter}
                onChange={(key) => setSubjectFilter(key === 'all' ? 'all' : Number(key))}
                allCount={entries.length}
                ariaLabel={t('cabinet.subject')}
            />

            {months.length === 0 ? (
                <EmptyState title={t('cabinet.gb_no_grades_term')} />
            ) : (
                months.map((month) => (
                    <section key={month.label}>
                        <h3 className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">{month.label}</h3>
                        <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                            {month.items.map((entry) => (
                                <li key={entry.id} className="flex items-center gap-3 px-3.5 py-2.5">
                                    <div className="w-14 shrink-0">
                                        <div className="text-sm font-semibold tabular-nums">{entry.date.slice(0, 5)}</div>
                                        <div className="text-[11px] text-muted-foreground first-letter:uppercase">{entry.weekday}</div>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">{entry.subject}</p>
                                        <p className="flex flex-wrap items-center gap-x-2 text-[11px] text-muted-foreground">
                                            <span>{entry.typeLabel}</span>
                                            {entry.teacher && (
                                                <span className="inline-flex items-center gap-1">
                                                    <UserRound className="size-3" aria-hidden />
                                                    {entry.teacher}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'inline-flex min-h-9 min-w-9 shrink-0 items-center justify-center rounded-md px-2 text-sm font-semibold',
                                            entry.isSummative
                                                ? 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/40 dark:text-amber-300'
                                                : 'bg-primary/10 text-primary',
                                        )}
                                    >
                                        {entry.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            )}

            <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                <span className="inline-block h-2.5 w-2.5 rounded-sm bg-amber-500/40 ring-1 ring-amber-500/40" />
                {t('cabinet.summative_legend')}
            </p>
        </div>
    );
}

export interface EvolutionData {
    matrix: SemesterAveragesMatrix;
    dynamics: {
        /** Media anuală pe fiecare treaptă parcursă (foaia matricolă). */
        general: { level: number; average: number }[];
        subjects: { subject: string; points: { level: number; value: number }[]; trend: Trend }[];
        current: {
            average: number | null;
            historyAverage: number | null;
            previousYearSameTerm: number | null;
            trend: Trend;
            alert: boolean;
        };
    };
}

/**
 * EVOLUȚIA: unde e elevul acum față de propriul lui istoric, an de an și disciplină cu disciplină.
 *
 * Traseul multi-an e desenat ca ȘIR DE VALORI, nu ca grafic cu bare: între 8,34 și 8,89 orice bară
 * cu bază arbitrară minte despre amplitudine. Cifrele + săgețile spun adevărul fără scală.
 */
export function GradeEvolution({ evolution }: { evolution: EvolutionData }) {
    const t = useTranslations();
    const { dynamics, matrix } = evolution;

    const milestones: { label: string; value: number; current: boolean }[] = dynamics.general.map((point) => ({
        label: `${t('cabinet.class')} ${point.level}`,
        value: point.average,
        current: false,
    }));

    if (dynamics.current.average !== null) {
        milestones.push({ label: t('cabinet.gb_now'), value: dynamics.current.average, current: true });
    }

    const series = milestones.map((m) => m.value);

    return (
        <div className="flex flex-col gap-5">
            {/* 1. Poziția de acum, raportată la istoricul PROPRIU (nu la alți elevi). */}
            <div className="rounded-xl border bg-card p-4 shadow-sm">
                <p className="text-xs text-muted-foreground">{t('cabinet.dynamics_current')}</p>
                <p className="flex items-baseline gap-2">
                    <span className="text-3xl font-bold tracking-tight tabular-nums">{dynamics.current.average ?? '—'}</span>
                    <TrendMark trend={dynamics.current.trend} />
                </p>

                <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                    {dynamics.current.historyAverage !== null && (
                        <div>
                            <dd className="font-semibold tabular-nums">{dynamics.current.historyAverage}</dd>
                            <dt className="text-[11px] text-muted-foreground">{t('cabinet.dynamics_history')}</dt>
                        </div>
                    )}
                    {dynamics.current.previousYearSameTerm !== null && (
                        <div>
                            <dd className="font-semibold tabular-nums">{dynamics.current.previousYearSameTerm}</dd>
                            <dt className="text-[11px] text-muted-foreground">{t('cabinet.dynamics_vs_last_year')}</dt>
                        </div>
                    )}
                </dl>

                {dynamics.current.alert && (
                    <p className="mt-3 flex items-start gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                        <TriangleAlert className="mt-px size-3.5 shrink-0" aria-hidden />
                        {t('cabinet.dynamics_alert')}
                    </p>
                )}
            </div>

            {/* 2. Traseul an de an. */}
            {milestones.length > 1 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">{t('cabinet.dynamics_general')}</h3>
                    <div className="rounded-xl border bg-card p-4 shadow-sm">
                        <Sparkline values={series} className="mb-3 text-primary/60" />
                        <ol className="flex flex-wrap gap-2">
                            {milestones.map((m) => (
                                <li
                                    key={m.label}
                                    className={cn(
                                        'rounded-lg border px-3 py-1.5 text-center',
                                        m.current ? 'border-primary bg-primary/5' : 'bg-muted/30',
                                    )}
                                >
                                    <span className={cn('block text-lg font-bold tabular-nums', m.current && 'text-primary')}>{m.value}</span>
                                    <span className="block text-[11px] text-muted-foreground">{m.label}</span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>
            )}

            {/* 3. Anul curent, semestru cu semestru (mediile oficiale). */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">{t('cabinet.catalog_sec_averages')}</h3>
                <SemesterAveragesTable averages={matrix} />
            </section>

            {/* 4. Tendința fiecărei discipline, pe trepte. */}
            {dynamics.subjects.length > 0 && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">{t('cabinet.dynamics_by_subject')}</h3>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {dynamics.subjects.map((subject) => {
                            const last = subject.points[subject.points.length - 1];

                            return (
                                <li key={subject.subject} className="flex items-center justify-between gap-3 rounded-lg border bg-card px-3.5 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{subject.subject}</p>
                                        <p className="text-[11px] text-muted-foreground">
                                            {subject.points.map((p) => p.value).join(' → ')}
                                        </p>
                                    </div>
                                    <span className="flex shrink-0 items-center gap-1.5">
                                        <span className="text-sm font-semibold tabular-nums">{last ? last.value : '—'}</span>
                                        <TrendMark trend={subject.trend} />
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}
        </div>
    );
}

/** Fișa unei discipline: toate notele semestrului, cu tot contextul + din ce se compune media. */
function SubjectDetailDialog({
    subject,
    term,
    entries,
    onClose,
    onContestGrade,
}: {
    subject: GradeBookSubject | null;
    term: number;
    entries: GradeBookEntry[];
    onClose: () => void;
    onContestGrade?: (gradeId: number) => void;
}) {
    const t = useTranslations();
    const stats = subject?.terms[term] ?? null;

    return (
        <Dialog open={subject !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                {subject !== null && stats !== null && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{subject.name}</DialogTitle>
                            <DialogDescription>
                                {subject.teachers.length > 0 ? `${t('cabinet.gb_teacher')}: ${subject.teachers.join(', ')}` : t('cabinet.gb_subject_grades')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex flex-col gap-4 text-sm">
                            {/* Media, cu descompunerea ei — de unde vine cifra. */}
                            <div className="rounded-lg border bg-muted/30 px-3 py-2.5">
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="text-xs text-muted-foreground">{t('cabinet.gb_ms')}</span>
                                    <span className={cn('text-2xl font-bold tabular-nums', averageClass(stats.average, stats.risk))}>
                                        {stats.average ?? '—'}
                                    </span>
                                </div>
                                {stats.mc !== null && stats.summative !== null && (
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        {t('cabinet.avg_current')} {stats.mc} · {t('cabinet.avg_summative')} {stats.summative} — {t('cabinet.gb_ms_formula')}
                                    </p>
                                )}
                                {stats.risk && (
                                    <p className="mt-1.5 flex items-start gap-1.5 text-[11px] font-medium text-destructive">
                                        <TriangleAlert className="mt-px size-3.5 shrink-0" aria-hidden />
                                        {t('cabinet.gb_risk_hint')}
                                    </p>
                                )}
                            </div>

                            {/* Fiecare notă, cu data, tipul și cine a consemnat-o. */}
                            <ul className="divide-y overflow-hidden rounded-lg border">
                                {entries.map((entry) => (
                                    <li key={entry.id} className="flex items-center gap-3 px-3 py-2">
                                        <span
                                            className={cn(
                                                'inline-flex min-h-9 min-w-9 shrink-0 items-center justify-center rounded-md px-2 text-sm font-semibold',
                                                entry.isSummative
                                                    ? 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/40 dark:text-amber-300'
                                                    : 'bg-primary/10 text-primary',
                                            )}
                                        >
                                            {entry.label}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">
                                                {entry.date} <span className="font-normal text-muted-foreground">· {entry.weekday}</span>
                                            </p>
                                            <p className="flex flex-wrap items-center gap-x-2 text-[11px] text-muted-foreground">
                                                <span>{entry.typeLabel}</span>
                                                {entry.teacher && <span>{t('cabinet.gb_recorded_by')}: {entry.teacher}</span>}
                                                {entry.recordedAt && <span>{t('cabinet.gb_recorded_at')}: {entry.recordedAt}</span>}
                                            </p>
                                        </div>
                                        {onContestGrade && (
                                            <button
                                                type="button"
                                                onClick={() => onContestGrade(entry.id)}
                                                className="shrink-0 cursor-pointer rounded-md border px-2.5 py-1.5 text-[11px] font-medium text-primary hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                {t('cabinet.gb_contest')}
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {onContestGrade && <p className="text-[11px] text-muted-foreground">{t('cabinet.grade_contest_legend')}</p>}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
