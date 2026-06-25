import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Construction, Download } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';

interface Crumb {
    title: string;
    href?: string;
}

interface PublicPageProps {
    title: string;
    description?: string;
    breadcrumbs?: Crumb[];
    hasDownloads?: boolean;
}

export default function PublicPage({ title, description, breadcrumbs = [], hasDownloads = false }: PublicPageProps) {
    return (
        <>
            <Head title={title} />

            {/* Banner pagină */}
            <section className="border-b border-border bg-muted/40">
                <div className="mx-auto max-w-7xl px-6 py-10">
                    <nav className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                        <Link href="/" className="hover:text-foreground">
                            Acasă
                        </Link>
                        {breadcrumbs.map((crumb) => (
                            <span key={crumb.title} className="flex items-center gap-1">
                                <ChevronRight className="size-3.5" />
                                {crumb.href ? (
                                    <Link href={crumb.href} className="hover:text-foreground">
                                        {crumb.title}
                                    </Link>
                                ) : (
                                    <span className="text-foreground">{crumb.title}</span>
                                )}
                            </span>
                        ))}
                    </nav>
                    <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
                    {description && <p className="mt-2 max-w-3xl text-muted-foreground">{description}</p>}
                </div>
            </section>

            {/* Conținut (schelet) */}
            <section className="mx-auto max-w-7xl px-6 py-12">
                <div className="rounded-lg border border-dashed border-border bg-card p-8">
                    <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Construction className="size-4" />
                        Pagină în construcție — conținut în curs de migrare de pe columna.org.md
                    </div>

                    <div className="mt-6 space-y-3">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-5/6" />
                        <Skeleton className="h-4 w-2/3" />
                    </div>

                    {hasDownloads && (
                        <div className="mt-8 rounded-md border border-border bg-muted/40 p-4">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <Download className="size-4" />
                                Secțiune cu descărcări
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Fișierele vor fi servite din <code className="rounded bg-background px-1 py-0.5">/downloads/…</code>.
                                Șabloanele sunt deja generate (vezi <code className="rounded bg-background px-1 py-0.5">public/downloads/</code>).
                            </p>
                        </div>
                    )}
                </div>
            </section>
        </>
    );
}
