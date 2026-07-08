import { Head } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, Globe2, Mail, Star, Users } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const COURSES = [
    {
        key: 'individual',
        title: 'cambridge_page.course_individual_title',
        students: 'cambridge_page.course_individual_students',
        hours: 'cambridge_page.course_individual_hours',
        duration: 'cambridge_page.course_individual_duration',
        price: 'cambridge_page.course_individual_price',
        featured: false,
    },
    {
        key: 'semi',
        title: 'cambridge_page.course_semi_title',
        students: 'cambridge_page.course_semi_students',
        hours: 'cambridge_page.course_semi_hours',
        duration: 'cambridge_page.course_semi_duration',
        price: 'cambridge_page.course_semi_price',
        featured: true, // formatul „mediu" e adesea cel optim — îl evidențiem
    },
    {
        key: 'group',
        title: 'cambridge_page.course_group_title',
        students: 'cambridge_page.course_group_students',
        hours: 'cambridge_page.course_group_hours',
        duration: 'cambridge_page.course_group_duration',
        price: 'cambridge_page.course_group_price',
        featured: false,
    },
] as const;

export default function CambridgeEnglishExam() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cambridge_page.meta_title', 'Cambridge English Exam — Liceul Columna')}>
                <meta name="description" content={t('cambridge_page.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('cambridge_page.title', 'Cambridge English Exam')}
                breadcrumbs={[{ title: t('cambridge_page.breadcrumb_self', 'Cambridge English') }]}
                description={t('cambridge_page.lead')}
            />

            {/* Secțiune ALBASTRĂ — identitate „centru autorizat" + 3 fapte cheie */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('cambridge_page.identity_eyebrow', 'Standard internațional')}
                    title={t('cambridge_page.identity_title', 'Centru autorizat · din 2019')}
                    className="mb-10"
                />
                <Reveal className="grid items-center gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-14">
                    <div className="space-y-5 text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/90">
                        <p>{t('cambridge_page.identity_p1')}</p>
                        <p>{t('cambridge_page.identity_p2')}</p>
                    </div>
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/general/g11.jpg"
                            alt={t('cambridge_page.identity_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={600}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 50%' }}
                        />
                    </figure>
                </Reveal>

                {/* 3 fapte cheie: 2019 · 20 000+ · CECRL (verbatim) */}
                <Reveal className="mt-12 grid gap-4 sm:grid-cols-3">
                    {[
                        { Icon: BadgeCheck, value: 'cambridge_page.fact_year_value', label: 'cambridge_page.fact_year_label' },
                        { Icon: Globe2, value: 'cambridge_page.fact_unis_value', label: 'cambridge_page.fact_unis_label' },
                        { Icon: Star, value: 'cambridge_page.fact_validity_value', label: 'cambridge_page.fact_validity_label' },
                    ].map((f) => (
                        <div key={f.label} className="rounded-[14px] border border-white/15 bg-white/[0.05] p-5 text-center sm:p-6">
                            <f.Icon className="mx-auto size-6 text-brand-green" aria-hidden="true" />
                            <span className="numeral mt-3 block text-[clamp(1.5rem,3vw,2.25rem)] leading-none text-brand-green">{t(f.value)}</span>
                            <span className="eyebrow mt-2 block text-white/75">{t(f.label)}</span>
                        </div>
                    ))}
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — beneficii (recunoaștere internațională, CECRL, valabil pe viață) */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="✦"
                    label={t('cambridge_page.benefits_eyebrow', 'Recunoaștere internațională')}
                    title={t('cambridge_page.benefits_title', 'Un certificat recunoscut peste tot')}
                    className="mb-8"
                />
                <Reveal className="mx-auto max-w-[68ch] space-y-5 text-[clamp(1.125rem,1.55vw,1.25rem)] leading-relaxed text-brand-dark/85">
                    <p>{t('cambridge_page.benefits_p1')}</p>
                    <p>{t('cambridge_page.benefits_p2')}</p>
                </Reveal>
            </Band>

            {/* Secțiune ALBASTRĂ — 3 pachete de curs (individual / semi / grup) cu tarife verbatim */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('cambridge_page.courses_eyebrow', 'Aplică online la curs')}
                    title={t('cambridge_page.courses_title', 'Trei formate de pregătire')}
                    lead={t('cambridge_page.courses_lead')}
                    className="mb-10"
                />
                <div className="grid gap-6 lg:grid-cols-3 lg:gap-7">
                    {COURSES.map((c) => (
                        <Reveal
                            as="article"
                            key={c.key}
                            className={cn(
                                'relative flex flex-col overflow-hidden rounded-[18px] border bg-white/[0.06] p-7 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.55)] sm:p-8',
                                c.featured ? 'border-brand-green' : 'border-white/15',
                            )}
                        >
                            {c.featured && (
                                <span className="absolute top-5 right-5 inline-flex items-center gap-1.5 rounded-full bg-brand-green px-2.5 py-0.5 text-[0.65rem] font-bold tracking-wide text-[color:var(--brand-green-foreground)] uppercase">
                                    <Star className="size-3" /> Recomandat
                                </span>
                            )}
                            <h3 className="display text-[clamp(1.375rem,2.4vw,1.625rem)] text-[color:var(--brand-navy-foreground)]">{t(c.title)}</h3>
                            <p className="mt-1 text-sm text-white/70">{t('cambridge_page.course_per_session')}</p>
                            <dl className="mt-6 space-y-3 border-t border-white/10 pt-5 text-sm">
                                <div className="flex items-baseline justify-between gap-3 text-white/85">
                                    <dt className="flex items-center gap-2 text-white/65">
                                        <Users className="size-3.5 text-brand-green" /> {t('cambridge_page.course_label_students')}
                                    </dt>
                                    <dd className="font-semibold text-[color:var(--brand-navy-foreground)]">{t(c.students)}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3 text-white/85">
                                    <dt className="text-white/65">{t('cambridge_page.course_label_hours')}</dt>
                                    <dd className="font-semibold text-[color:var(--brand-navy-foreground)]">
                                        {t(c.hours)} {t('cambridge_page.course_unit_hours')}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3 text-white/85">
                                    <dt className="text-white/65">{t('cambridge_page.course_label_duration')}</dt>
                                    <dd className="font-semibold text-[color:var(--brand-navy-foreground)]">
                                        {t(c.duration)} {t('cambridge_page.course_unit_weeks')}
                                    </dd>
                                </div>
                            </dl>
                            <div className="mt-6 border-t border-white/10 pt-5">
                                <span className="eyebrow block text-white/65">{t('cambridge_page.course_label_price')}</span>
                                <span className="numeral mt-1 block text-[clamp(2rem,4vw,2.75rem)] leading-none text-brand-green">{t(c.price)}</span>
                                <span className="eyebrow mt-1 block text-white/75">{t('cambridge_page.course_unit_currency')}</span>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA „aplică la curs" */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('cambridge_page.cta_eyebrow', 'Aplică la curs')}
                        title={t('cambridge_page.cta_title', 'Pregătește-te pentru un certificat care durează o viață')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('cambridge_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('cambridge_page.cta_primary', 'Contacte')}
                        </BrandButton>
                        <BrandButton href="/scoala-liceala" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('cambridge_page.cta_secondary', 'Vezi treapta liceală')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
