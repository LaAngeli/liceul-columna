import { Head } from '@inertiajs/react';
import { ArrowRight, CalendarClock, Download, FileText } from 'lucide-react';
import { Band, BrandButton, Reveal, Rhombus, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/* Ariile curriculare ale învățământului primar (cf. Curriculumul Național, MECC 2018). */
const CURRICULAR_AREAS = [
    { area: 'primary_page.area_lang', subjects: ['primary_page.sub_language', 'primary_page.sub_foreign'] },
    { area: 'primary_page.area_math', subjects: ['primary_page.sub_math', 'primary_page.sub_sci'] },
    { area: 'primary_page.area_social', subjects: ['primary_page.sub_history', 'primary_page.sub_moral'] },
    { area: 'primary_page.area_arts', subjects: ['primary_page.sub_music', 'primary_page.sub_visual'] },
    { area: 'primary_page.area_sport', subjects: ['primary_page.sub_pe'] },
    { area: 'primary_page.area_tech', subjects: ['primary_page.sub_tech', 'primary_page.sub_digital', 'primary_page.sub_robotics'] },
    { area: 'primary_page.area_personal', subjects: ['primary_page.sub_personal'] },
] as const;

/* Galerie: cele 4 fotografii reale (NU repetăm imaginile folosite în alte secțiuni decât intenționat). */
const GALLERY = [
    { src: '/images/galerie/scoala-primara/1.jpg', alt: 'primary_page.gallery_alt_1' },
    { src: '/images/galerie/scoala-primara/2.jpg', alt: 'primary_page.gallery_alt_2' },
    { src: '/images/galerie/scoala-primara/3.jpg', alt: 'primary_page.gallery_alt_3' },
    { src: '/images/galerie/scoala-primara/4.jpg', alt: 'primary_page.gallery_alt_4' },
] as const;

const CURRICULUM_PDF = '/downloads/curriculum/curriculum-invatamantul-primar.pdf';

export default function ScoalaPrimara() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('primary_page.meta_title', 'Școala primară · I–IV — Liceul Columna')}>
                <meta name="description" content={t('primary_page.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('primary_page.title', 'Școala primară')}
                breadcrumbs={[
                    { title: t('primary_page.breadcrumb_section', 'Structura școlii'), href: '/structura-scolii' },
                    { title: t('primary_page.breadcrumb_self', 'Școala primară') },
                ]}
                description={t('primary_page.lead')}
            />

            {/* Secțiune ALBASTRĂ — identitate (foto „lecție SMART" + body verbatim) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="I"
                    variant="navy"
                    label={t('primary_page.identity_eyebrow', 'Treapta primară')}
                    title={t('primary_page.identity_title', 'Primii pași: solid și cu blândețe')}
                    className="mb-10"
                />
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14">
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-primara/4.jpg"
                            alt={t('primary_page.identity_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 35%' }}
                        />
                    </figure>
                    <div>
                        <div className="flex items-center gap-2 text-brand-green" aria-hidden="true">
                            <Rhombus className="size-4" />
                            <span className="numeral text-sm text-white/80">I–IV</span>
                        </div>
                        <p className="mt-5 text-[clamp(1.125rem,1.7vw,1.25rem)] leading-relaxed text-white/90">{t('primary_page.identity_body')}</p>
                        <span data-rule className="mt-7 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                    </div>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — Curriculum: arii curriculare + descărcare PDF oficial */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="✦"
                    label={t('primary_page.curriculum_eyebrow', 'Ce studiem')}
                    title={t('primary_page.curriculum_title', 'Disciplinele treptei primare')}
                    lead={t('primary_page.curriculum_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
                {/* Card de descărcare a curriculumului oficial */}
                <Reveal className="mt-10">
                    <a
                        href={CURRICULUM_PDF}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group flex flex-col items-start gap-4 rounded-[16px] border keyline bg-card p-6 transition-colors hover:border-l-[5px] hover:border-l-brand-green sm:flex-row sm:items-center sm:gap-6 sm:p-7"
                    >
                        <span className="grid size-14 shrink-0 place-items-center rounded-[12px] bg-brand-green/15 text-brand-green">
                            <FileText className="size-7" />
                        </span>
                        <div className="flex-1">
                            <span className="display block text-[1.125rem] leading-snug text-brand-navy">{t('primary_page.download_curriculum', 'Descarcă Curriculumul Național · învățământul primar (PDF)')}</span>
                            <span className="mt-1 block text-sm text-brand-gray">{t('primary_page.download_note')}</span>
                        </div>
                        <Download className="size-5 shrink-0 text-brand-navy transition-transform group-hover:translate-y-0.5" />
                    </a>
                </Reveal>
            </Band>

            {/* Secțiune ALBASTRĂ — dotări (text verbatim + foto „sala cu proiector + elevi") */}
            <Band variant="navy" pattern="mesh">
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1fr_1.1fr] lg:gap-14">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('primary_page.facilities_eyebrow', 'Sălile și dotările')}</span>
                            <span data-rule className="h-px w-12 origin-left bg-white/30" aria-hidden="true" />
                        </div>
                        <h2 className="display mt-4 text-[clamp(1.5rem,2.8vw,2.125rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                            {t('primary_page.facilities_title', 'Un mediu pregătit pentru primii ani de școală')}
                        </h2>
                        <span data-rule className="mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-5 text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/90">{t('primary_page.facilities_body')}</p>
                    </div>
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-primara/2.jpg"
                            alt={t('primary_page.facilities_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 65%' }}
                        />
                    </figure>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — galerie + CTA */}
            <Band variant="light" pattern="signature">
                <SectionHeader
                    index="✦"
                    label={t('primary_page.gallery_eyebrow', 'Galerie')}
                    title={t('primary_page.gallery_title', 'Din viața clasei')}
                    className="mb-8"
                />
                <Reveal className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                    {GALLERY.map((g) => (
                        <figure key={g.src} className="relative aspect-[4/3] overflow-hidden rounded-[12px] border keyline">
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
                <div className="mx-auto mt-14 max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('primary_page.cta_eyebrow', 'Pasul următor')}
                        title={t('primary_page.cta_title', 'Continuă explorarea treptelor')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('primary_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={CalendarClock} className="w-full justify-center sm:w-auto">
                            {t('primary_page.cta_primary', 'Programează o vizită')}
                        </BrandButton>
                        <BrandButton href="/scoala-gimnaziala" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('primary_page.cta_secondary', 'Vezi treapta gimnazială')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
