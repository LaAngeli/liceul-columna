import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Coffee, Soup } from 'lucide-react';
import { useState } from 'react';
import { DateRangePicker } from '@/components/cabinet/date-range-picker';
import type { DateRangeCalendar, DateRangeLabels } from '@/components/cabinet/date-range-picker';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

interface MenuRow {
    label: string;
    value: string;
}

interface DayMenu {
    breakfast: MenuRow[];
    lunch: MenuRow[];
    notes: string | null;
}

interface Day {
    date: string;
    label: string;
    short: string;
    isToday: boolean;
    menu: DayMenu | null;
}

interface Group {
    /** Eticheta săptămânii — doar când perioada cuprinde mai multe (lună, arhivă). */
    label: string | null;
    days: Day[];
}

interface Pill {
    key: string;
    label: string;
    href: string;
    active: boolean;
}

interface Period {
    mode: string;
    pills: Pill[];
    label: string;
    prev: string | null;
    next: string | null;
    todayHref: string;
    isCurrent: boolean;
    from: string | null;
    until: string | null;
    /** Fraza perioadei alese, formatată pe server — aceleași cazuri ca în panou. */
    customLabel: string;
    calendar: DateRangeCalendar;
    labels: DateRangeLabels & { aria: string; today: string };
}

interface Props {
    period: Period;
    groups: Group[];
    today: string;
}

