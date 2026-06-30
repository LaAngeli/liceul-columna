import { Head } from '@inertiajs/react';
import { ArrowRight, GraduationCap, Quote } from 'lucide-react';
import { Band, BrandButton, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

const PRINCIPLES = ['philosophy.p1', 'philosophy.p2', 'philosophy.p3', 'philosophy.p4', 'philosophy.p5', 'philosophy.p6'] as const;

export default function FilosofiaLiceului() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('philosophy.meta_title', 'Filosofia liceului — Liceul Columna')}>
                <meta name="description" content={t('philosophy.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('philosophy.title', 'Filosofia liceului')}
                breadcrumbs={[
                    { title: t('philosophy.breadcrumb_section', 'Despre liceu') },
                    { title: t('philosophy.breadcrumb_self', 'Filosofia liceului') },
                ]}
                description={t('philosophy.lead')}
            />

            {/* Secțiune ALBASTRĂ — principiile ca manifest numerotat (crez pe navy) + portret real sticky */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('philosophy.principles_eyebrow', 'Crezul nostru')}
                    title={t('philosophy.principles_title', 'Principiile care ne ghidează')}
                    className="mb-8"
                />
                <div className="grid gap-8 lg:grid-cols-[1.05fr_0.95fr] lg:gap-14">
                    {/* Manifestul numerotat */}
                    <div>
                        <Reveal>
                            <p className="display text-[clamp(1.25rem,2.4vw,1.75rem)] text-brand-green">{t('philosophy.intro', 'Liceul „Columna":')}</p>
                        </Reveal>
                        <ol className="mt-6 border-y border-white/10">
                            {PRINCIPLES.map((key, i) => (
                                <Reveal as="li" key={key} className="flex items-start gap-5 border-t border-white/10 py-6 first:border-t-0 sm:gap-7 sm:py-7">
                                    <span className="numeral shrink-0 text-[clamp(2rem,4vw,3rem)] leading-none text-brand-green/80" aria-hidden="true">
                                        {String(i + 1).padStart(2, '0')}
                                    </span>
                                    <p className="text-[clamp(1.0625rem,1.55vw,1.25rem)] leading-relaxed text-white/90">{t(key)}</p>
                                </Reveal>
                            ))}
                        </ol>
                    </div>

                    {/* Portret real — fiecare elev, în centrul actului educațional */}
                    <Reveal as="div" className="lg:sticky lg:top-24 lg:self-start">
                        <figure className="relative aspect-[4/5] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                            <img
                                src="/images/galerie/general/g19.jpg"
                                alt={t('philosophy.principles_photo_alt', 'Elevă a Liceului Columna concentrată în timpul lecției')}
                                loading="lazy"
                                decoding="async"
                                width={800}
                                height={1000}
                                className="absolute inset-0 h-full w-full object-cover"
                                style={{ objectPosition: 'center 35%' }}
                            />
                        </figure>
                    </Reveal>
                </div>
            </Band>

            {/* Secțiune ALBĂ — convingerea: foto reală + statement-citat + CTA */}
            <Band variant="light">
                <div className="grid items-center gap-8 lg:grid-cols-2 lg:gap-14">
                    <Reveal>
                        <figure className="relative aspect-[4/3] overflow-hidden rounded-[18px] border keyline shadow-[0_30px_70px_-45px_rgba(15,77,119,0.5)]">
                            <img
                                src="/images/galerie/general/g1.jpg"
                                alt={t('philosophy.photo_alt', 'Elevi ai Liceului Columna bucurându-se pe gazonul campusului din Chișinău')}
                                loading="lazy"
                                decoding="async"
                                width={900}
                                height={600}
                                className="absolute inset-0 h-full w-full object-cover"
                                style={{ objectPosition: 'center 45%' }}
                            />
                        </figure>
                    </Reveal>
                    <Reveal>
                        <span className="eyebrow text-brand-navy">{t('philosophy.conviction_eyebrow', 'Convingerea noastră')}</span>
                        <Quote className="mt-4 size-9 text-brand-green" aria-hidden="true" />
                        <p className="display mt-3 text-[clamp(1.5rem,3vw,2.25rem)] leading-snug text-balance text-brand-navy">
                            {t('philosophy.conviction', 'Suntem convinși că educația schimbă lumea spre bine și contribuim cu pasiune și perseverență la acest nobil proces.')}
                        </p>
                        <span data-rule className="mt-6 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <BrandButton href="/de-ce-columna" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                                {t('philosophy.cta_primary', 'De ce Columna')}
                            </BrandButton>
                            <BrandButton href="/scrisoarea-directorului" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                                {t('philosophy.cta_secondary', 'Scrisoarea directorului')}
                            </BrandButton>
                        </div>
                    </Reveal>
                </div>
            </Band>
        </>
    );
}
