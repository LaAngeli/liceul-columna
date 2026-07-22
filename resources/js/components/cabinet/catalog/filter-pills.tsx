import { useState } from 'react';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Filtrul pe categorii al modulelor de catalog (discipline în „Teme" și „Absențe").
 *
 * Pastilele se ÎNFĂȘOARĂ pe mai multe rânduri — fără scroll orizontal (o bară de scroll ascunde
 * exact ce trebuie scanat dintr-o privire, iar pe touch fură gestul de scroll al paginii). Ca să
 * nu crească necontrolat pe verticală la clasele de liceu (15+ discipline), peste `VISIBLE` opțiuni
 * restul stă pliat după un buton „+N"; opțiunea ACTIVĂ rămâne mereu vizibilă, chiar dacă ar cădea
 * dincolo de prag.
 */

export interface FilterPillOption {
    key: string | number;
    label: string;
    count: number;
    /** Punct de semnal (ex. disciplina are temă de predat azi). */
    dot?: boolean;
    /** Contorul cere atenție (ex. absențe nemotivate) — evidențiat în roșu când pastila e inactivă. */
    danger?: boolean;
}

/** Câte pastile (în afară de „Toate") rămân vizibile înainte de pliere. */
const VISIBLE = 8;

export function FilterPills({
    options,
    active,
    onChange,
    allCount,
    ariaLabel,
}: {
    options: FilterPillOption[];
    /** Cheia activă sau `'all'`. */
    active: string | number | 'all';
    onChange: (key: string | number | 'all') => void;
    allCount: number;
    ariaLabel: string;
}) {
    const t = useTranslations();
    const [expanded, setExpanded] = useState(false);

    if (options.length <= 1) {
        return null;
    }

    let shown = expanded ? options : options.slice(0, VISIBLE);

    // Activa nu are voie să dispară la pliere — altfel filtrul pare resetat, deși nu e.
    if (!expanded && !shown.some((option) => option.key === active)) {
        const current = options.find((option) => option.key === active);

        if (current) {
            shown = [...shown, current];
        }
    }

    const hidden = options.length - shown.length;

    const pillClass = (isActive: boolean) =>
        cn(
            'inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-colors',
            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            isActive
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
        );

    const countClass = (isActive: boolean, danger?: boolean) =>
        cn(
            'inline-flex min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold',
            isActive ? 'bg-primary-foreground/20' : danger ? 'bg-destructive/10 text-destructive' : 'bg-muted',
        );

    return (
        <div className="flex flex-wrap gap-1.5" role="group" aria-label={ariaLabel}>
            <button type="button" onClick={() => onChange('all')} aria-pressed={active === 'all'} className={pillClass(active === 'all')}>
                {t('cabinet.reg_all')}
                <span className={countClass(active === 'all')}>{allCount}</span>
            </button>

            {shown.map((option) => {
                const isActive = option.key === active;

                return (
                    <button
                        key={option.key}
                        type="button"
                        onClick={() => onChange(option.key)}
                        aria-pressed={isActive}
                        className={pillClass(isActive)}
                    >
                        <span className="max-w-52 truncate">{option.label}</span>
                        {option.dot && (
                            <span className={cn('size-1.5 rounded-full', isActive ? 'bg-primary-foreground' : 'bg-primary')} aria-hidden />
                        )}
                        <span className={countClass(isActive, option.danger)}>{option.count}</span>
                    </button>
                );
            })}

            {(hidden > 0 || expanded) && (
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    aria-expanded={expanded}
                    className="inline-flex h-9 cursor-pointer items-center rounded-full border border-dashed border-border px-3.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {expanded ? t('cabinet.filter_less') : `+${hidden}`}
                    {!expanded && <span className="sr-only"> {t('cabinet.filter_more')}</span>}
                </button>
            )}
        </div>
    );
}
