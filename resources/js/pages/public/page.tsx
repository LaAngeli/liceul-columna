import { Head } from '@inertiajs/react';
import { Construction, Download } from 'lucide-react';
import { Container } from '@/components/public/brand';
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
                <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                    <div className="rounded-[12px] border border-dashed keyline border-l-[5px] border-l-brand-green bg-card p-5 sm:p-8">
                        <div className="flex items-center gap-2 text-sm font-semibold text-brand-navy">
                            <Construction className="size-4 text-brand-green" />
                            Pagină în construcție
                        </div>

                        <div className="mt-6 space-y-3">
                            <Skeleton className="h-4 w-3/4" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-5/6" />
                            <Skeleton className="h-4 w-2/3" />
                        </div>

                        {hasDownloads && (
                            <div className="mt-8 rounded-md border keyline bg-brand-navy/[0.03] p-4">
                                <div className="flex items-center gap-2 text-sm font-semibold text-brand-navy">
                                    <Download className="size-4 text-brand-green" />
                                    Descărcări
                                </div>
                            </div>
                        )}
                    </div>
                </Container>
            )}
        </>
    );
}
