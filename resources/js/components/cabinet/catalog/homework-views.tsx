import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { useTranslations } from '@/lib/i18n';

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
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
}

/** Data locală ca Y-m-d (NU toISOString — UTC-ul ar aluneca o zi noaptea). */
export function localIso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** Temele grupate pe ZILE după data efectivă (termen ?? atribuire); istoricul stă pliat. */
export function HomeworkByDay({ homework }: { homework: HomeworkItem[] }) {
    const t = useTranslations();

    const upcoming = homework.filter((h) => h.status !== 'past');
    const past = homework.filter((h) => h.status === 'past');

    // „Mâine" se decide pe client (doar etichetă; gruparea folosește cheia serverului).
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowIso = localIso(tomorrow);

    const days: { key: string; label: string; isToday: boolean; items: HomeworkItem[] }[] = [];

    for (const h of upcoming) {
        const last = days[days.length - 1];

        if (last && last.key === h.effectiveDate) {
            last.items.push(h);
            continue;
        }

        days.push({
            key: h.effectiveDate,
            label:
                h.status === 'today'
                    ? t('cabinet.homework_today')
                    : h.effectiveDate === tomorrowIso
                      ? t('cabinet.homework_tomorrow')
                      : h.dayLabel,
            isToday: h.status === 'today',
            items: [h],
        });
    }

    return (
        <div className="flex flex-col gap-4">
            {days.length === 0 && (
                <p className="rounded-xl border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                    {t('cabinet.homework_none_upcoming')}
                </p>
            )}
            {days.map((day) => (
                <div key={day.key}>
                    <h4 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                        {day.label}
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

            {past.length > 0 && (
                <details className="rounded-xl border bg-muted/20 px-4 py-3">
                    <summary className="text-sm font-medium text-muted-foreground">
                        {t('cabinet.homework_past')} ({past.length})
                    </summary>
                    <div className="mt-3 flex flex-col gap-3">
                        {past.map((h) => (
                            <HomeworkCard key={h.id} h={h} muted />
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
