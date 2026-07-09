import { LocaleLink } from '@/components/locale-link';
import { Container, FourStar } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';

interface Crumb {
    title: string;
    href?: string;
}

/** Page Hero V2 (pagini interioare): breadcrumb cu steluțe + titlu Cervino + lead, watermark crest.
 *  Fundal NAVY imersiv cu textura mesh (identică cu benzile navy din site) — consistență completă. */
export function PageBanner({ title, breadcrumbs = [], description }: { title: string; breadcrumbs?: Crumb[]; description?: string }) {
    const t = useTranslations();

    return (
        <section className="on-navy relative overflow-hidden bg-surface-navy text-[color:var(--brand-navy-foreground)]">
            {/* Textura mesh — aceleași puncte în ton opus ca pe <Band variant="navy" pattern="mesh"> */}
            <div className="tx-mesh-navy pointer-events-none absolute inset-0" aria-hidden="true" />
            {/* Altitudine FIXĂ pe toate paginile (h-[13rem] = 208px) pentru consistență vizuală absolută.
               Lead-urile trebuie să încapă pe UN SINGUR RÂND — vezi reformulările din i18n. */}
            <Container className="relative flex h-[13rem] flex-col justify-center">
                {/* Crest — varianta ALBĂ pe fundal navy, cu opacitate joasă (watermark discret) */}
                <img
                    src="/images/logo/columna-crest-white.png"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-0 hidden aspect-square h-[10rem] max-w-[44%] -translate-y-1/2 object-contain opacity-[0.14] select-none md:block"
                />
                <nav className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-white/70">
                    <LocaleLink href="/" className="inline-flex min-h-9 items-center hover:text-white">
                        {t('breadcrumb.home', 'Acasă')}
                    </LocaleLink>
                    {breadcrumbs.map((crumb) => (
                        <span key={crumb.title} className="flex items-center gap-2">
                            <FourStar className="size-2 text-brand-green" />
                            {crumb.href ? (
                                <LocaleLink href={crumb.href} className="inline-flex min-h-9 items-center hover:text-white">
                                    {crumb.title}
                                </LocaleLink>
                            ) : (
                                <span className="inline-flex min-h-9 items-center font-semibold text-[color:var(--brand-navy-foreground)]">{crumb.title}</span>
                            )}
                        </span>
                    ))}
                </nav>
                <h1 className="display mt-3 max-w-[20ch] text-[clamp(1.875rem,4vw,3rem)] text-[color:var(--brand-navy-foreground)]">{title}</h1>
                <span className="mt-4 block h-1 w-20 rounded-full bg-brand-green" aria-hidden="true" />
                {description && (
                    <p className="mt-4 max-w-[62ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/80">{description}</p>
                )}
            </Container>
        </section>
    );
}
