import { Head } from '@inertiajs/react';
import { Construction, Download } from 'lucide-react';
import { PageBanner } from '@/components/public/page-banner';
import { PageSections, type PageSection } from '@/components/public/page-sections';
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
    sections?: PageSection[];
}

export default function PublicPage({ title, description, breadcrumbs = [], hasDownloads = false, sections = [] }: PublicPageProps) {
    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={breadcrumbs} description={description} />

            {/* Conținut migrat din columna.org.md (secțiuni în brand nou) */}
            {sections.length > 0 ? (
                <PageSections sections={sections} />
            ) : (
                <section className="mx-auto max-w-7xl px-6 py-12">
                    <div className="rounded-lg border border-dashed border-border bg-card p-8">
                        <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Construction className="size-4" />
                            Pagină în construcție
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
                                    Descărcări
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            )}
        </>
    );
}