/** O secțiune de masă (Dejun/Prânz): rubricile completate ale zilei, etichetate de server. */
function MealSection({ title, icon: Icon, rows }: { title: string; icon: typeof Coffee; rows: MenuRow[] }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <div>
            <h3 className="flex items-center gap-1.5 text-sm font-semibold">
                <Icon className="size-4 text-primary" aria-hidden />
                {title}
            </h3>
            <dl className="mt-2 divide-y divide-border/60">
                {rows.map((row) => (
                    <div key={row.label} className="flex items-baseline justify-between gap-3 py-1.5">
                        <dt className="shrink-0 text-xs text-muted-foreground">{row.label}</dt>
                        <dd className="text-right text-sm font-medium">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

export default function CanteenPage({ period, groups, today }: Props) {
    const t = useTranslations();

    const days = groups.flatMap((group) => group.days);
    const isCustom = period.mode === 'personalizat';

    // Ziua activă pe mobil: azi dacă e în perioada afișată, altfel prima zi.
    const [selected, setSelected] = useState<string>(days.find((day) => day.date === today)?.date ?? days[0]?.date ?? '');

    /**
     * Capetele alese în calendar merg la SERVER, nu în starea locală: perioada rămâne adresabilă
     * (favorite, link trimis mai departe) — același principiu ca restul navigării din pagină, și
     * echivalentul lui `setCustomRange`/`clearCustomRange` din bara panoului.
     */
    const setRange = (start: string | null, end: string | null) => {
        router.get(
            '/cabinet/meniu',
            { mod: 'personalizat', de: start ?? undefined, pana: end ?? undefined },
            { preserveScroll: true, preserveState: true },
        );
    };

    const dayCard = (day: Day) => (
        <article
            key={day.date}
            className={cn('flex flex-col gap-4 rounded-xl border bg-card p-4', day.isToday && 'border-primary/50 ring-1 ring-primary/30')}
        >
            <header className="flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">{day.label}</h2>
                {day.isToday && (
                    <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                        {t('cabinet.canteen_today_badge', 'Azi')}
                    </span>
                )}
            </header>

            {day.menu === null ? (
                <p className="py-4 text-center text-sm text-muted-foreground">
                    {t('cabinet.canteen_day_empty', 'Meniul zilei nu a fost publicat încă.')}
                </p>
            ) : (
                <>
                    <MealSection title={t('cabinet.canteen_breakfast', 'Dejun')} icon={Coffee} rows={day.menu.breakfast} />
                    <MealSection title={t('cabinet.canteen_lunch', 'Prânz')} icon={Soup} rows={day.menu.lunch} />
                    {day.menu.notes !== null && <p className="rounded-lg bg-muted/60 p-2.5 text-xs text-muted-foreground">{day.menu.notes}</p>}
                </>
            )}
        </article>
    );

    const selectedDay = days.find((day) => day.date === selected) ?? days[0];

    return (
        <>
            <Head title={t('cabinet.canteen_title', 'Meniul cantinei')} />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{t('cabinet.canteen_title', 'Meniul cantinei')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('cabinet.canteen_hint', 'Dejunul și prânzul fiecărei zile, așa cum sunt publicate de administrație.')}
                    </p>
                </div>

                {/* BARA TEMPORALĂ — aceleași moduri și aceeași semantică precum bara panoului.
                    Pastilele sunt LINKURI reale: perioada e adresabilă și supraviețuiește unui reload. */}
                <div className="flex flex-col gap-3">
                    <nav className="flex flex-wrap gap-1.5" aria-label={period.labels.aria}>
                        {period.pills.map((pill) => (
                            <Link
                                key={pill.key}
                                href={pill.href}
                                preserveScroll
                                aria-current={pill.active ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 items-center rounded-full border px-3.5 text-sm font-medium transition-colors',
                                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    pill.active
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {pill.label}
                            </Link>
                        ))}
                    </nav>

                    {isCustom ? (
                        <DateRangePicker
                            from={period.from}
                            until={period.until}
                            label={period.customLabel}
                            calendar={period.calendar}
                            labels={period.labels}
                            onApply={setRange}
                            onClear={() => setRange(null, null)}
                        />
                    ) : (
                        period.prev !== null &&
                        period.next !== null && (
                            <div className="flex items-center justify-between gap-2">
                                <Link
                                    href={period.prev}
                                    preserveScroll
                                    aria-label={period.labels.prev}
                                    className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                                >
                                    <ChevronLeft className="size-4" aria-hidden />
                                </Link>
                                <div className="text-center">
                                    <p className="text-sm font-medium">{period.label}</p>
                                    {!period.isCurrent && (
                                        <Link href={period.todayHref} preserveScroll className="text-xs text-primary underline underline-offset-2">
                                            {period.labels.today}
                                        </Link>
                                    )}
                                </div>
                                <Link
                                    href={period.next}
                                    preserveScroll
                                    aria-label={period.labels.next}
                                    className="flex min-h-11 min-w-11 items-center justify-center rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                                >
                                    <ChevronRight className="size-4" aria-hidden />
                                </Link>
                            </div>
                        )
                    )}
                </div>

                {days.length === 0 ? (
                    <p className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                        {t('cabinet.canteen_period_empty', 'Niciun meniu publicat în perioada aleasă.')}
                    </p>
                ) : (
                    <>
                        {/* Mobil: cipuri de zi + ziua aleasă. Desktop: grila perioadei, pe săptămâni. */}
                        <div
                            className="flex gap-2 overflow-x-auto pb-1 lg:hidden"
                            role="tablist"
                            aria-label={t('cabinet.canteen_title', 'Meniul cantinei')}
                        >
                            {days.map((day) => (
                                <button
                                    key={day.date}
                                    type="button"
                                    role="tab"
                                    aria-selected={day.date === selected}
                                    onClick={() => setSelected(day.date)}
                                    className={cn(
                                        'min-h-11 shrink-0 rounded-lg border px-3 text-sm',
                                        day.date === selected ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-muted',
                                    )}
                                >
                                    {day.short}
                                </button>
                            ))}
                        </div>

                        <div className="lg:hidden">{selectedDay !== undefined && dayCard(selectedDay)}</div>

                        <div className="hidden flex-col gap-6 lg:flex">
                            {groups.map((group, index) => (
                                <section key={group.label ?? index} className="flex flex-col gap-3">
                                    {/* Eticheta apare doar când perioada cuprinde mai multe săptămâni. */}
                                    {group.label !== null && (
                                        <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{group.label}</h2>
                                    )}
                                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">{group.days.map(dayCard)}</div>
                                </section>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

CanteenPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_canteen', href: '#' },
    ],
};
