import { LocaleLink } from '@/components/locale-link';
import { Container, FourStar } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';

interface Crumb {
    title: string;
    href?: string;
}

/** Page Hero V2 (pagini interioare): breadcrumb cu steluțe + titlu Cervino + lead, watermark crest. */
export function PageBanner({ title, breadcrumbs = [], description }: { title: string; breadcrumbs?: Crumb[]; description?: string }) {
    const t = useTranslations();

    return (
        <section className="relative overflow-hidden border-b keyline bg-card">
            {/* Altitudine FIXĂ pe toate paginile (h-[13rem] = 208px) pentru consistență vizuală absolută.
               Lead-urile trebuie să încapă pe UN SINGUR RÂND — vezi reformulările din i18n. */}
            <Container className="relative flex h-[13rem] flex-col justify-center">
                {/* Crest — dimensiune FIXĂ pe toate paginile (h-[10rem] = 160px) */}
                <img
                    src="/images/logo/columna-crest-color.png"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-0 hidden aspect-square h-[10rem] max-w-[44%] -translate-y-1/2 object-contain select-none md:block dark:hidden"
                />
                <img
                    src="/images/logo/columna-crest-white.png"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-0 hidden aspect-square h-[10rem] max-w-[44%] -translate-y-1/2 object-contain opacity-[0.03] select-none md:dark:block"
                />
                <nav className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-brand-gray">
                    <LocaleLink href="/" className="inline-flex min-h-9 items-center hover:text-brand-navy">
                        {t('breadcrumb.home', 'Acasă')}
                    </LocaleLink>
                    {breadcrumbs.map((crumb) => (
                        <span key={crumb.title} className="flex items-center gap-2">
                            <FourStar className="size-2 text-brand-green/60" />
                            {crumb.href ? (
                                <LocaleLink href={crumb.href} className="inline-flex min-h-9 items-center hover:text-brand-navy">
                                    {crumb.title}
                                </LocaleLink>
                            ) : (
                                <span className="inline-flex min-h-9 items-center font-semibold text-brand-navy">{crumb.title}</span>
                            )}
                        </span>
                    ))}
                </nav>
                <h1 className="display mt-3 max-w-[20ch] text-[clamp(1.875rem,4vw,3rem)] text-brand-navy">{title}</h1>
                <span className="mt-4 block h-1 w-20 rounded-full bg-brand-green" aria-hidden="true" />
                {description && (
                    <p className="mt-4 max-w-[62ch] text-[clamp(1.0625rem,1.6vw,1.1875rem)] leading-relaxed text-brand-gray">{description}</p>
                )}
            </Container>
        </section>
    );
}
