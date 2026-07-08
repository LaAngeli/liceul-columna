import { Head } from '@inertiajs/react';
import { ArrowRight, GraduationCap } from 'lucide-react';
import { Band, BrandButton, Reveal, Rhombus, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/** Cele 3 trepte — reutilizează cheile i18n existente `why.path.*` (verbatim RO/RU/EN deja traduse). */
const STAGES = [
    {
        numeral: 'I',
        classes: 'I–IV',
        href: '/scoala-primara',
        label: 'why.path.primary_label',
        body: 'why.path.primary_body',
        alt: 'why.path.primary_alt',
        photo: '/images/galerie/scoala-primara/3.jpg',
        focal: 'center 50%',
    },
    {
        numeral: 'II',
        classes: 'V–IX',
        href: '/scoala-gimnaziala',
        label: 'why.path.middle_label',
        body: 'why.path.middle_body',
        alt: 'why.path.middle_alt',
        photo: '/images/galerie/scoala-gimnaziala/2.jpg',
        focal: 'center 65%',
    },
    {
        numeral: 'III',
        classes: 'X–XII',
        href: '/scoala-liceala',
        label: 'why.path.high_label',
        body: 'why.path.high_body',
        alt: 'why.path.high_alt',
        photo: '/images/galerie/scoala-liceala/6.jpg',
        focal: 'center 50%',
    },
] as const;

export default function StructuraScolii() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('structure_page.meta_title', 'Structura școlii — Liceul Columna')}>
                <meta name="description" content={t('structure_page.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('structure_page.title', 'Structura școlii')}
                breadcrumbs={[{ title: t('structure_page.breadcrumb_self', 'Structura școlii') }]}
                description={t('structure_page.lead')}
            />

            {/* Secțiune ALBASTRĂ — cele 3 trepte ca trio editorial (foto + numeral roman + clase + body + CTA) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    align="center"
                    label={t('structure_page.levels_eyebrow', 'Trei trepte')}
                    title={t('structure_page.levels_title', 'Sub un singur acoperiș, de la prima literă la diplomă')}
                    lead={t('structure_page.levels_lead')}
                    className="mb-12"
                />
                <div className="grid gap-6 lg:grid-cols-3 lg:gap-7">
                    {STAGES.map((s, i) => (
                        <Reveal as="article" key={s.numeral} className="flex flex-col overflow-hidden rounded-[18px] border border-white/15 bg-white/[0.05] shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                            {/* Foto 16:9 cu numeral roman suprapus în colț */}
                            <figure className="relative aspect-[16/10] overflow-hidden">
                                <img
                                    src={s.photo}
                                    alt={t(s.alt)}
                                    loading="lazy"
                                    decoding="async"
                                    width={900}
                                    height={505}
                                    className="absolute inset-0 h-full w-full object-cover"
                                    style={{ objectPosition: s.focal }}
                                />
                                <div
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-0"
                                    style={{ background: 'linear-gradient(135deg, color-mix(in oklch, var(--surface-navy) 70%, transparent) 0%, transparent 45%)' }}
                                />
                                <span
                                    className="numeral absolute top-3 left-4 text-[clamp(2.25rem,4vw,3rem)] leading-none text-white sm:top-5 sm:left-6"
                                    style={{ textShadow: '0 2px 14px color-mix(in oklch, var(--surface-navy) 75%, transparent)' }}
                                >
                                    {s.numeral}
                                </span>
                            </figure>
                            {/* Conținut */}
                            <div className="flex flex-1 flex-col p-6 sm:p-7">
                                <div className="flex items-center gap-2 text-brand-green" aria-hidden="true">
                                    {Array.from({ length: i + 1 }).map((_, idx) => (
                                        <Rhombus key={idx} className="size-3" />
                                    ))}
                                </div>
                                <h3 className="display mt-4 text-[clamp(1.25rem,2vw,1.625rem)] text-balance text-[color:var(--brand-navy-foreground)]">{t(s.label)}</h3>
                                <p className="mt-3 flex-1 text-[0.95rem] leading-relaxed text-white/85">{t(s.body)}</p>
                                <span className="mt-5 inline-block">
                                    <BrandButton href={s.href} variant="link-navy" icon={ArrowRight}>
                                        {t('structure_page.discover', 'Despre treaptă')}
                                    </BrandButton>
                                </span>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA „pasul următor" */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('structure_page.cta_eyebrow', 'Pasul următor')}
                        title={t('structure_page.cta_title', 'Continuă explorarea')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('structure_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/admitere" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                            {t('structure_page.cta_primary', 'Vino la admitere')}
                        </BrandButton>
                        <BrandButton href="/de-ce-columna" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('structure_page.cta_secondary', 'De ce Columna')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
