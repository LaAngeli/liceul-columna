import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { FilterPills } from '@/components/cabinet/catalog/filter-pills';
import { EmptyState } from '@/components/cabinet/empty-state';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { pluralKey, useLocale, useTranslations } from '@/lib/i18n';
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

/** Fereastra de dată aleasă din calendar: o zi (start === end) sau un interval. Chei ISO Y-m-d. */
interface DateRange {
    start: string;
    end: string;
}

/** ISO Y-m-d → d.m.Y pentru afișare (fără a construi un Date — evită derapajul de fus). */
function isoToDmy(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}.${m}.${y}`;
}

/** Luni–duminică ale săptămânii care conține ziua dată. */
function weekRange(todayIso: string): DateRange {
    const d = new Date(`${todayIso}T00:00:00`);
    const mondayOffset = (d.getDay() + 6) % 7; // getDay(): duminică=0 → offset față de luni
    const monday = new Date(d);
    monday.setDate(d.getDate() - mondayOffset);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);

    return { start: localIso(monday), end: localIso(sunday) };
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
 * (pastile cu contoare — același tipar ca registrul de absențe; comutare instant, client-side)
 * și, opțional (`showCalendar`), un FILTRU DE DATĂ: navighezi la o zi anume sau la un interval.
 *
 * `showCalendar` e activ DOAR în modulul dedicat „Teme", care primește tot setul anului — acolo
 * navigarea la orice zi are sens. Fișa elevului („Orar & teme") primește doar fereastra recentă,
 * deci rămâne pe vederea implicită, fără calendar.
 */
export function HomeworkByDay({ homework, showCalendar = false }: { homework: HomeworkItem[]; showCalendar?: boolean }) {
    const t = useTranslations();
    const [activeSubject, setActiveSubject] = useState<string | 'all'>('all');
    // Fereastra de dată aleasă din calendar; null = fără filtru de dată (vederea implicită).
    const [range, setRange] = useState<DateRange | null>(null);

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

    // Cele mai voluminoase discipline întâi — la pliere, pastilele vizibile sunt cele relevante.
    subjects.sort((a, b) => b.total - a.total || a.name.localeCompare(b.name));

    // Filtrele se COMPUN: disciplina întâi (alimentează și punctele din calendar), apoi data.
    const bySubject = activeSubject === 'all' ? homework : homework.filter((h) => h.subject === activeSubject);

    return (
        <div className="flex flex-col gap-4">
            {/* Filtrul pe discipline — pastile care se înfășoară (fără scroll orizontal). */}
            <FilterPills
                options={subjects.map((s) => ({ key: s.name, label: s.name, count: s.total, dot: s.hasToday }))}
                active={activeSubject}
                onChange={(key) => setActiveSubject(key === 'all' ? 'all' : String(key))}
                allCount={homework.length}
                ariaLabel={t('cabinet.subject')}
            />

            {showCalendar && <HomeworkDateFilter homework={bySubject} range={range} onChange={setRange} />}

            {range ? (
                <HomeworkRangeView homework={bySubject} range={range} onClear={() => setRange(null)} />
            ) : (
                <HomeworkDefaultView homework={bySubject} />
            )}
        </div>
    );
}

/** Vederea implicită: „de făcut" (azi/viitor) grupat pe zile + istoricul pliat, tot pe zile. */
function HomeworkDefaultView({ homework }: { homework: HomeworkItem[] }) {
    const t = useTranslations();

    const upcoming = homework.filter((h) => h.status !== 'past');
    const past = homework.filter((h) => h.status === 'past');

    // „Mâine" se decide pe client (doar etichetă; gruparea folosește cheia serverului).
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowIso = localIso(tomorrow);

    const days = groupByDay(upcoming, t('cabinet.homework_today'), t('cabinet.homework_tomorrow'), tomorrowIso);
    const pastDays = groupByDay(past, t('cabinet.homework_today'), t('cabinet.homework_tomorrow'), tomorrowIso);

    return (
        <>
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
        </>
    );
}

/** Vederea pe fereastră de dată: temele din ziua/intervalul ales, cronologic, cu antet + șterge. */
function HomeworkRangeView({ homework, range, onClear }: { homework: HomeworkItem[]; range: DateRange; onClear: () => void }) {
    const t = useTranslations();

    // Cronologic ASC în fereastra aleasă (server-ordering nu mai e garantat după feliere).
    const items = homework
        .filter((h) => h.effectiveDate >= range.start && h.effectiveDate <= range.end)
        .sort((a, b) => a.effectiveDate.localeCompare(b.effectiveDate) || a.subject.localeCompare(b.subject));

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const days = groupByDay(items, t('cabinet.homework_today'), t('cabinet.homework_tomorrow'), localIso(tomorrow));

    const label = range.start === range.end ? isoToDmy(range.start) : `${isoToDmy(range.start)} – ${isoToDmy(range.end)}`;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-primary/5 px-3.5 py-2.5">
                <p className="text-sm font-semibold">
                    {label}
                    <span className="ml-1.5 font-normal text-muted-foreground">
                        · {items.length} {t(pluralKey('cabinet.hw_items', items.length))}
                    </span>
                </p>
                <button
                    type="button"
                    onClick={onClear}
                    className="inline-flex h-8 cursor-pointer items-center gap-1 rounded-md px-2.5 text-xs font-medium text-primary hover:bg-primary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <X className="size-3.5" aria-hidden />
                    {t('cabinet.hw_clear_dates')}
                </button>
            </div>

            {items.length === 0 ? (
                <EmptyState title={t(range.start === range.end ? 'cabinet.hw_none_day' : 'cabinet.hw_none_range')} />
            ) : (
                days.map((day) => (
                    <div key={day.key}>
                        <h4 className={cn('mb-2 flex items-center gap-2 text-sm font-semibold', day.isToday && 'text-primary')}>
                            {day.isToday && <span className="size-1.5 rounded-full bg-primary" aria-hidden />}
                            {day.label}
                            <span className="font-normal text-muted-foreground">· {day.items.length}</span>
                        </h4>
                        <div className="flex flex-col gap-3">
                            {day.items.map((h) => (
                                <HomeworkCard key={h.id} h={h} />
                            ))}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}

/** Bara de filtrare pe dată: scurtături (Toate/Azi/Săptămâna) + calendar lunar pliabil. */
function HomeworkDateFilter({
    homework,
    range,
    onChange,
}: {
    homework: HomeworkItem[];
    range: DateRange | null;
    onChange: (range: DateRange | null) => void;
}) {
    const t = useTranslations();
    const [open, setOpen] = useState(false);
    // Primul capăt al unui interval încă neînchis: al doilea click îl completează.
    const [pendingStart, setPendingStart] = useState<string | null>(null);

    const todayIso = localIso(new Date());
    const week = weekRange(todayIso);

    // Zilele care AU teme (după filtrul de disciplină) — punctele din calendar ghidează navigarea.
    const homeworkDates = useMemo(() => new Set(homework.map((h) => h.effectiveDate)), [homework]);

    const isTodayActive = range?.start === todayIso && range?.end === todayIso;
    const isWeekActive = range?.start === week.start && range?.end === week.end;

    /** Selecția din calendar: primul click = o zi; al doilea = interval; al treilea reia. */
    function pickDay(iso: string): void {
        if (pendingStart === null) {
            setPendingStart(iso);
            onChange({ start: iso, end: iso });
        } else {
            const [start, end] = pendingStart <= iso ? [pendingStart, iso] : [iso, pendingStart];
            onChange({ start, end });
            setPendingStart(null);
        }
    }

    function preset(next: DateRange | null): void {
        onChange(next);
        setPendingStart(null);
    }

    // `w-full sm:w-auto` + `justify-center`: pe mobil pastilele umplu celula de grilă (2×2 egal),
    // pe desktop revin la lățime-după-conținut. min-h-11 = țintă tactilă ≥44px (regula proiectului).
    const chip = (active: boolean) =>
        cn(
            'inline-flex min-h-11 w-full cursor-pointer items-center justify-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors sm:w-auto',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            active ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
        );

    return (
        <div className="flex flex-col gap-2">
            {/* MOBIL: card de filtre (bordură + fundal discret) care SEPARĂ zona de dată de pastilele
                de disciplină de deasupra, cu cele 4 controale într-o grilă 2×2 ECHILIBRATĂ — nu
                ruperea 3+1 care părea accidentală. DESKTOP (≥sm): cardul dispare și controalele revin
                pe un singur rând, ca înainte. */}
            <div
                className={cn(
                    'grid grid-cols-2 gap-1.5 rounded-xl border bg-muted/30 p-2',
                    'sm:flex sm:flex-wrap sm:items-center sm:border-0 sm:bg-transparent sm:p-0',
                )}
                role="group"
                aria-label={t('cabinet.hw_filter_dates')}
            >
                <button type="button" onClick={() => preset(null)} aria-pressed={range === null} className={chip(range === null)}>
                    {t('cabinet.hw_dates_all')}
                </button>
                <button type="button" onClick={() => preset({ start: todayIso, end: todayIso })} aria-pressed={isTodayActive} className={chip(isTodayActive)}>
                    {t('cabinet.hw_date_today')}
                </button>
                <button type="button" onClick={() => preset(week)} aria-pressed={isWeekActive} className={chip(isWeekActive)}>
                    {t('cabinet.hw_dates_week')}
                </button>
                <button type="button" onClick={() => setOpen((v) => !v)} aria-expanded={open} className={chip(open)}>
                    <CalendarDays className="size-3.5" aria-hidden />
                    {t('cabinet.hw_dates_calendar')}
                </button>
            </div>

            {open && <HomeworkCalendar homeworkDates={homeworkDates} range={range} todayIso={todayIso} onPick={pickDay} />}
        </div>
    );
}

/** Calendarul lunar interactiv: aceeași grilă ca vederea de absențe, dar cu selecție de zi/interval. */
function HomeworkCalendar({
    homeworkDates,
    range,
    todayIso,
    onPick,
}: {
    homeworkDates: Set<string>;
    range: DateRange | null;
    todayIso: string;
    onPick: (iso: string) => void;
}) {
    const t = useTranslations();
    const locale = useLocale();
    const localeTag = locale === 'ru' ? 'ru-RU' : locale === 'en' ? 'en-US' : 'ro-RO';

    // Marginile de navigare = luna primei și ultimei teme (nu rătăcim în luni goale la nesfârșit).
    const sorted = useMemo(() => [...homeworkDates].sort(), [homeworkDates]);
    const minMonth = sorted[0]?.slice(0, 7) ?? todayIso.slice(0, 7);
    const maxMonth = sorted[sorted.length - 1]?.slice(0, 7) ?? todayIso.slice(0, 7);

    const [cursor, setCursor] = useState(() => {
        const todayMonth = todayIso.slice(0, 7);

        return todayMonth < minMonth ? minMonth : todayMonth > maxMonth ? maxMonth : todayMonth;
    });

    // Lu…Du în limba interfeței (1 ian. 2024 e o luni).
    const weekdays = useMemo(() => {
        const fmt = new Intl.DateTimeFormat(localeTag, { weekday: 'short' });

        return Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2024, 0, 1 + i)));
    }, [localeTag]);

    const [year, monthIndex] = [Number(cursor.slice(0, 4)), Number(cursor.slice(5, 7)) - 1];
    const first = new Date(year, monthIndex, 1);
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const offset = (first.getDay() + 6) % 7; // getDay(): duminică=0 → offset față de luni
    const monthLabel = new Intl.DateTimeFormat(localeTag, { month: 'long', year: 'numeric' }).format(first);

    function shiftMonth(delta: number): void {
        const d = new Date(year, monthIndex + delta, 1);
        setCursor(localIso(d).slice(0, 7));
    }

    return (
        // max-w-sm: e un SELECTOR de dată, nu o vedere de lună — pe container lat, `aspect-square`
        // ar face celulele cât un ecran. Sub `sm` ocupă lățimea disponibilă (mobil ~358px < 24rem).
        <div className="w-full max-w-sm rounded-xl border bg-card p-3.5 shadow-sm">
            <div className="mb-2 flex items-center justify-between">
                <button
                    type="button"
                    onClick={() => shiftMonth(-1)}
                    disabled={cursor <= minMonth}
                    aria-label={t('cabinet.hw_prev_month')}
                    className="inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <ChevronLeft className="size-4" aria-hidden />
                </button>
                <span className="text-sm font-semibold first-letter:uppercase">{monthLabel}</span>
                <button
                    type="button"
                    onClick={() => shiftMonth(1)}
                    disabled={cursor >= maxMonth}
                    aria-label={t('cabinet.hw_next_month')}
                    className="inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <ChevronRight className="size-4" aria-hidden />
                </button>
            </div>

            <div className="grid grid-cols-7 gap-1 text-center">
                {weekdays.map((label) => (
                    <span key={label} className="pb-1 text-[10px] font-medium text-muted-foreground">
                        {label}
                    </span>
                ))}

                {Array.from({ length: offset }, (_, i) => (
                    <span key={`gap-${i}`} aria-hidden />
                ))}

                {Array.from({ length: daysInMonth }, (_, i) => {
                    const day = i + 1;
                    const iso = `${cursor}-${String(day).padStart(2, '0')}`;
                    const hasHomework = homeworkDates.has(iso);
                    const selected = range !== null && iso >= range.start && iso <= range.end;
                    const isToday = iso === todayIso;

                    return (
                        <button
                            key={iso}
                            type="button"
                            onClick={() => onPick(iso)}
                            aria-pressed={selected}
                            aria-label={hasHomework ? `${isoToDmy(iso)} — ${t('cabinet.hw_has_homework')}` : isoToDmy(iso)}
                            className={cn(
                                'relative flex aspect-square min-h-9 items-center justify-center rounded-md text-xs tabular-nums transition-colors',
                                'cursor-pointer hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                selected && 'bg-primary text-primary-foreground hover:bg-primary',
                                !selected && isToday && 'ring-1 ring-primary/50',
                                !selected && !hasHomework && 'text-muted-foreground/50',
                            )}
                        >
                            {day}
                            {/* Punctul semnalează zilele cu teme — dispare pe fundal selectat (contrast). */}
                            {hasHomework && !selected && (
                                <span className="absolute bottom-1 size-1 rounded-full bg-primary" aria-hidden />
                            )}
                        </button>
                    );
                })}
            </div>

            <p className="mt-2 text-[11px] text-muted-foreground">{t('cabinet.hw_dates_hint')}</p>
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
                                className="inline-flex min-h-11 items-center rounded-md bg-muted px-3 text-xs text-primary underline-offset-2 hover:underline md:min-h-0 md:px-2 md:py-0.5"
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
