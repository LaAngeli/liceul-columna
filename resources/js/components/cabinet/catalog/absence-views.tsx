import { CalendarDays, CalendarRange, Clock3, DoorOpen, ListOrdered, Lock, Search, TriangleAlert, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { FilterPills } from '@/components/cabinet/catalog/filter-pills';
import { EmptyState } from '@/components/cabinet/empty-state';
import { pluralKey, useLocale, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * SITUAȚIA absențelor — vederile modulului „Absențe" (elev + părinte).
 *
 * Registrul dinainte răspundea la o singură întrebare („ce absențe are?") și o făcea ca un tabel de
 * evidență. Un părinte pune însă patru întrebări, în ordinea asta: *cât de rău e?*, *ce a rămas
 * nerezolvat?*, *se înrăutățește?*, *unde anume lipsește?*. Ecranul le răspunde în aceeași ordine —
 * sinteză, alerte acționabile, evoluție lunară, apoi detaliul, în trei citiri comutabile.
 *
 * Convenția de culoare e una singură în tot modulul și nu se abate niciodată: **verde = motivată,
 * roșu = nemotivată**. Nimic altceva nu împrumută aceste două culori, ca să nu ceară interpretare.
 */

export interface AbsenceTerm {
    number: number;
    label: string;
    current: boolean;
}

export interface AbsenceSubjectTerm {
    total: number;
    motivated: number;
    unmotivated: number;
}

export interface AbsenceSubject {
    id: number;
    name: string;
    /** Profesorul disciplinei, din alocări (null când alocarea e ambiguă — grupe). */
    teacher: string | null;
    terms: Record<number, AbsenceSubjectTerm>;
}

export interface AbsenceEntry {
    id: number;
    term: number;
    subjectId: number;
    subject: string;
    iso: string;
    date: string;
    weekday: string;
    monthKey: string;
    monthLabel: string;
    teacher: string | null;
    /** Lecția, dedusă din orar — null unde disciplina apare de mai multe ori în acea zi. */
    lesson: { number: number; room: string | null } | null;
    motivated: boolean;
    recordedAt: string | null;
    deadline: string | null;
    deadlinePassed: boolean;
    /** Zile rămase până expiră dreptul de a cere motivare — semnalul acționabil. */
    deadlineDays: number | null;
    locked: boolean;
}

export interface AbsenceMonth {
    key: string;
    label: string;
    total: number;
    motivated: number;
    unmotivated: number;
}

export interface AbsenceSummary {
    total: number;
    motivated: number;
    unmotivated: number;
    motivatedRate: number | null;
    days: number;
    subjectsCount: number;
    worstSubject: { name: string; count: number } | null;
    lastDate: string | null;
    expiringSoon: number;
    locked: number;
    previousTotal: number | null;
    /** `up` = mai puține absențe decât semestrul trecut (deci bine), `down` = mai multe. */
    trend: 'up' | 'down' | 'stable' | null;
}

export interface AbsenceOverviewData {
    terms: AbsenceTerm[];
    currentTerm: number | null;
    subjects: AbsenceSubject[];
    absences: AbsenceEntry[];
    summary: Record<number, AbsenceSummary>;
    months: Record<number, AbsenceMonth[]>;
}

type StatusFilter = 'all' | 'motivated' | 'unmotivated';
type ViewMode = 'subjects' | 'timeline' | 'calendar';

/** Bara de proporție motivate/nemotivate — indicatorul de progres al modulului. */
function SplitBar({ motivated, unmotivated, className }: { motivated: number; unmotivated: number; className?: string }) {
    const total = motivated + unmotivated;

    if (total === 0) {
        return null;
    }

    return (
        <div className={cn('flex h-2 overflow-hidden rounded-full bg-muted', className)} aria-hidden>
            <div className="bg-emerald-500" style={{ width: `${(motivated / total) * 100}%` }} />
            <div className="bg-destructive" style={{ width: `${(unmotivated / total) * 100}%` }} />
        </div>
    );
}

/** Eticheta de status a unei absențe — singurul loc care decide culoarea. */
function StatusChip({ motivated, className }: { motivated: boolean; className?: string }) {
    const t = useTranslations();

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs font-semibold',
                motivated ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-destructive/10 text-destructive',
                className,
            )}
        >
            {t(motivated ? 'cabinet.motivated' : 'cabinet.unmotivated')}
        </span>
    );
}

