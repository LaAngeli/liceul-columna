import { Link } from '@inertiajs/react';
import { useTranslations } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const t = useTranslations();

    return (
        <div className="auth-shell relative flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={home()} aria-label="Liceul Columna" className="block">
                            {/* Emblema Columna: surround static + scutul care se rotește 3D (doar el).
                                Straturile/temele vin din CSS (`.auth-emblem`, `app.css`). */}
                            <span
                                className="auth-emblem block h-28 w-28 sm:h-32 sm:w-32"
                                aria-hidden="true"
                            >
                                <span className="auth-emblem__coin" />
                            </span>
                        </Link>

                        <div className="space-y-1.5 text-center">
                            <h1 className="font-serif text-2xl font-semibold tracking-tight">
                                {title ? t(title) : title}
                            </h1>
                            {description && (
                                <p className="text-sm text-muted-foreground">{t(description)}</p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                        {children}
                    </div>
                </div>
            </div>

            <p className="text-center text-xs text-muted-foreground">Liceul Columna · Chișinău</p>
        </div>
    );
}
