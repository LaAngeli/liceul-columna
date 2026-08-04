import { History, UserRound } from 'lucide-react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { pluralKey, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * „Cronologie" — notele și absențele ÎMPREUNĂ, în ordinea producerii. Părintele urmărește
 * evoluția copilului calendaristic („ce s-a întâmplat săptămâna asta?"), nu pe tipuri de
 * informație; modulele Note / Absențe rămân destinațiile de adâncime (medii, motivări, termene).
 *
 * Gramatica vizuală e cea deja învățată în module: cardul-zi din registrul de absențe
 * (antet dată · zi + bilanț), pastila de notă din jurnalul catalogului (primar / chihlimbar
 * pentru sumative), chip-ul de status al absenței (verde motivată / roșu nemotivată).
 * Un singur lucru e nou aici: cele două tipuri stau pe același fir.
 *
 * Lista vine SORTATĂ de pe server (zile desc; în zi: note, apoi absențe pe lecții) —
 * gruparea pe zile și separatoarele de lună se formează liniar, fără alt request.
 */

export interface TimelineLesson {
    number: number;
    room: string | null;
}

export interface TimelineEntry {
    key: string;
    kind: 'grade' | 'absence';
    iso: string;
    date: string;
    weekday: string;
    monthKey: string;
    monthLabel: string;
    subject: string;
    teacher: string | null;
    /** Doar note: nota afișată (sau calificativul din primar). */
    label: string | null;
    value: number | null;
    typeLabel: string | null;
    isSummative: boolean;
    /** Doar absențe. */
    motivated: boolean | null;
    lesson: TimelineLesson | null;
}

export interface ActivityTimelineData {
    entries: TimelineEntry[];
}

/** Pastila de notă — aceeași convenție ca jurnalul catalogului (sumativa = chihlimbar). */
function GradeChip({ entry }: { entry: TimelineEntry }) {
    return (
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
    );
}

/**
 * Chip-ul de status al absenței. Eticheta e la SINGULAR („motivată" / „nemotivată" / „fără statut")
 * — chip-ul calificã O absență, nu un grup; formele de plural rămân pentru contoare și filtre.
 * `null` = consemnată de profesor, statutul urmează de la diriginte — chihlimbar, nu roșu.
 */
function AbsenceChip({ motivated }: { motivated: boolean | null }) {
    const t = useTranslations();

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs font-semibold first-letter:uppercase',
                motivated === true && 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
                motivated === false && 'bg-destructive/10 text-destructive',
                motivated === null && 'bg-amber-400/15 text-amber-700 dark:text-amber-400',
            )}
        >
            {t(motivated === true ? 'cabinet.motivated_one' : motivated === false ? 'cabinet.unmotivated_one' : 'cabinet.pending_one')}
        </span>
    );
}

/** Rândul secundar al unei intrări: tipul notei / lecția absenței + profesorul. */
function EntryContext({ entry }: { entry: TimelineEntry }) {
    const t = useTranslations();

    return (
        <p className="flex flex-wrap items-center gap-x-2 text-[11px] text-muted-foreground">
            {entry.kind === 'grade' ? (
                <span>{entry.typeLabel}</span>
            ) : (
                entry.lesson !== null && (
                    <span>
                        {t('cabinet.abs_lesson')} {entry.lesson.number}
                    </span>
                )
            )}
            {entry.teacher !== null && (
                <span className="inline-flex items-center gap-1">
                    <UserRound className="size-3" aria-hidden />
                    {entry.teacher}
                </span>
            )}
        </p>
    );
}

export function ActivityTimeline({ timeline }: { timeline: ActivityTimelineData }) {
    const t = useTranslations();

    // Firul e continuu peste tot anul școlar — fără împărțire pe semestre (cerința
    // beneficiarului): părintele derulează calendarul, nu comută între perioade contabile.
    const entries = timeline.entries;
    const gradeCount = entries.filter((entry) => entry.kind === 'grade').length;
    const absenceCount = entries.length - gradeCount;
    const unmotivatedCount = entries.filter((entry) => entry.kind === 'absence' && entry.motivated === false).length;
    const hasSummative = entries.some((entry) => entry.isSummative);

    // Lista vine sortată descrescător de pe server → grupurile de zi se formează liniar.
    const days: { iso: string; date: string; weekday: string; monthLabel: string; items: TimelineEntry[] }[] = [];

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
        <div className="flex flex-col gap-4">
            {/* Bilanțul anului școlar — cifrele mari, înainte de fir. */}
            {entries.length > 0 && (
                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                    <span>
                        <span className="font-semibold text-foreground tabular-nums">{gradeCount}</span>{' '}
                        {t(gradeCount === 1 ? 'cabinet.gb_grades_one' : 'cabinet.gb_grades_other')}
                    </span>
                    <span aria-hidden>·</span>
                    <span>
                        <span className="font-semibold text-foreground tabular-nums">{absenceCount}</span>{' '}
                        {t(absenceCount === 1 ? 'cabinet.abs_one' : 'cabinet.abs_many')}
                    </span>
                    {unmotivatedCount > 0 && (
                        <span className="font-medium text-destructive">
                            · {unmotivatedCount} {t(pluralKey('cabinet.unmotivated', unmotivatedCount))}
                        </span>
                    )}
                </p>
            )}

            {entries.length === 0 ? (
                <EmptyState icon={History} title={t('cabinet.tl_empty')} />
            ) : (
                <div className="flex flex-col gap-3">
                    {days.map((day) => {
                        const header = day.monthLabel !== previousMonth ? day.monthLabel : null;
                        previousMonth = day.monthLabel;
                        const dayGrades = day.items.filter((item) => item.kind === 'grade').length;
                        const dayAbsences = day.items.length - dayGrades;

                        return (
                            <div key={day.iso}>
                                {header && (
                                    <h3 className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">{header}</h3>
                                )}
                                <section className="overflow-hidden rounded-xl border bg-card">
                                    <div className="flex items-center justify-between gap-2 border-b bg-muted/40 px-3.5 py-2">
                                        <p className="text-sm font-semibold">
                                            {day.date}{' '}
                                            <span className="font-normal text-muted-foreground first-letter:uppercase">· {day.weekday}</span>
                                        </p>
                                        {/* Bilanțul zilei: câte note, câte absențe — vizibil înainte de a citi rândurile. */}
                                        <p className="shrink-0 text-[11px] text-muted-foreground">
                                            {dayGrades > 0 && (
                                                <span>
                                                    {dayGrades} {t(dayGrades === 1 ? 'cabinet.gb_grades_one' : 'cabinet.gb_grades_other')}
                                                </span>
                                            )}
                                            {dayGrades > 0 && dayAbsences > 0 && <span aria-hidden> · </span>}
                                            {dayAbsences > 0 && (
                                                <span>
                                                    {dayAbsences} {t(dayAbsences === 1 ? 'cabinet.abs_one' : 'cabinet.abs_many')}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <ul className="divide-y">
                                        {day.items.map((entry) => (
                                            <li key={entry.key} className="flex items-center gap-3 px-3.5 py-2.5">
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">{entry.subject}</p>
                                                    <EntryContext entry={entry} />
                                                </div>
                                                {entry.kind === 'grade' ? (
                                                    <GradeChip entry={entry} />
                                                ) : (
                                                    <AbsenceChip motivated={entry.motivated} />
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            </div>
                        );
                    })}
                </div>
            )}

            {hasSummative && (
                <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-amber-500/40 ring-1 ring-amber-500/40" />
                    {t('cabinet.summative_legend')}
                </p>
            )}
        </div>
    );
}
