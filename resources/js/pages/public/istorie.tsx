import { Head } from '@inertiajs/react';
import { ArrowRight, GraduationCap } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const MILESTONES = [
    { key: 'm1', kind: 'year' as const },
    { key: 'm2', kind: 'phase' as const },
    { key: 'm3', kind: 'phase' as const },
    { key: 'm4', kind: 'year' as const },
    { key: 'm5', kind: 'now' as const },
];

export default function Istorie() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('history.meta_title', 'Istoria liceului — Liceul Columna')}>
                <meta name="description" content={t('history.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal alb + crest watermark */}
            <PageBanner
                title={t('history.title', 'Istoria liceului')}
                breadcrumbs={[
                    { title: t('history.breadcrumb_section', 'Despre liceu') },
                    { title: t('history.breadcrumb_self', 'Istoria liceului') },
                ]}
                description={t('history.banner_lead', 'Povestea Liceului „Columna", din 1998 până azi.')}
            />

            {/* Secțiune ALBASTRĂ — cronologia „Din 1998 până azi" */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    align="center"
                    label={t('history.intro_eyebrow', 'Din 1998 până azi')}
                    lead={t('history.intro_lead')}
                    className="mx-auto max-w-3xl"
                />

                <ol className="relative mx-auto mt-14 max-w-3xl space-y-12">
                    {MILESTONES.map((m, i) => (
                        <Reveal as="li" key={m.key} className="relative pl-12 sm:pl-16">
                            {/* conector vertical către nodul următor */}
                            {i < MILESTONES.length - 1 && (
                                <span
                                    className="pointer-events-none absolute top-9 bottom-[-3rem] left-[1.125rem] w-px bg-white/15 sm:top-11 sm:left-[1.375rem]"
                                    aria-hidden="true"
                                />
                            )}
                            {/* nodul de pe linie */}
                            <span
                                className="absolute top-0 left-0 flex size-9 items-center justify-center rounded-full bg-brand-green ring-4 ring-brand-navy sm:size-11"
                                aria-hidden="true"
                            >
                                <FourStar className="size-4 text-[color:var(--brand-dark)]" />
                            </span>
                            {/* eticheta: an (numeral mare verde) sau fază (eyebrow verde) */}
                            <span
                                className={cn(
                                    'block',
                                    m.kind === 'year'
                                        ? 'numeral text-[clamp(1.875rem,4.5vw,2.75rem)] leading-none text-brand-green'
                                        : 'eyebrow text-brand-green',
                                )}
                            >
                                {t(`history.${m.key}_label`)}
                            </span>
                            <h3 className="display mt-2 text-[clamp(1.25rem,2.4vw,1.75rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                                {t(`history.${m.key}_title`)}
                            </h3>
                            <p className="mt-3 max-w-[60ch] leading-relaxed text-white/85">{t(`history.${m.key}_body`)}</p>
                        </Reveal>
                    ))}
                </ol>
            </Band>

            {/* Secțiune ALBĂ — campusul azi + invitație */}
            <Band variant="light">
                <div className="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">
                    <Reveal>
                        <figure className="relative aspect-[3/2] overflow-hidden rounded-[16px] border keyline shadow-[0_24px_60px_-44px_rgba(15,77,119,0.55)]">
                            <img
                                src="/images/galerie/general/g15.jpg"
                                alt={t('history.cta_photo_alt', 'Vedere aeriană a campusului Liceului „Columna" din Chișinău, astăzi')}
                                loading="lazy"
                                decoding="async"
                                width={900}
                                height={600}
                                className="absolute inset-0 h-full w-full object-cover"
                                style={{ objectPosition: 'center 35%' }}
                            />
                        </figure>
                    </Reveal>
                    <Reveal>
                        <SectionHeader
                            index="✦"
                            label={t('history.cta_eyebrow', 'Pasul următor')}
                            title={t('history.cta_title', 'Vrei să faci parte din povestea noastră?')}
                        />
                        <p className="mt-4 leading-relaxed text-brand-gray">{t('history.cta_lead')}</p>
                        <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            <BrandButton href="/inregistrarea-student" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                                {t('history.cta_primary', 'Înscrie copilul')}
                            </BrandButton>
                            <BrandButton href="/contacte" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                                {t('history.cta_secondary', 'Contacte')}
                            </BrandButton>
                        </div>
                    </Reveal>
                </div>
            </Band>
        </>
    );
}
