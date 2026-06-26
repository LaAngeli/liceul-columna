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
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-8 bg-gradient-to-b from-muted/40 to-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={home()} aria-label="Liceul Columna">
                            <img
                                src="/images/logo/columna-navy.png"
                                alt="Liceul Columna"
                                className="h-24 w-24 dark:hidden"
                            />
                            <img
                                src="/images/logo/columna-white.png"
                                alt="Liceul Columna"
                                className="hidden h-24 w-24 dark:block"
                            />
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
