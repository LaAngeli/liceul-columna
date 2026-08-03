import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Calendarul intervalului liber — PORT FIDEL al celui din panoul staff
 * (`filament/catalog/partials/date-range-calendar` + componenta Alpine `cxDateRange`), ca familia
 * și personalul să aleagă o perioadă exact la fel. Panoul e Alpine, cabinetul React; comportamentul
 * e însă același, până la detaliu:
 *
 *   • UN SINGUR calendar, într-un popover — nu două câmpuri de dată. Utilizatorul vede perioada ca
 *     întreg, nu tastează separat fiecare capăt.
 *   • Primul clic alege ZIUA (selecție deja validă, aplicată pe loc — cine caută o zi a terminat);
 *     al doilea o extinde la interval și pliază calendarul. Un clic după un interval complet
 *     reîncepe selecția.
 *   • În timpul extinderii, ziua de sub cursor ține locul capătului nealess — intervalul se vede
 *     înainte de a fi confirmat.
 *   • Eticheta lunii deschide vederea de luni, în ACELAȘI panou (fără salt de layout).
 *   • Pe telefon: foaie de jos + fundal estompat, ca atingerea „pe lângă" să nu nimerească în
 *     conținutul de dedesubt.
 *
 * Niciun cuvânt nu trăiește aici: lunile, zilele și îndrumările vin traduse de pe server (aceleași
 * chei ca bara panoului), deci componenta merge identic în RO/RU/EN.
 */

export interface DateRangeCalendar {
    months: string[];
    weekdays: string[];
    today: string;
}

export interface DateRangeLabels {
    pick: string;
    prev: string;
    next: string;
    done: string;
    clear: string;
    hintStart: string;
    hintExtend: string;
    hintRestart: string;
}

