import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StatTrend = 'up' | 'down' | 'stable' | null;

/**
 * Card de statistică pentru snapshot (medie, absențe, note totale) + opțional o săgeată de tendință.
 * Folosit în cockpit (carduri-copil) și în profilul student (tab Prezentare → snapshot).
 *
 * Caller-ul pasează valorile finale + label-ul deja tradus.
 */
export function StatCard({
    icon: Icon,
    label,
    value,
    sublabel,
    trend,
    trendLabel,
    className,
}: {
    icon?: LucideIcon;
    label: string;
    value: string | number;
    sublabel?: string;
    trend?: StatTrend;
    trendLabel?: string;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-1 rounded-lg bg-muted/50 p-3', className)}>
            <div className="flex items-baseline gap-1.5">
                {Icon && <Icon className="size-4 self-center text-muted-foreground" aria-hidden="true" />}
                <span className="text-lg font-semibold leading-none">{value}</span>
                {trend && (
                    <span
                        className={cn(
                            'text-xs font-semibold',
                            trend === 'up' && 'text-emerald-700 dark:text-emerald-300',
                            trend === 'down' && 'text-destructive',
                            trend === 'stable' && 'text-muted-foreground',
                        )}
                        title={trendLabel}
                        aria-label={trendLabel}
                    >
                        {trend === 'up' ? '▲' : trend === 'down' ? '▼' : '▬'}
                    </span>
                )}
            </div>
            <p className="text-xs text-muted-foreground">{label}</p>
            {sublabel && <p className="text-xs text-muted-foreground/80">{sublabel}</p>}
        </div>
    );
}