/** Contextul secundar al unei absențe: profesor, lecție, sală, termen, consolidare. */
function EntryMeta({ entry }: { entry: AbsenceEntry }) {
    const t = useTranslations();

    return (
        <div className="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[11px] text-muted-foreground">
            {entry.teacher && (
                <span className="inline-flex items-center gap-1">
                    <UserRound className="size-3" aria-hidden />
                    {entry.teacher}
                </span>
            )}
            {/* Lecția apare doar unde orarul o poate spune fără echivoc — vezi lessonSlotsFor(). */}
            {entry.lesson && (
                <span className="inline-flex items-center gap-1">
                    <Clock3 className="size-3" aria-hidden />
                    {t('cabinet.abs_lesson')} {entry.lesson.number}
                </span>
            )}
            {entry.lesson?.room && (
                <span className="inline-flex items-center gap-1">
                    <DoorOpen className="size-3" aria-hidden />
                    {t('cabinet.abs_room')} {entry.lesson.room}
                </span>
            )}
            {entry.locked && (
                <span
                    className="inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 font-medium text-amber-700 dark:text-amber-300"
                    title={t('cabinet.reg_locked_hint')}
                >
                    <Lock className="size-3" aria-hidden />
                    {t('cabinet.reg_locked')}
                </span>
            )}
            {!entry.locked && entry.deadlineDays !== null && (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium',
                        entry.deadlineDays <= 7 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-muted',
                    )}
                >
                    <CalendarRange className="size-3" aria-hidden />
                    {t('cabinet.reg_deadline')}: {entry.deadline}
                </span>
            )}
            {!entry.locked && entry.deadlinePassed && (
                <span className="inline-flex items-center gap-1 rounded bg-destructive/10 px-1.5 py-0.5 font-medium text-destructive">
                    {t('cabinet.reg_deadline_passed')}
                </span>
            )}
        </div>
    );
}

