import { Head } from '@inertiajs/react';
import { Bell, Brush, CalendarDays, Globe2, GraduationCap, Mail, Map, Palette, Sparkles, Trophy, Users } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/**
 * Pagină-placeholder ONESTĂ: prezintă conceptul + categorii orientative (verbe la viitor/condițional),
 * fără să inventeze cifre, date sau taxe. Detaliile reale ale ediției urmează să fie comunicate.
 */

const ACTIVITIES = [
    { Icon: Trophy, title: 'summer_camp.a1_title', body: 'summer_camp.a1_body' },
    { Icon: Palette, title: 'summer_camp.a2_title', body: 'summer_camp.a2_body' },
    { Icon: GraduationCap, title: 'summer_camp.a3_title', body: 'summer_camp.a3_body' },
    { Icon: Map, title: 'summer_camp.a4_title', body: 'summer_camp.a4_body' },
    { Icon: Sparkles, title: 'summer_camp.a5_title', body: 'summer_camp.a5_body' },
    { Icon: Globe2, title: 'summer_camp.a6_title', body: 'summer_camp.a6_body' },
] as const;

const PLAN_ITEMS = [
    { Icon: CalendarDays, title: 'summer_camp.plan_item_1_title', body: 'summer_camp.plan_item_1_body' },
    { Icon: Users, title: 'summer_camp.plan_item_2_title', body: 'summer_camp.plan_item_2_body' },
    { Icon: Brush, title: 'summer_camp.plan_item_3_title', body: 'summer_camp.plan_item_3_body' },
] as const;

export default function TabaraDeVara() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('summer_camp.meta_title', 'Tabăra de vară — Liceul Columna')}>
                <meta name="description" content={t('summer_camp.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner) */}
            <PageBanner
                title={t('summer_camp.title', 'Tabăra de vară')}
                breadcrumbs={[{ title: t('summer_camp.breadcrumb_self', 'Tabăra de vară') }]}
                description={t('summer_camp.lead')}
            />

            {/* Secțiune ALBASTRĂ — despre tabără (concept + foto + badge „În pregătire") */}
            <Band variant="navy" pattern="mesh">
                <Reveal className="grid items-center gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-14">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('summer_camp.about_eyebrow', 'Despre tabără')}</span>
                            <span data-rule className="h-px w-12 origin-left bg-white/30" aria-hidden="true" />
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-green/20 px-2.5 py-1 text-xs font-semibold tracking-wide text-brand-green uppercase ring-1 ring-brand-green/40">
                                {t('summer_camp.status_badge', 'În pregătire')}
                            </span>
                        </div>
                        <h2 className="display mt-4 text-[clamp(1.5rem,2.8vw,2.125rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                            {t('summer_camp.about_title', 'O vară activă, în comunitatea în care copilul crește')}
                        </h2>
                        <span data-rule className="mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-5 text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/90">{t('summer_camp.about_body')}</p>
                    </div>
                    <figure className="relative aspect-[16/10] overflow-hidden rounded-[18px] border border-white/15 shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                        <img
                            src="/images/galerie/general/g4.jpg"
                            alt={t('summer_camp.about_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={600}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 45%' }}
                        />
                    </figure>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — 6 categorii orientative de activități (NU lucruri confirmate; verbe la viitor/posibil) */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="✦"
                    label={t('summer_camp.activities_eyebrow', 'Categorii de activități')}
                    title={t('summer_camp.activities_title', 'Ce poate cuprinde o ediție')}
                    lead={t('summer_camp.activities_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                    {ACTIVITIES.map((a) => (
                        <Reveal as="article" key={a.title} className="flex flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-green bg-card p-6 sm:p-7">
                            <span className="grid size-11 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                <a.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <h3 className="display mt-4 text-[1.125rem] leading-snug text-brand-navy">{t(a.title)}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-brand-dark/85">{t(a.body)}</p>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBASTRĂ — „În pregătire": 3 carduri cu ce urmează să fie comunicat */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('summer_camp.plan_eyebrow', 'Ce urmează')}
                    title={t('summer_camp.plan_title', 'Detaliile ediției — în pregătire')}
                    lead={t('summer_camp.plan_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 md:grid-cols-3">
                    {PLAN_ITEMS.map((p) => (
                        <Reveal as="article" key={p.title} className="flex items-start gap-4 rounded-[16px] border border-white/15 bg-white/[0.05] p-6">
                            <span className="grid size-11 shrink-0 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                <p.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <div>
                                <h3 className="text-[1rem] leading-snug font-semibold text-[color:var(--brand-navy-foreground)]">{t(p.title)}</h3>
                                <p className="mt-1.5 text-sm leading-relaxed text-white/85">{t(p.body)}</p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA „rămâi la curent" */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('summer_camp.cta_eyebrow', 'Rămâi la curent')}
                        title={t('summer_camp.cta_title', 'Vrei să primești primul anunțurile?')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('summer_camp.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('summer_camp.cta_primary', 'Contactează secretariatul')}
                        </BrandButton>
                        <BrandButton href="/actualitati-si-evenimente" variant="ghost" icon={Bell} className="w-full justify-center sm:w-auto">
                            {t('summer_camp.cta_secondary', 'Vezi Actualitățile')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
