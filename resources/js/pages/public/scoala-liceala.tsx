import { Head } from '@inertiajs/react';
import { BookOpen, Globe, GraduationCap, Users } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, Rhombus, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/* Disciplinele treptei liceale, grupate pe arii curriculare (lista verbatim de pe site-ul vechi). */
const CURRICULAR_AREAS = [
    { area: 'high_page.area_lang', subjects: ['high_page.sub_romanian', 'high_page.sub_foreign', 'high_page.sub_universal_lit'] },
    { area: 'high_page.area_math_sci', subjects: ['high_page.sub_math', 'high_page.sub_informatics', 'high_page.sub_biology', 'high_page.sub_chemistry', 'high_page.sub_physics'] },
    { area: 'high_page.area_social', subjects: ['high_page.sub_history'] },
    { area: 'high_page.area_sport', subjects: ['high_page.sub_pe'] },
] as const;

/* Galerie: 4 imagini DISTINCTE de identitate/dotări — atmosferă reală de lecție + grup social.
   Folosim foto din general/ ca să nu repetăm imaginile deja vizibile mai sus pe pagină. */
const GALLERY = [
    { src: '/images/galerie/general/g10.jpg', alt: 'high_page.gallery_alt_1' },
    { src: '/images/galerie/general/g11.jpg', alt: 'high_page.gallery_alt_2' },
    { src: '/images/galerie/general/g17.jpg', alt: 'high_page.gallery_alt_3' },
    { src: '/images/galerie/general/g7.jpg', alt: 'high_page.gallery_alt_4' },
] as const;

export default function ScoalaLiceala() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('high_page.meta_title', 'Școala liceală · X–XII — Liceul Columna')}>
                <meta name="description" content={t('high_page.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner) */}
            <PageBanner
                title={t('high_page.title', 'Școala liceală')}
                breadcrumbs={[
                    { title: t('high_page.breadcrumb_section', 'Structura școlii'), href: '/structura-scolii' },
                    { title: t('high_page.breadcrumb_self', 'Școala liceală') },
                ]}
                description={t('high_page.lead')}
            />

            {/* Secțiune ALBASTRĂ — identitate (foto LED video wall + body) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="III"
                    variant="navy"
                    label={t('high_page.identity_eyebrow', 'Treapta liceală')}
                    title={t('high_page.identity_title', 'Maturizare academică, deschisă lumii')}
                    className="mb-10"
                />
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14">
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-liceala/5.jpg"
                            alt={t('high_page.identity_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 50%' }}
                        />
                    </figure>
                    <div>
                        <div className="flex items-center gap-2 text-brand-green" aria-hidden="true">
                            <Rhombus className="size-4" />
                            <Rhombus className="size-3" />
                            <Rhombus className="size-2" />
                            <span className="numeral ml-2 text-sm text-white/80">X–XII</span>
                        </div>
                        <p className="mt-5 text-[clamp(1.125rem,1.7vw,1.25rem)] leading-relaxed text-white/90">{t('high_page.identity_body')}</p>
                        <span data-rule className="mt-7 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                    </div>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — Curriculum: arii curriculare + link spre sub-pagina cu PDF-uri pe disciplină */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="✦"
                    label={t('high_page.curriculum_eyebrow', 'Ce studiem')}
                    title={t('high_page.curriculum_title', 'Disciplinele treptei liceale')}
                    lead={t('high_page.curriculum_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {CURRICULAR_AREAS.map((a) => (
                        <Reveal as="article" key={a.area} className="rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-card p-5 sm:p-6">
                            <h3 className="display text-[1.125rem] leading-snug text-brand-navy">{t(a.area)}</h3>
                            <ul className="mt-3 space-y-1.5 text-sm leading-relaxed text-brand-dark/85">
                                {a.subjects.map((s) => (
                                    <li key={s} className="flex gap-2">
                                        <span className="mt-2 size-1.5 shrink-0 rounded-full bg-brand-green" aria-hidden="true" />
                                        <span>{t(s)}</span>
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    ))}
                </div>
                <Reveal className="mt-10 text-center">
                    <BrandButton href="/scoala-liceala/curriculum" variant="link" icon={BookOpen}>
                        {t('high_page.see_curriculum', 'Vezi curriculumul pe disciplină')}
                    </BrandButton>
                </Reveal>
            </Band>

            {/* Secțiune ALBASTRĂ — dotări (foto lab chimie + text verbatim) */}
            <Band variant="navy" pattern="mesh">
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1fr_1.1fr] lg:gap-14">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('high_page.facilities_eyebrow', 'Sălile și dotările')}</span>
                            <span data-rule className="h-px w-12 origin-left bg-white/30" aria-hidden="true" />
                        </div>
                        <h2 className="display mt-4 text-[clamp(1.5rem,2.8vw,2.125rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                            {t('high_page.facilities_title', 'Săli specializate pentru discipline reale')}
                        </h2>
                        <span data-rule className="mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-5 text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/90">{t('high_page.facilities_body')}</p>
                    </div>
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-liceala/6.jpg"
                            alt={t('high_page.facilities_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 50%' }}
                        />
                    </figure>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — Cambridge English (atu liceul) */}
            <Band variant="light" pattern="mesh">
                <Reveal className="mx-auto max-w-3xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <span className="eyebrow mt-3 block text-brand-navy">{t('high_page.cambridge_eyebrow', 'Standard internațional')}</span>
                    <h2 className="display mt-3 text-[clamp(1.5rem,3vw,2.25rem)] text-balance text-brand-navy">
                        {t('high_page.cambridge_title', 'Centru autorizat Cambridge English · din 2019')}
                    </h2>
                    <span data-rule className="mx-auto mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                    <p className="mx-auto mt-6 max-w-[60ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-brand-dark/85">{t('high_page.cambridge_lead')}</p>
                    <div className="mt-7 flex justify-center">
                        <BrandButton href="/cambridge-english-exam" variant="primary" icon={Globe}>
                            {t('high_page.cambridge_cta', 'Despre Cambridge English')}
                        </BrandButton>
                    </div>
                </Reveal>
            </Band>

            {/* Secțiune ALBASTRĂ — galerie (4 imagini noi din viața elevilor, distincte de identitate/dotări) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('high_page.gallery_eyebrow', 'Galerie')}
                    title={t('high_page.gallery_title', 'Din viața treptei liceale')}
                    className="mb-8"
                />
                <Reveal className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                    {GALLERY.map((g) => (
                        <figure key={g.src} className="relative aspect-[4/3] overflow-hidden rounded-[12px] border border-white/15">
                            <img
                                src={g.src}
                                alt={t(g.alt)}
                                loading="lazy"
                                decoding="async"
                                width={900}
                                height={675}
                                className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 hover:scale-[1.04]"
                            />
                        </figure>
                    ))}
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — CTA „pasul următor" */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('high_page.cta_eyebrow', 'Pasul următor')}
                        title={t('high_page.cta_title', 'Continuă explorarea')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('high_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/admitere" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                            {t('high_page.cta_primary', 'Vino la admitere')}
                        </BrandButton>
                        <BrandButton href="/personal" variant="ghost" icon={Users} className="w-full justify-center sm:w-auto">
                            {t('high_page.cta_secondary', 'Cunoaște echipa')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
