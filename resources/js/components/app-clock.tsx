import { Clock } from 'lucide-react';
import { useLiveClock } from '@/hooks/use-live-clock';
import { useLocale } from '@/lib/i18n';

/**
 * Ceas + dată live în antetul cabinetului (ascuns pe ecrane mici). Pe ORIZONTALĂ, într-un „pill"
 * discret: iconiță · zi + dată (estompate) · separator vertical · oră accentuată cu secundele
 * estompate. `tabular-nums` ca lățimea să nu tresară la fiecare tic.
 */
export function AppClock() {
    const locale = useLocale();
    const { weekday, dayMonth, hm, ss, ready } = useLiveClock(locale);

    if (!ready) {
        return null;
    }

    return (
        <div
            className="hidden items-center gap-2 rounded-full bg-muted/50 px-3 py-1.5 md:flex"
            aria-label={`${weekday}, ${dayMonth} · ${hm}:${ss}`}
        >
            <Clock className="size-3.5 text-muted-foreground/80" aria-hidden="true" />
            <span className="text-xs font-medium text-muted-foreground" aria-hidden="true">
                <span className="capitalize">{weekday}</span>, {dayMonth}
            </span>
            <span className="h-3.5 w-px bg-border" aria-hidden="true" />
            <span className="text-xs font-semibold tabular-nums text-foreground" aria-hidden="true">
                {hm}
                <span className="font-normal text-muted-foreground/60">:{ss}</span>
            </span>
        </div>
    );
}
