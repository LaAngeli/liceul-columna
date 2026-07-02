import { Head } from '@inertiajs/react';
import { ArrowRight, CalendarClock, Quote } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Band, FourStar, Reveal, Rhombus, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

type Tr = (k: string, f?: string) => string;

/* Obiectivele formative (3) și direcțiile strategice (4) — verbatim din Regulamentul CPAE (site vechi). */
const OBJECTIVES = ['obj_1', 'obj_2', 'obj_3'];
const DIRECTIONS = ['dir_1', 'dir_2', 'dir_3', 'dir_4'];

/* Coordonatorii atelierelor — 7 cu portret real (extensii mixte .png/.jpg), 2 fără foto (fallback inițiale). */
const COORDINATORS: { name: string; photo: string | null }[] = [
    { name: 'Rudei Rodica', photo: 'rudei-rodica.png' },
    { name: 'Dumitrașcu Alexandr', photo: 'dumitrascu-alexandr.png' },
    { name: 'Ungureanu Vasile', photo: 'ungureanu-vasile.jpg' },
    { name: 'Ciobanu Adrian', photo: 'ciobanu-adrian.png' },
    { name: 'Breabin Marius', photo: 'breabin-marius.jpg' },
    { name: 'Tricolici Olga', photo: 'tricolici-olga.png' },
    { name: 'Bardița Irina', photo: 'bardita-irina.jpg' },
    { name: 'Voitcovschi Daniela', photo: null },
    { name: 'Doriana Zubcu-Mărginean', photo: null },
];

function initialsOf(name: string): string {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('');
}

function CoordinatorCard({ t, name, photo }: { t: Tr; name: string; photo: string | null }) {
    return (
        <Reveal as="div" className="h-full">
            <div className="group flex h-full flex-col items-center rounded-[16px] border keyline bg-card p-5 text-center transition-all hover:-translate-y-1 hover:shadow-[0_18px_40px_-28px_rgba(15,77,119,0.5)]">
                <span className="relative block size-24 shrink-0 overflow-hidden rounded-full border-2 keyline bg-brand-navy/5 ring-2 ring-transparent transition-all group-hover:ring-brand-green">
                    {photo ? (
                        <img
                            src={`/images/coordonatori/${photo}`}
                            alt={name}
                            loading="lazy"
                            decoding="async"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    ) : (
                        <span className="absolute inset-0 grid place-items-center bg-gradient-to-br from-brand-navy to-[#0c3d5f]" aria-hidden="true">
                            <span className="display text-xl text-white">{initialsOf(name)}</span>
                        </span>
                    )}
                </span>
                <span className="display mt-4 text-[1.0625rem] leading-tight text-balance text-brand-navy">{name}</span>
                <span className="mt-1 text-sm text-brand-gray">{t('cpae.role_coordinator', 'Coordonator de atelier')}</span>
            </div>
        </Reveal>
    );
}