/** Situația completă: semestru → sinteză → evoluție → filtre → vedere. */
export function AbsenceOverview({ overview, onRequestMotivation }: { overview: AbsenceOverviewData; onRequestMotivation?: () => void }) {
    const t = useTranslations();
    const [term, setTerm] = useState<number>(() => overview.currentTerm ?? overview.terms[0]?.number ?? 1);
    const [view, setView] = useState<ViewMode>('subjects');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [subjectFilter, setSubjectFilter] = useState<number | 'all'>('all');
    const [query, setQuery] = useState('');

    const summary = overview.summary[term] ?? null;
    const months = overview.months[term] ?? [];

    const termEntries = useMemo(
        () => overview.absences.filter((a) => a.term === term),
        [overview.absences, term],
    );

    const subjects = useMemo(
        () => overview.subjects.filter((s) => s.terms[term] !== undefined),
        [overview.subjects, term],
    );

    // Filtrele se compun: status ∧ disciplină ∧ căutare. Căutarea acoperă disciplina, profesorul și
    // data scrisă în orice formă („12.03", „martie", „vineri") — un părinte caută cum ține minte.
    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return termEntries.filter((entry) => {
            if (status === 'motivated' && !entry.motivated) {
                return false;
            }

            if (status === 'unmotivated' && entry.motivated) {
                return false;
            }

            if (subjectFilter !== 'all' && entry.subjectId !== subjectFilter) {
                return false;
            }

            if (needle === '') {
                return true;
            }

            return [entry.subject, entry.teacher ?? '', entry.date, entry.weekday, entry.monthLabel]
                .join(' ')
                .toLowerCase()
                .includes(needle);
        });
    }, [termEntries, status, subjectFilter, query]);

    if (overview.terms.length === 0 || overview.absences.length === 0) {
        return <EmptyState title={t('cabinet.no_absences')} />;
    }

    const filtersActive = status !== 'all' || subjectFilter !== 'all' || query.trim() !== '';

    return (
        <div className="flex flex-col gap-4">
            {/* Semestrul — aceeași axă ca la Note, ca cele două module să se citească la fel. */}
            {overview.terms.length > 1 && (
                <div className="flex flex-wrap gap-1.5" role="group" aria-label={t('cabinet.gb_term')}>
                    {overview.terms.map((item) => (
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

            {summary !== null && <SummaryBand summary={summary} onRequestMotivation={onRequestMotivation} />}

            {months.length > 1 && <MonthlyChart months={months} />}

            {termEntries.length === 0 ? (
                <EmptyState title={t('cabinet.abs_none_term')} />
            ) : (
                <>
                    <Filters
                        subjects={subjects}
                        term={term}
                        total={termEntries.length}
                        status={status}
                        onStatus={setStatus}
                        subjectFilter={subjectFilter}
                        onSubject={setSubjectFilter}
                        query={query}
                        onQuery={setQuery}
                        view={view}
                        onView={setView}
                    />

                    {filtersActive && (
                        <p className="text-xs text-muted-foreground" role="status">
                            {t('cabinet.abs_filtered')
                                .replace(':count', String(visible.length))
                                .replace(':total', String(termEntries.length))}
                        </p>
                    )}

                    {visible.length === 0 ? (
                        <EmptyState title={t('cabinet.abs_no_match')} />
                    ) : view === 'subjects' ? (
                        <SubjectBreakdown subjects={subjects} entries={visible} term={term} />
                    ) : view === 'timeline' ? (
                        <Timeline entries={visible} />
                    ) : (
                        <AbsenceCalendar entries={visible} months={months} />
                    )}
                </>
            )}
        </div>
    );
}

/** Banda de sinteză: cifra mare, proporția motivat/nemotivat și ce mai poate fi rezolvat. */
function SummaryBand({ summary, onRequestMotivation }: { summary: AbsenceSummary; onRequestMotivation?: () => void }) {
    const t = useTranslations();

    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-xs text-muted-foreground">{t('cabinet.abs_total')}</p>
                    <p className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold tracking-tight tabular-nums">{summary.total}</span>
                        {summary.previousTotal !== null && (
                            <span
                                className={cn(
                                    'text-xs font-medium',
                                    // La absențe „mai multe" e rău: `down` (creștere) se colorează roșu.
                                    summary.trend === 'down' ? 'text-destructive' : summary.trend === 'up' ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground',
                                )}
                            >
                                {summary.trend === 'down' ? '▲' : summary.trend === 'up' ? '▼' : '▬'}{' '}
                                {t('cabinet.gb_previous_term')}: {summary.previousTotal}
                            </span>
                        )}
                    </p>
                </div>

                <dl className="flex flex-wrap gap-x-5 gap-y-1.5">
                    <div>
                        <dd className="text-lg font-semibold tabular-nums">{summary.days}</dd>
                        <dt className="text-[11px] text-muted-foreground">{t('cabinet.abs_days')}</dt>
                    </div>
                    <div>
                        <dd className="text-lg font-semibold tabular-nums">{summary.subjectsCount}</dd>
                        <dt className="text-[11px] text-muted-foreground">{t('cabinet.abs_subjects_hit')}</dt>
                    </div>
                    {summary.lastDate && (
                        <div>
                            <dd className="text-lg font-semibold tabular-nums">{summary.lastDate}</dd>
                            <dt className="text-[11px] text-muted-foreground">{t('cabinet.abs_last')}</dt>
                        </div>
                    )}
                </dl>
            </div>

            {/* Proporția — se citește dintr-o privire, fără să numeri. */}
            <div className="mt-3">
                <SplitBar motivated={summary.motivated} unmotivated={summary.unmotivated} />
                <div className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-emerald-500" aria-hidden />
                        <span className="font-semibold tabular-nums">{summary.motivated}</span>
                        <span className="text-muted-foreground">{t(pluralKey('cabinet.motivated', summary.motivated))}</span>
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-destructive" aria-hidden />
                        <span className="font-semibold tabular-nums">{summary.unmotivated}</span>
                        <span className="text-muted-foreground">{t(pluralKey('cabinet.unmotivated', summary.unmotivated))}</span>
                    </span>
                    {summary.motivatedRate !== null && (
                        <span className="text-muted-foreground">
                            {summary.motivatedRate}% {t('cabinet.abs_motivated_rate')}
                        </span>
                    )}
                </div>
            </div>

            {/* Disciplina cea mai afectată — răspunsul la „unde e problema?". */}
            {summary.worstSubject && summary.worstSubject.count > 1 && (
                <p className="mt-3 text-xs text-muted-foreground">
                    {t('cabinet.abs_worst')}: <span className="font-medium text-foreground">{summary.worstSubject.name}</span> (
                    {summary.worstSubject.count})
                </p>
            )}

            {/* Singurul lucru din modul care mai poate fi REZOLVAT — deci singurul cu buton. */}
            {summary.expiringSoon > 0 && (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-500/10 px-3 py-2">
                    <p className="flex items-start gap-2 text-xs font-medium text-amber-800 dark:text-amber-300">
                        <TriangleAlert className="mt-px size-3.5 shrink-0" aria-hidden />
                        {t('cabinet.abs_expiring').replace(':count', String(summary.expiringSoon))}
                    </p>
                    {onRequestMotivation && (
                        <button
                            type="button"
                            onClick={onRequestMotivation}
                            className="inline-flex h-8 shrink-0 cursor-pointer items-center rounded-md bg-amber-600 px-3 text-xs font-medium text-white hover:bg-amber-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            {t('cabinet.abs_go_motivate')}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

/** Evoluția lunară, ca bare stivuite — arată ritmul, nu doar totalul. */
function MonthlyChart({ months }: { months: AbsenceMonth[] }) {
    const t = useTranslations();
    const peak = Math.max(...months.map((m) => m.total), 1);

    return (
        <section className="rounded-xl border bg-card p-4 shadow-sm">
            <h3 className="text-sm font-semibold">{t('cabinet.abs_evolution')}</h3>
            <p className="mt-0.5 text-[11px] text-muted-foreground">{t('cabinet.abs_evolution_hint')}</p>

            <ol className="mt-3 flex items-end gap-2">
                {months.map((month) => (
                    <li key={month.key} className="flex min-w-0 flex-1 flex-col items-center gap-1">
                        <span className="text-[11px] font-semibold tabular-nums">{month.total || ''}</span>
                        {/* Înălțimea proporțională cu luna de vârf; stiva păstrează codul de culoare. */}
                        <div
                            className="flex w-full max-w-12 flex-col-reverse justify-start overflow-hidden rounded-md bg-muted/40"
                            style={{ height: '4.5rem' }}
                            title={`${month.label}: ${month.total}`}
                        >
                            <div className="w-full bg-destructive" style={{ height: `${(month.unmotivated / peak) * 100}%` }} />
                            <div className="w-full bg-emerald-500" style={{ height: `${(month.motivated / peak) * 100}%` }} />
                        </div>
                        <span className="w-full truncate text-center text-[10px] text-muted-foreground">{month.label}</span>
                    </li>
                ))}
            </ol>
        </section>
    );
}

/** Căutare + status + discipline + comutatorul de vedere. */
function Filters({
    subjects,
    term,
    total,
    status,
    onStatus,
    subjectFilter,
    onSubject,
    query,
    onQuery,
    view,
    onView,
}: {
    subjects: AbsenceSubject[];
    term: number;
    total: number;
    status: StatusFilter;
    onStatus: (value: StatusFilter) => void;
    subjectFilter: number | 'all';
    onSubject: (value: number | 'all') => void;
    query: string;
    onQuery: (value: string) => void;
    view: ViewMode;
    onView: (value: ViewMode) => void;
}) {
    const t = useTranslations();

    const statuses: { value: StatusFilter; label: string }[] = [
        { value: 'all', label: t('cabinet.abs_status_all') },
        { value: 'unmotivated', label: t('cabinet.unmotivated') },
        { value: 'motivated', label: t('cabinet.motivated') },
    ];

    const views: { value: ViewMode; label: string; icon: typeof ListOrdered }[] = [
        { value: 'subjects', label: t('cabinet.gb_view_subjects'), icon: ListOrdered },
        { value: 'timeline', label: t('cabinet.abs_view_timeline'), icon: CalendarRange },
        { value: 'calendar', label: t('cabinet.abs_view_calendar'), icon: CalendarDays },
    ];

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <label className="relative min-w-0 flex-1 sm:max-w-xs">
                    <span className="sr-only">{t('cabinet.abs_search')}</span>
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => onQuery(e.target.value)}
                        placeholder={t('cabinet.abs_search')}
                        className="h-11 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    />
                </label>

                <div className="flex rounded-lg border p-0.5" role="group" aria-label={t('cabinet.gb_view')}>
                    {views.map((mode) => (
                        <button
                            key={mode.value}
                            type="button"
                            onClick={() => onView(mode.value)}
                            aria-pressed={view === mode.value}
                            title={mode.label}
                            className={cn(
                                // min-w-11: sub `sm` rămâne doar pictograma, iar `px-3` dădea 38px
                                // lățime — sub pragul tactil, deși înălțimea era bună.
                                'inline-flex h-11 min-w-11 cursor-pointer items-center justify-center gap-1.5 rounded-md px-3 text-xs font-medium transition-colors',
                                view === mode.value ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <mode.icon className="size-3.5" aria-hidden />
                            {/* Pe telefon rămâne doar pictograma — trei etichete ar rupe rândul. */}
                            <span className="hidden sm:inline">{mode.label}</span>
                        </button>
                    ))}
                </div>
            </div>

            <div className="flex flex-wrap gap-1.5" role="group" aria-label={t('cabinet.abs_status_all')}>
                {statuses.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        onClick={() => onStatus(item.value)}
                        aria-pressed={status === item.value}
                        className={cn(
                            'inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            status === item.value
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        {item.value !== 'all' && (
                            <span
                                className={cn('size-2 rounded-full', item.value === 'motivated' ? 'bg-emerald-500' : 'bg-destructive')}
                                aria-hidden
                            />
                        )}
                        {item.label}
                    </button>
                ))}
            </div>

            <FilterPills
                options={subjects.map((s) => ({
                    key: s.id,
                    label: s.name,
                    count: s.terms[term]?.total ?? 0,
                    danger: (s.terms[term]?.unmotivated ?? 0) > 0,
                }))}
                active={subjectFilter}
                onChange={(key) => onSubject(key === 'all' ? 'all' : Number(key))}
                allCount={total}
                ariaLabel={t('cabinet.subject')}
            />
        </div>
    );
}

/** Pe discipline: cardul arată proporția motivat/nemotivat și absențele disciplinei. */
function SubjectBreakdown({ subjects, entries, term }: { subjects: AbsenceSubject[]; entries: AbsenceEntry[]; term: number }) {
    const t = useTranslations();

    // Doar disciplinele care au rămas după filtrare — altfel ar apărea carduri goale.
    const present = subjects.filter((s) => entries.some((e) => e.subjectId === s.id));

    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {present.map((subject) => {
                const stats = subject.terms[term];
                const items = entries.filter((e) => e.subjectId === subject.id);

                return (
                    <li key={subject.id} className="flex flex-col gap-2.5 rounded-xl border bg-card p-3.5 shadow-sm">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="truncate font-medium">{subject.name}</p>
                                {subject.teacher && <p className="truncate text-xs text-muted-foreground">{subject.teacher}</p>}
                            </div>
                            <p className="shrink-0 text-2xl leading-none font-bold tabular-nums">{stats?.total ?? items.length}</p>
                        </div>

                        <div>
                            <SplitBar motivated={stats?.motivated ?? 0} unmotivated={stats?.unmotivated ?? 0} />
                            <p className="mt-1 flex flex-wrap gap-x-3 text-[11px] text-muted-foreground">
                                <span>
                                    {stats?.motivated ?? 0} {t(pluralKey('cabinet.motivated', stats?.motivated ?? 0))}
                                </span>
                                <span className={cn((stats?.unmotivated ?? 0) > 0 && 'font-medium text-destructive')}>
                                    {stats?.unmotivated ?? 0} {t(pluralKey('cabinet.unmotivated', stats?.unmotivated ?? 0))}
                                </span>
                            </p>
                        </div>

                        <ul className="flex flex-col gap-1.5">
                            {items.map((entry) => (
                                <li key={entry.id} className="flex items-start gap-2 rounded-lg bg-muted/40 px-2.5 py-1.5">
                                    <span className="w-14 shrink-0 text-xs font-semibold tabular-nums">{entry.date.slice(0, 5)}</span>
                                    <div className="min-w-0 flex-1">
                                        <span className="text-xs text-muted-foreground first-letter:uppercase">{entry.weekday}</span>
                                        <EntryMeta entry={entry} />
                                    </div>
                                    <span
                                        className={cn(
                                            'mt-0.5 size-2.5 shrink-0 rounded-full',
                                            entry.motivated ? 'bg-emerald-500' : 'bg-destructive',
                                        )}
                                        title={t(entry.motivated ? 'cabinet.motivated' : 'cabinet.unmotivated')}
                                    >
                                        <span className="sr-only">{t(entry.motivated ? 'cabinet.motivated' : 'cabinet.unmotivated')}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </li>
                );
            })}
        </ul>
    );
}

/** Cronologic: absențele aceleiași zile stau împreună — așa se văd zilele întregi lipsite. */
function Timeline({ entries }: { entries: AbsenceEntry[] }) {
    const t = useTranslations();

    // Lista vine sortată descrescător de pe server → grupurile se formează liniar.
    const days: { iso: string; date: string; weekday: string; monthLabel: string; items: AbsenceEntry[] }[] = [];

    for (const entry of entries) {
        const last = days[days.length - 1];

        if (last && last.iso === entry.iso) {
            last.items.push(entry);
        } else {
            days.push({ iso: entry.iso, date: entry.date, weekday: entry.weekday, monthLabel: entry.monthLabel, items: [entry] });
        }
    }

    let previousMonth = '';

    return (
        <div className="flex flex-col gap-3">
            {days.map((day) => {
                const header = day.monthLabel !== previousMonth ? day.monthLabel : null;
                previousMonth = day.monthLabel;
                const unmotivated = day.items.filter((i) => !i.motivated).length;

                return (
                    <div key={day.iso}>
                        {header && <h3 className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">{header}</h3>}
                        <div className="overflow-hidden rounded-xl border bg-card">
                            <div className="flex items-center justify-between gap-2 border-b bg-muted/40 px-3.5 py-2">
                                <p className="text-sm font-semibold">
                                    {day.date} <span className="font-normal text-muted-foreground first-letter:uppercase">· {day.weekday}</span>
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {day.items.length} {t(day.items.length === 1 ? 'cabinet.abs_one' : 'cabinet.abs_many')}
                                    {unmotivated > 0 && (
                                        <span className="ml-1.5 font-medium text-destructive">
                                            · {unmotivated} {t(pluralKey('cabinet.unmotivated', unmotivated))}
                                        </span>
                                    )}
                                </p>
                            </div>
                            <ul className="divide-y">
                                {day.items.map((entry) => (
                                    <li key={entry.id} className="flex items-start gap-3 px-3.5 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{entry.subject}</p>
                                            <EntryMeta entry={entry} />
                                        </div>
                                        <StatusChip motivated={entry.motivated} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/** Calendarul: lunile cu absențe, ca grilă — grupările („a lipsit toată săptămâna") sar în ochi. */
function AbsenceCalendar({ entries, months }: { entries: AbsenceEntry[]; months: AbsenceMonth[] }) {
    const t = useTranslations();
    const locale = useLocale();
    const localeTag = locale === 'ru' ? 'ru-RU' : locale === 'en' ? 'en-US' : 'ro-RO';

    // Doar lunile care au rămas după filtrare; ordinea rămâne cea cronologică a serverului.
    const byDay = useMemo(() => {
        const map = new Map<string, AbsenceEntry[]>();

        for (const entry of entries) {
            map.set(entry.iso, [...(map.get(entry.iso) ?? []), entry]);
        }

        return map;
    }, [entries]);

    const visibleMonths = months.filter((m) => entries.some((e) => e.monthKey === m.key));

    // Lu…Du — antetul grilei, în limba interfeței (1 ian. 2024 e o luni).
    const weekdayLabels = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(localeTag, { weekday: 'short' });

        return Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2024, 0, 1 + i)));
    }, [localeTag]);

    return (
        <div className="flex flex-col gap-4">
            {visibleMonths.map((month) => {
                const [year, monthIndex] = month.key.split('-').map(Number);
                const first = new Date(year, monthIndex - 1, 1);
                const daysInMonth = new Date(year, monthIndex, 0).getDate();
                // getDay(): duminică=0 → offset față de luni.
                const offset = (first.getDay() + 6) % 7;

                return (
                    <section key={month.key} className="rounded-xl border bg-card p-3.5 shadow-sm">
                        <h3 className="mb-2 text-sm font-semibold first-letter:uppercase">{month.label}</h3>

                        <div className="grid grid-cols-7 gap-1 text-center">
                            {weekdayLabels.map((label) => (
                                <span key={label} className="pb-1 text-[10px] font-medium text-muted-foreground">
                                    {label}
                                </span>
                            ))}

                            {Array.from({ length: offset }, (_, i) => (
                                <span key={`gap-${i}`} aria-hidden />
                            ))}

                            {Array.from({ length: daysInMonth }, (_, i) => {
                                const day = i + 1;
                                const iso = `${year}-${String(monthIndex).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                const items = byDay.get(iso) ?? [];
                                const unmotivated = items.filter((entry) => !entry.motivated).length;

                                return (
                                    <span
                                        key={iso}
                                        title={
                                            items.length > 0
                                                ? `${items[0].date}: ${items.map((entry) => entry.subject).join(', ')}`
                                                : undefined
                                        }
                                        className={cn(
                                            'flex aspect-square min-h-9 flex-col items-center justify-center rounded-md text-xs tabular-nums',
                                            items.length === 0 && 'text-muted-foreground/50',
                                            items.length > 0 && unmotivated > 0 && 'bg-destructive/15 font-semibold text-destructive',
                                            items.length > 0 && unmotivated === 0 && 'bg-emerald-500/15 font-semibold text-emerald-700 dark:text-emerald-400',
                                        )}
                                    >
                                        {day}
                                        {items.length > 1 && <span className="text-[9px] leading-none font-normal">×{items.length}</span>}
                                        {items.length > 0 && (
                                            <span className="sr-only">
                                                {items.length} {t(items.length === 1 ? 'cabinet.abs_one' : 'cabinet.abs_many')}
                                            </span>
                                        )}
                                    </span>
                                );
                            })}
                        </div>
                    </section>
                );
            })}

            <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-emerald-500/40 ring-1 ring-emerald-500/40" aria-hidden />
                    {t('cabinet.abs_cal_motivated')}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-destructive/40 ring-1 ring-destructive/40" aria-hidden />
                    {t('cabinet.abs_cal_unmotivated')}
                </span>
            </p>
        </div>
    );
}
