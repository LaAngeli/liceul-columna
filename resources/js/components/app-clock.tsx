import { Clock } from 'lucide-react';
import { useLiveClock } from '@/hooks/use-live-clock';
import { useLocale } from '@/lib/i18n';

/** Ceas + dată live în antetul cabinetului (ascuns pe ecrane mici). */
export function AppClock() {
    const locale = useLocale();
    const { date, time } = useLiveClock(locale);

    if (time === '') {
        return null;
    }

    return (
        <div className="hidden items-center gap-1.5 text-xs font-medium text-muted-foreground tabular-nums md:flex">
            <Clock className="size-3.5" />
            <span className="capitalize">{date}</span>
            <span aria-hidden="true">·</span>
            <span>{time}</span>
        </div>
    );
}
