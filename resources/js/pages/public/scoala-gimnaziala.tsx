import { Head } from '@inertiajs/react';
import { ArrowRight, CalendarClock, Download, FileText } from 'lucide-react';
import { Band, BrandButton, Reveal, Rhombus, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/* Disciplinele treptei gimnaziale (V–IX) — fiecare cu PDF-ul oficial de curriculum (Curriculum Național 2019),
   descărcat local din columna.org.md în public/downloads/curriculum/gimnaziu/. */
const SUBJECTS = [
    { slug: 'matematica', key: 'math' },
    { slug: 'limba-romana', key: 'romanian' },
    { slug: 'limba-straina', key: 'foreign' },
    { slug: 'limba-rusa', key: 'russian' },
    { slug: 'fizica', key: 'physics' },
    { slug: 'chimie', key: 'chem' },
    { slug: 'biologie', key: 'bio' },
    { slug: 'geografie', key: 'geo' },
    { slug: 'istorie', key: 'history' },
    { slug: 'informatica', key: 'cs' },
    { slug: 'educatie-fizica', key: 'pe' },
    { slug: 'educatie-plastica', key: 'art' },
    { slug: 'educatie-muzicala', key: 'music' },
] as const;

/* Galerie — imaginile reale ale treptei gimnaziale (imagini DIFERITE de cele din identitate/dotări). */
const GALLERY = [
    { src: '/images/galerie/general/g9.jpg', alt: 'gymnasium_page.gallery_alt_1' },
    { src: '/images/galerie/general/g5.jpg', alt: 'gymnasium_page.gallery_alt_2' },
    { src: '/images/galerie/general/g18.jpg', alt: 'gymnasium_page.gallery_alt_3' },
    { src: '/images/galerie/general/g7.jpg', alt: 'gymnasium_page.gallery_alt_4' },
] as const;

export default function ScoalaGimnaziala() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('gymnasium_page.meta_title', 'Școala gimnazială · V–IX — Liceul Columna')}>
                <meta name="description" content={t('gymnasium_page.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('gymnasium_page.title', 'Școala gimnazială')}
                breadcrumbs={[
                    { title: t('gymnasium_page.breadcrumb_section', 'Structura școlii'), href: '/structura-scolii' },
                    { title: t('gymnasium_page.breadcrumb_self', 'Școala gimnazială') },
                ]}
                description={t('gymnasium_page.lead')}
            />

            {/* Secțiune ALBASTRĂ — identitatea treptei (foto „sală cu proiector + elevi") */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="II"
                    variant="navy"
                    label={t('gymnasium_page.identity_eyebrow', 'Treapta gimnazială')}
                    title={t('gymnasium_page.identity_title', 'Aprofundare: caracter și cunoaștere')}
                    className="mb-10"
                />
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1.05fr_1fr] lg:gap-14">
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-gimnaziala/2.jpg"
                            alt={t('gymnasium_page.identity_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 60%' }}
                        />
                    </figure>
                    <div>
                        <div className="flex items-center gap-2 text-brand-green" aria-hidden="true">
                            <Rhombus className="size-4" />
                            <span className="numeral text-sm text-white/80">V–IX</span>
                        </div>
                        <p className="mt-5 text-[clamp(1.0625rem,1.7vw,1.25rem)] leading-relaxed text-white/90">{t('gymnasium_page.identity_body')}</p>
                        <span data-rule className="mt-7 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                    </div>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — Curriculum: cele 13 discipline cu PDF descărcabil */}
            <Band variant="light">
                <SectionHeader
                    index="✦"
                    label={t('gymnasium_page.curriculum_eyebrow', 'Ce studiem')}
                    title={t('gymnasium_page.curriculum_title', 'Curriculumul la disciplină')}
                    lead={t('gymnasium_page.curriculum_lead')}
                    className="mb-10"
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {SUBJECTS.map((s) => (
                        <Reveal as="div" key={s.slug} className="h-full">
                            <a
                                href={`/downloads/curriculum/gimnaziu/${s.slug}.pdf`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="group flex h-full items-center gap-3 rounded-[14px] border keyline border-l-[5px] border-l-brand-green bg-card p-4 transition-all hover:-translate-y-0.5 hover:shadow-[0_16px_36px_-26px_rgba(15,77,119,0.5)]"
                            >
                                <span className="grid size-11 shrink-0 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                    <FileText className="size-5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="display block text-[0.95rem] leading-tight text-balance text-brand-navy">{t(`gymnasium_page.sub_${s.key}`)}</span>
                                    <span className="text-xs text-brand-gray">{t('gymnasium_page.download_hint', 'PDF · Curriculum Național 2019')}</span>
                                </span>
                                <Download className="size-4 shrink-0 text-brand-navy transition-transform group-hover:translate-y-0.5" />
                            </a>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBASTRĂ — dotări (text verbatim + foto „laborator", imagine diferită) */}
            <Band variant="navy" pattern="dotgrid">
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1fr_1.1fr] lg:gap-14">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('gymnasium_page.facilities_eyebrow', 'Sălile și dotările')}</span>
                            <span data-rule className="h-px w-12 origin-left bg-white/30" aria-hidden="true" />
                        </div>
                        <h2 className="display mt-4 text-[clamp(1.5rem,2.8vw,2.125rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                            {t('gymnasium_page.facilities_title', 'Săli specializate pentru discipline reale')}
                        </h2>
                        <span data-rule className="mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-5 text-[clamp(1.0625rem,1.6vw,1.1875rem)] leading-relaxed text-white/90">{t('gymnasium_page.facilities_body')}</p>
                    </div>
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/scoala-liceala/6.jpg"
                            alt={t('gymnasium_page.facilities_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={505}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 55%' }}
                        />
                    </figure>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — galerie + CTA */}
            <Band variant="light">
                <SectionHeader
                    index="✦"
                    label={t('gymnasium_page.gallery_eyebrow', 'Galerie')}
                    title={t('gymnasium_page.gallery_title', 'Din viața treptei gimnaziale')}
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
                        label={t('gymnasium_page.cta_eyebrow', 'Pasul următor')}
                        title={t('gymnasium_page.cta_title', 'Continuă explorarea treptelor')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('gymnasium_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={CalendarClock} className="w-full justify-center sm:w-auto">
                            {t('gymnasium_page.cta_primary', 'Programează o vizită')}
                        </BrandButton>
                        <BrandButton href="/scoala-liceala" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('gymnasium_page.cta_secondary', 'Vezi treapta liceală')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