export default function Extracurriculare() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cpae.meta_title', 'Activități extracurriculare (CPAE) — Liceul Columna')}>
                <meta name="description" content={t('cpae.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal alb + crest watermark */}
            <PageBanner
                title={t('cpae.title', 'Activități extracurriculare')}
                breadcrumbs={[{ title: t('cpae.breadcrumb_self', 'CPAE') }]}
                description={t('cpae.banner_lead')}
            />

            {/* Secțiune ALBASTRĂ — viziunea CPAE (foto „lecție non-formală" + citat Ken Robinson) */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="I"
                    variant="navy"
                    label={t('cpae.vision_eyebrow', 'Viziunea Centrului')}
                    title={t('cpae.vision_title', 'Educație prin creativitate și curaj')}
                    className="mb-10"
                />
                <Reveal className="grid items-center gap-8 lg:grid-cols-[1fr_1.05fr] lg:gap-14">
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/general/g12.jpg"
                            alt={t('cpae.vision_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={563}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 45%' }}
                        />
                    </figure>
                    <div>
                        <p className="text-[clamp(1.0625rem,1.7vw,1.25rem)] leading-relaxed text-white/90">{t('cpae.vision_p1')}</p>
                        <p className="mt-5 leading-relaxed text-white/80">{t('cpae.vision_p2')}</p>
                        <span data-rule className="mt-7 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <span className="mt-7 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/[0.05] px-4 py-2 text-sm text-white/85">
                            <FourStar className="size-3.5 shrink-0 text-brand-green" />
                            {t('cpae.founding_label', 'Aprobat prin Ordinul directorului nr. 108A · 20.09.2021')}
                        </span>
                    </div>
                </Reveal>

                {/* Citat reper — Ken Robinson */}
                <Reveal className="mx-auto mt-14 max-w-3xl text-center">
                    <Quote className="mx-auto size-8 text-brand-green" aria-hidden="true" />
                    <blockquote className="display mt-4 text-[clamp(1.25rem,2.6vw,1.875rem)] leading-snug text-balance text-[color:var(--brand-navy-foreground)]">
                        {t('cpae.vision_quote')}
                    </blockquote>
                    <figcaption className="numeral mt-5 text-sm tracking-wide text-white/70">— {t('cpae.vision_quote_author', 'Ken Robinson')}</figcaption>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — obiective formative + direcții strategice (cele două liste) */}
            <Band variant="light">
                <SectionHeader
                    index="✦"
                    label={t('cpae.aims_eyebrow', 'Ce urmărim')}
                    title={t('cpae.aims_title', 'Obiective și direcții strategice')}
                    lead={t('cpae.aims_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 lg:grid-cols-2 lg:gap-7">
                    {/* Obiective formative */}
                    <Reveal as="div" className="h-full">
                        <div className="flex h-full flex-col rounded-[18px] border keyline border-l-[5px] border-l-brand-green bg-card p-6 sm:p-8">
                            <h3 className="display text-xl text-brand-navy">{t('cpae.obj_title', 'Mediul formativ')}</h3>
                            <ul className="mt-5 space-y-4">
                                {OBJECTIVES.map((k) => (
                                    <li key={k} className="flex gap-3">
                                        <Rhombus className="mt-1.5 size-3.5 shrink-0 text-brand-green" />
                                        <span className="leading-relaxed text-brand-dark/85">{t(`cpae.${k}`)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </Reveal>

                    {/* Direcții strategice */}
                    <Reveal as="div" className="h-full">
                        <div className="flex h-full flex-col rounded-[18px] border keyline bg-surface-navy p-6 sm:p-8">
                            <h3 className="display text-xl text-[color:var(--brand-navy-foreground)]">{t('cpae.dir_title', 'Direcții strategice')}</h3>
                            <ol className="mt-5 space-y-4">
                                {DIRECTIONS.map((k, i) => (
                                    <li key={k} className="flex gap-4">
                                        <span className="numeral flex size-8 shrink-0 items-center justify-center rounded-full bg-white/[0.08] text-sm text-brand-green ring-1 ring-white/15">
                                            {i + 1}
                                        </span>
                                        <span className="leading-relaxed text-white/85">{t(`cpae.${k}`)}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </Reveal>
                </div>
            </Band>

            {/* Secțiune ALBASTRĂ — ateliere și coordonatori (carduri-portret pe navy) */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    align="center"
                    label={t('cpae.team_eyebrow', 'Cine ne ghidează')}
                    title={t('cpae.team_title', 'Ateliere și coordonatori')}
                    lead={t('cpae.team_lead')}
                    className="mx-auto max-w-2xl"
                />
                <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {COORDINATORS.map((c) => (
                        <CoordinatorCard key={c.name} t={t} name={c.name} photo={c.photo} />
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA + bară „Vezi și" */}
            <Band variant="light">
                <div className="mx-auto max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('cpae.cta_eyebrow', 'Implică-te')}
                        title={t('cpae.cta_title', 'Vino la atelierele CPAE')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('cpae.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <LocaleLink
                            href="/orarul-cpae"
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-surface-navy px-6 text-sm font-semibold text-[color:var(--brand-navy-foreground)] transition-colors hover:bg-surface-navy/90"
                        >
                            <CalendarClock className="size-4 text-brand-green" />
                            {t('cpae.cta_primary', 'Vezi orarul CPAE')}
                        </LocaleLink>
                        <LocaleLink
                            href="/contacte"
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full border keyline bg-card px-6 text-sm font-semibold text-brand-navy transition-colors hover:bg-surface-navy hover:text-[color:var(--brand-navy-foreground)]"
                        >
                            {t('cpae.cta_secondary', 'Contactează-ne')}
                            <ArrowRight className="size-4 text-brand-green" />
                        </LocaleLink>
                    </div>
                </div>
            </Band>
        </>
    );
}
