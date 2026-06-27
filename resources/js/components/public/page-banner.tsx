import { ChevronRight } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { useTranslations } from '@/lib/i18n';

interface Crumb {
    title: string;
    href?: string;
}

/** Banner de pagină publică: breadcrumb + titlu serif + descriere opțională. */
export function PageBanner({ title, breadcrumbs = [], description }: { title: string; breadcrumbs?: Crumb[]; description?: string }) {
    const t = useTranslations();

    return (
        <section className="border-b border-border bg-muted/40">
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
                <nav className="flex flex-wrap items-center gap-x-1 gap-y-1.5 text-sm text-muted-foreground">
                    <LocaleLink href="/" className="hover:text-foreground">
                        {t('breadcrumb.home', 'Acasă')}
                    </LocaleLink>
                    {breadcrumbs.map((crumb) => (
                        <span key={crumb.title} className="flex items-center gap-1">
                            <ChevronRight className="size-3.5" />
                            {crumb.href ? (
                                <LocaleLink href={crumb.href} className="hover:text-foreground">
                                    {crumb.title}
                                </LocaleLink>
                            ) : (
                                <span className="text-foreground">{crumb.title}</span>
                            )}
                        </span>
                    ))}
                </nav>
                <h1 className="mt-3 font-serif text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
                {description && <p className="mt-2 max-w-3xl text-muted-foreground">{description}</p>}
            </div>
        </section>
    );
}
