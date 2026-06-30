import { cn } from '@/lib/utils';

/**
 * Skeleton-uri minimaliste pentru prop-uri defer-ate (Inertia v3).
 * Toate folosesc `animate-pulse` + `bg-muted` ca standard shadcn.
 */

export function SkeletonLine({ className }: { className?: string }) {
    return <div className={cn('h-4 w-full animate-pulse rounded bg-muted', className)} />;
}

export function SkeletonCard({ className }: { className?: string }) {
    return (
        <div className={cn('rounded-xl border bg-card p-4 shadow-sm', className)}>
            <div className="space-y-2">
                <SkeletonLine className="w-1/3" />
                <SkeletonLine />
                <SkeletonLine className="w-5/6" />
            </div>
        </div>
    );
}

export function SkeletonTable({ rows = 4 }: { rows?: number }) {
    return (
        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div className="border-b border-sidebar-border/70 bg-muted/50 px-4 py-2.5 dark:border-sidebar-border">
                <SkeletonLine className="h-3 w-1/4" />
            </div>
            <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                {Array.from({ length: rows }, (_, i) => (
                    <div key={i} className="flex items-center gap-3 px-4 py-3">
                        <SkeletonLine className="h-3 w-1/4" />
                        <SkeletonLine className="h-3 w-2/5" />
                        <SkeletonLine className="ml-auto h-3 w-12" />
                    </div>
                ))}
            </div>
        </div>
    );
}

export function SkeletonGrid({ count = 6, columns = 3 }: { count?: number; columns?: number }) {
    return (
        <div
            className={cn('grid gap-2', {
                'sm:grid-cols-2': columns === 2,
                'sm:grid-cols-2 lg:grid-cols-3': columns === 3,
                'sm:grid-cols-2 lg:grid-cols-4': columns === 4,
            })}
        >
            {Array.from({ length: count }, (_, i) => (
                <div key={i} className="h-12 animate-pulse rounded-lg bg-muted/60" />
            ))}
        </div>
    );
}
