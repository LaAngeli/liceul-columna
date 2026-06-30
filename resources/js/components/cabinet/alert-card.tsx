import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type AlertVariant = 'info' | 'warning' | 'danger' | 'success';

const VARIANT_CLASSES: Record<AlertVariant, { card: string; icon: string; value: string }> = {
    info: {
        card: 'border-primary/30 bg-primary/5 hover:bg-primary/10',
        icon: 'text-primary',
        value: 'text-primary',
    },
    warning: {
        card: 'border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/15',
        icon: 'text-amber-700 dark:text-amber-300',
        value: 'text-amber-700 dark:text-amber-300',
    },
    danger: {
        card: 'border-destructive/40 bg-destructive/10 hover:bg-destructive/15',
        icon: 'text-destructive',
        value: 'text-destructive',
    },
    success: {
        card: 'border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/15',
        icon: 'text-emerald-700 dark:text-emerald-300',
        value: 'text-emerald-700 dark:text-emerald-300',
    },
};

/**
 * Card de alertă pentru banda cockpit (mesaje necitite, motivări pending, riscuri).
 * Dacă primește `href`, întreg cardul e un Link Inertia (a11y card-link pattern).
 * Altfel rămâne static (utilizat pentru „nicio alertă activă").
 *
 * Caller-ul pasează etichete deja traduse.
 */
export function AlertCard({
    icon: Icon,
    label,
    value,
    variant = 'info',
    href,
    className,
}: {
    icon: LucideIcon;
    label: string;
    value: string | number;
    variant?: AlertVariant;
    href?: string;
    className?: string;
}) {
    const variantClass = VARIANT_CLASSES[variant];
    const inner = (
        <>
            <Icon className={cn('size-5 shrink-0', variantClass.icon)} aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <p className={cn('text-2xl font-semibold leading-none', variantClass.value)}>{value}</p>
                <p className="mt-1 truncate text-xs font-medium text-muted-foreground">{label}</p>
            </div>
        </>
    );

    const cardClass = cn(
        'flex items-start gap-3 rounded-xl border p-4 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        variantClass.card,
        className,
    );

    if (href) {
        return (
            <Link href={href} className={cardClass} aria-label={`${value} ${label}`}>
                {inner}
            </Link>
        );
    }

    return <div className={cardClass}>{inner}</div>;
}
