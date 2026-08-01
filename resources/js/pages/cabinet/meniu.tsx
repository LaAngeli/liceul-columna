import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Coffee, Soup } from 'lucide-react';
import { useState } from 'react';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { cn } from '@/lib/utils';

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

interface Props {
    week: { label: string; prev: string; next: string; isCurrent: boolean };
    days: Day[];
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

export default function CanteenPage({ week, days, today }: Props) {
    const t = useTranslations();

    // Ziua activă pe mobil: azi dacă e în săptămâna afișată, altfel prima zi.
    const [selected, setSelected] = useState<string>(days.find((day) => day.date === today)?.date ?? days[0]?.date ?? '');

    const dayCard = (day: Day) => (
        <article
            key={day.date}
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-4',
                day.isToday && 'border-primary/50 ring-1 ring-primary/30',
            )}
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
                    {day.menu.notes !== null && (
                        <p className="rounded-lg bg-muted/60 p-2.5 text-xs text-muted-foreground">{day.menu.notes}</p>
                    )}
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

                {/* Navigarea pe săptămâni: linkuri reale (server-side), nu stare de client. */}
                <nav className="flex items-center justify-between gap-2" aria-label={t('cabinet.canteen_week_aria', 'Navigare pe săptămâni')}>
                    <Link
                        href={`/cabinet/meniu?data=${week.prev}`}
                        preserveScroll
                        className="flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                        aria-label={t('cabinet.canteen_prev_week', 'Săptămâna precedentă')}
                    >
                        <ChevronLeft className="size-4" aria-hidden />
                        <span className="hidden sm:inline">{t('cabinet.canteen_prev_week', 'Săptămâna precedentă')}</span>
                    </Link>
                    <div className="text-center">
                        <p className="text-sm font-medium">{week.label}</p>
                        {!week.isCurrent && (
                            <Link href="/cabinet/meniu" preserveScroll className="text-xs text-primary underline underline-offset-2">
                                {t('cabinet.canteen_current_week', 'Înapoi la săptămâna curentă')}
                            </Link>
                        )}
                    </div>
                    <Link
                        href={`/cabinet/meniu?data=${week.next}`}
                        preserveScroll
                        className="flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                        aria-label={t('cabinet.canteen_next_week', 'Săptămâna următoare')}
                    >
                        <span className="hidden sm:inline">{t('cabinet.canteen_next_week', 'Săptămâna următoare')}</span>
                        <ChevronRight className="size-4" aria-hidden />
                    </Link>
                </nav>

                {/* Mobil: cipuri de zi + ziua aleasă. Desktop: toată săptămâna în grilă. */}
                <div className="flex gap-2 overflow-x-auto pb-1 lg:hidden" role="tablist" aria-label={t('cabinet.canteen_title', 'Meniul cantinei')}>
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

                <div className="hidden gap-4 lg:grid lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">{days.map(dayCard)}</div>
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
