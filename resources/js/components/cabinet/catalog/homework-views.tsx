import { useState } from 'react';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Vederile PARTAJATE ale temelor — folosite de modulul „Teme" din meniu ȘI de tabul
 * „Orar & teme" al fișei elevului. O singură implementare → aceleași reguli/design peste tot.
 */

export interface HomeworkItem {
    id: number;
    /** Data atribuirii (d.m.Y). */
    date: string;
    /** Termenul de predare (d.m.Y) — null la temele legacy. */
    due: string | null;
    /** Cheia zilei efective (Y-m-d) — gruparea cronologică. */
    effectiveDate: string;
    /** Eticheta zilei, tradusă pe server („Vineri, 18 iulie"). */
    dayLabel: string;
    status: 'today' | 'upcoming' | 'past';
    subject: string;
    /** Profesorul care a dat tema (snapshot-ul author_name). */
    teacher: string | null;
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
}

/** Data locală ca Y-m-d (NU toISOString — UTC-ul ar aluneca o zi noaptea). */
export function localIso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

interface DayGroup {
    key: string;
    label: string;
    isToday: boolean;
    items: HomeworkItem[];
}

/** Temele consecutive pe aceeași zi efectivă → un grup (lista vine deja sortată de server). */
function groupByDay(items: HomeworkItem[], todayLabel: string, tomorrowLabel: string, tomorrowIso: string): DayGroup[] {
    const days: DayGroup[] = [];

    for (const h of items) {
        const last = days[days.length - 1];

        if (last && last.key === h.effectiveDate) {
            last.items.push(h);
            continue;
        }

        days.push({
            key: h.effectiveDate,
            label: h.status === 'today' ? todayLabel : h.effectiveDate === tomorrowIso ? tomorrowLabel : h.dayLabel,
            isToday: h.status === 'today',
            items: [h],
        });
    }

    return days;
}

/**
 * Temele grupate pe ZILE după data efectivă (termen ?? atribuire), cu FILTRU pe disciplină
 * (pastile cu contoare — același tipar ca registrul de absențe; comutare instant, client-side).
 * Istoricul stă pliat, dar e grupat pe zile la fel ca viitorul.
 */
export function HomeworkByDay({ homework }: { homework: HomeworkItem[] }) {
    const t = useTranslations();
    const [activeSubject, setActiveSubject] = useState<string | 'all'>('all');

    // Contoarele pastilelor se calculează pe TOT setul (nu pe filtrat) — pastila își arată
    // volumul indiferent ce e selectat; punctul marchează disciplinele cu teme AZI.
    const subjects: { name: string; total: number; hasToday: boolean }[] = [];

    for (const h of homework) {
        const existing = subjects.find((s) => s.name === h.subject);

        if (existing) {
            existing.total++;
            existing.hasToday ||= h.status === 'today';
        } else {
            subjects.push({ name: h.subject, total: 1, hasToday: h.status === 'today' });
        }
    }

    const visible = activeSubject === 'all' ? homework : homework.filter((h) => h.subject === activeSubject);
    const upcoming = visible.filter((h) => h.status !== 'past');
    const past = visible.filter((h) => h.status === 'past');

    // „Mâine" se decide pe client (doar etichetă; gruparea folosește cheia serverului).
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowIso = localIso(tomorrow);

    const days = groupByDay(upcoming, t('cabinet.homework_today'), t('cabinet.homework_tomorrow'), tomorrowIso);
    const pastDays = groupByDay(past, t('cabinet.homework_today'), t('cabinet.homework_tomorrow'), tomorrowIso);

    const pillClass = (active: boolean) =>
        cn(
            'inline-flex h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors',
            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            active
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
        );

    return (
        <div className="flex flex-col gap-4">
            {/* Filtrul pe discipline — doar când există ce filtra (2+ discipline). */}
            {subjects.length > 1 && (
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
                            {homework.length}
                        </span>
                    </button>
                    {subjects.map((s) => {
                        const active = activeSubject === s.name;

                        return (
                            <button
                                key={s.name}
                                type="button"
                                onClick={() => setActiveSubject(s.name)}
                                aria-pressed={active}
                                className={pillClass(active)}
                            >
                                <span className="max-w-44 truncate">{s.name}</span>
                                {/* Punct: disciplina are temă DE PREDAT azi. */}
                                {s.hasToday && (
                                    <span className={cn('size-1.5 rounded-full', active ? 'bg-primary-foreground' : 'bg-primary')} aria-hidden />
                                )}
                                <span
                                    className={cn(
                                        'inline-flex min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold',
                                        active ? 'bg-primary-foreground/20' : 'bg-muted',
                                    )}
                                >
                                    {s.total}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            {days.length === 0 && (
                <p className="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    {t('cabinet.homework_none_upcoming')}
                </p>
            )}
            {days.map((day) => (
                <div key={day.key}>
                    <h4 className={cn('mb-2 flex items-center gap-2 text-sm font-semibold', day.isToday && 'text-primary')}>
                        {day.isToday && <span className="size-1.5 rounded-full bg-primary" aria-hidden />}
                        {day.label}
                        <span className="font-normal text-muted-foreground">· {day.items.length}</span>
                        {day.isToday && (
                            <span className="rounded-md bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                {t('cabinet.homework_due_badge')}
                            </span>
                        )}
                    </h4>
                    <div className="flex flex-col gap-3">
                        {day.items.map((h) => (
                            <HomeworkCard key={h.id} h={h} />
                        ))}
                    </div>
                </div>
            ))}

            {/* Istoricul — pliat, dar grupat pe zile la fel ca viitorul (aceeași structură). */}
            {past.length > 0 && (
                <details className="rounded-xl border bg-muted/20 px-4 py-3">
                    <summary className="cursor-pointer text-sm font-medium text-muted-foreground">
                        {t('cabinet.homework_past')} ({past.length})
                    </summary>
                    <div className="mt-3 flex flex-col gap-4">
                        {pastDays.map((day) => (
                            <div key={day.key}>
                                <h4 className="mb-2 text-xs font-semibold text-muted-foreground">
                                    {day.label} <span className="font-normal">· {day.items.length}</span>
                                </h4>
                                <div className="flex flex-col gap-3">
                                    {day.items.map((h) => (
                                        <HomeworkCard key={h.id} h={h} muted />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </details>
            )}
        </div>
    );
}

export function HomeworkCard({ h, muted = false }: { h: HomeworkItem; muted?: boolean }) {
    const t = useTranslations();

    return (
        <article className={`rounded-xl border bg-card p-4 shadow-sm ${muted ? 'opacity-80' : ''}`}>
            <div className="mb-1 flex flex-wrap items-center gap-2">
                <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                {/* Cine a dat tema — context, nu decor: la teme neclare, familia știe pe cine întreabă. */}
                {h.teacher && <span className="text-xs text-muted-foreground">{h.teacher}</span>}
                {/* Termenul e informația principală; data atribuirii rămâne context secundar. */}
                {h.due && (
                    <span className="text-xs text-muted-foreground">
                        {t('cabinet.homework_due_on')} <span className="font-medium text-foreground">{h.due}</span>
                    </span>
                )}
                <span className="text-xs text-muted-foreground">
                    {t('cabinet.homework_assigned_on')} {h.date}
                </span>
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
            {h.links.filter(Boolean).length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {h.links.filter(Boolean).map((link, i) =>
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
    );
}
