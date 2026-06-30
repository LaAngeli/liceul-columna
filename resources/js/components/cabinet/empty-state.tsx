import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Card de gol unificat pentru cabinet (liste goale, ecrane fără date).
 * Înlocuiește variantele divergente `<p className="rounded-xl border border-dashed …">`
 * scrise în 4 pagini diferite, cu padding-uri inconsistente.
 *
 * Caller-ul pasează titlul + descrierea deja traduse (primitivul rămâne agnostic de chei i18n).
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    children,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-muted-foreground dark:border-sidebar-border',
                className,
            )}
        >
            {Icon && <Icon className="size-8 opacity-60" aria-hidden="true" />}
            <p className="text-sm font-medium text-foreground">{title}</p>
            {description && <p className="max-w-md text-sm">{description}</p>}
            {children && <div className="mt-2">{children}</div>}
        </div>
    );
}