const iso = (year: number, month: number, day: number): string =>
    `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

export function DateRangePicker({
    from,
    until,
    label,
    calendar,
    labels,
    onApply,
    onClear,
}: {
    from: string | null;
    until: string | null;
    /** Fraza perioadei alese, formatată pe server (aceleași cazuri ca în panou). */
    label: string;
    calendar: DateRangeCalendar;
    labels: DateRangeLabels;
    onApply: (start: string | null, end: string | null) => void;
    onClear: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [view, setView] = useState<'days' | 'months'>('days');
    const [hover, setHover] = useState<string | null>(null);
    // „extindere" = s-a ales o zi și următorul clic o transformă în interval.
    const [extending, setExtending] = useState(false);

    /**
     * Selecția în curs. Serverul e sursa de adevăr pentru perioadă; ciorna acoperă doar fereastra
     * dintre clic și răspunsul navigării. DERIVATĂ, nu oglindită printr-un efect: sincronizarea
     * prop→state ar fi produs randări în cascadă (și e exact ce interzice regula de hooks).
     */
    const [draft, setDraft] = useState<{ start: string | null; end: string | null } | null>(null);
    const start = draft !== null ? draft.start : from;
    const end = draft !== null ? draft.end : until;

    const anchor = from ?? calendar.today;
    const [year, setYear] = useState(() => Number(anchor.slice(0, 4)));
    const [month, setMonth] = useState(() => Number(anchor.slice(5, 7)) - 1);

    const root = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && close();
        const onClick = (event: MouseEvent) => {
            if (root.current !== null && !root.current.contains(event.target as Node)) {
                close();
            }
        };

        window.addEventListener('keydown', onKey);
        document.addEventListener('mousedown', onClick);

        return () => {
            window.removeEventListener('keydown', onKey);
            document.removeEventListener('mousedown', onClick);
        };
    });

    function close() {
        setOpen(false);
        setExtending(false);
        setHover(null);
        setDraft(null);
    }

    function openPanel() {
        setView('days');
        setExtending(false);
        setHover(null);

        const target = start ?? calendar.today;
        setYear(Number(target.slice(0, 4)));
        setMonth(Number(target.slice(5, 7)) - 1);
        setOpen(true);
    }

    // Casetele lunii: goluri pentru aliniere (săptămâna începe LUNI) + zilele reale.
    const cells = useMemo(() => {
        const lead = (new Date(year, month, 1).getDay() + 6) % 7;
        const total = new Date(year, month + 1, 0).getDate();
        const out: { key: string; date: string | null; day: number | null }[] = [];

        for (let i = 0; i < lead; i++) {
            out.push({ key: `blank-${i}`, date: null, day: null });
        }

        for (let day = 1; day <= total; day++) {
            const date = iso(year, month, day);
            out.push({ key: date, date, day });
        }

        return out;
    }, [year, month]);

    // Capetele PREVIZUALIZATE: ziua de sub cursor ține locul celui nealess.
    const previewFrom = extending && hover !== null && start !== null ? (hover < start ? hover : start) : start;
    const previewTo = extending && hover !== null && start !== null ? (hover < start ? start : hover) : end;

    const isEdge = (date: string) => date === previewFrom || date === previewTo;
    const inRange = (date: string) => previewFrom !== null && previewTo !== null && date >= previewFrom && date <= previewTo;

    function select(date: string) {
        if (!extending) {
            setDraft({ start: date, end: date });
            setExtending(true);
            setHover(null);
            onApply(date, date);

            return;
        }

        const [nextStart, nextEnd] = date < (start ?? date) ? [date, start] : [start, date];

        setDraft({ start: nextStart, end: nextEnd });
        setExtending(false);
        setHover(null);
        onApply(nextStart, nextEnd);
        close();
    }

    const hint = extending ? labels.hintExtend : start !== null ? labels.hintRestart : labels.hintStart;
    const isEmpty = from === null && until === null;

    return (
        <div ref={root} className="relative max-sm:w-full">
            {/* DECLANȘATORUL: perioada aleasă (frază de pe server) sau invitația de a alege. */}
            <button
                type="button"
                onClick={() => (open ? close() : openPanel())}
                aria-expanded={open}
                aria-haspopup="dialog"
                className={cn(
                    'inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors max-sm:w-full',
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    isEmpty ? 'border-border bg-card hover:bg-muted' : 'border-primary bg-primary/10 text-primary',
                )}
            >
                <CalendarDays className="size-4 shrink-0" aria-hidden />
                <span className="truncate">{isEmpty ? labels.pick : label}</span>
                <ChevronDown className={cn('size-4 shrink-0 transition-transform', open && 'rotate-180')} aria-hidden />
            </button>

            {/* Fundal pe telefon: fără el, atingerea „pe lângă" ar nimeri în conținutul de dedesubt. */}
            {open && <div className="fixed inset-0 z-30 bg-foreground/40 sm:hidden" aria-hidden onClick={close} />}

            {open && (
                <div
                    role="dialog"
                    aria-label={labels.pick}
                    className={cn(
                        'absolute start-0 top-full z-40 mt-2 w-72 rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg',
                        'max-sm:fixed max-sm:inset-x-0 max-sm:top-auto max-sm:bottom-0 max-sm:mt-0 max-sm:w-auto max-sm:rounded-b-none max-sm:p-4 max-sm:pb-6',
                    )}
                >
                    {/* Antet: ‹ [Luna Anul] › — eticheta comută pe vederea de luni, în același panou. */}
                    <div className="flex items-center justify-between gap-1">
                        <button
                            type="button"
                            onClick={() => (view === 'days' ? (month === 0 ? (setMonth(11), setYear(year - 1)) : setMonth(month - 1)) : setYear(year - 1))}
                            aria-label={labels.prev}
                            className="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted max-sm:size-11"
                        >
                            <ChevronLeft className="size-5" aria-hidden />
                        </button>
                        <button
                            type="button"
                            onClick={() => setView(view === 'days' ? 'months' : 'days')}
                            className="flex-1 rounded-lg px-2 py-1.5 text-sm font-semibold hover:bg-muted max-sm:min-h-11"
                        >
                            {view === 'days' ? `${calendar.months[month]} ${year}` : year}
                        </button>
                        <button
                            type="button"
                            onClick={() => (view === 'days' ? (month === 11 ? (setMonth(0), setYear(year + 1)) : setMonth(month + 1)) : setYear(year + 1))}
                            aria-label={labels.next}
                            className="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted max-sm:size-11"
                        >
                            <ChevronRight className="size-5" aria-hidden />
                        </button>
                    </div>

                    {view === 'days' ? (
                        <div className="mt-2">
                            <div className="grid grid-cols-7 gap-y-1">
                                {calendar.weekdays.map((weekday) => (
                                    <div key={weekday} className="py-1 text-center text-xs font-medium text-muted-foreground">
                                        {weekday}
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-7" onMouseLeave={() => setHover(null)}>
                                {cells.map((cell) => (
                                    <div
                                        key={cell.key}
                                        className={cn(
                                            'p-px',
                                            cell.date !== null && inRange(cell.date) && 'bg-primary/10',
                                            cell.date !== null && cell.date === previewFrom && 'rounded-s-lg',
                                            cell.date !== null && cell.date === previewTo && 'rounded-e-lg',
                                        )}
                                    >
                                        {cell.date === null ? (
                                            <div className="aspect-square" />
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() => select(cell.date as string)}
                                                onMouseEnter={() => setHover(cell.date)}
                                                aria-current={cell.date === calendar.today ? 'date' : undefined}
                                                className={cn(
                                                    'flex aspect-square w-full items-center justify-center rounded-lg text-sm transition-colors max-sm:min-h-11',
                                                    isEdge(cell.date)
                                                        ? 'bg-primary font-semibold text-primary-foreground'
                                                        : inRange(cell.date)
                                                          ? 'text-primary'
                                                          : 'hover:bg-muted',
                                                    cell.date === calendar.today && !isEdge(cell.date) && 'ring-1 ring-primary ring-inset',
                                                )}
                                            >
                                                {cell.day}
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="mt-2 grid grid-cols-3 gap-1">
                            {calendar.months.map((name, index) => (
                                <button
                                    key={name}
                                    type="button"
                                    onClick={() => {
                                        setMonth(index);
                                        setView('days');
                                    }}
                                    className={cn(
                                        'rounded-lg px-2 py-2 text-sm transition-colors max-sm:min-h-11',
                                        index === month ? 'bg-primary font-semibold text-primary-foreground' : 'hover:bg-muted',
                                    )}
                                >
                                    {name}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Subsolul: ce urmează (ține locul hover-ului pe touch) + ieșirile. */}
                    <div className="mt-3 border-t pt-3">
                        <p className="text-xs text-muted-foreground">{hint}</p>
                        <div className="mt-2 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setDraft({ start: null, end: null });
                                    setHover(null);
                                    setExtending(false);
                                    onClear();
                                }}
                                className="rounded-lg px-2 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted max-sm:min-h-11"
                            >
                                {labels.clear}
                            </button>
                            <button
                                type="button"
                                onClick={close}
                                className="rounded-lg bg-muted px-3 py-1.5 text-sm font-medium hover:bg-muted/70 max-sm:min-h-11"
                            >
                                {labels.done}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
