import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Titlu standard de secțiune în cabinet: eyebrow opțional (mic, muted), titlu (h2 semibold),
 * descriere opțională și un slot dreapta pentru acțiuni / badge-uri / link-uri.
 * Înlocuiește perechile `<h2 className="mb-3 text-lg font-semibold">…</h2>` repetate.
 *
 * Caller-ul pasează textele deja traduse (agnostic de i18n).
 */
export function SectionHeading({
    eyebrow,
    title,
    description,
    actions,
    className,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('mb-3 flex flex-wrap items-start justify-between gap-3', className)}>
            <div className="min-w-0">
                {eyebrow && (
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{eyebrow}</p>
                )}
                <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
                {description && <p className="mt-0.5 text-sm text-muted-foreground">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
    );
}
