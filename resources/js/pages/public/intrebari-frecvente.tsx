import { Head } from '@inertiajs/react';
import { ArrowRight, BookMarked, BookOpenCheck, ChevronDown, ClipboardCheck, GraduationCap, Languages, Mail, MonitorSmartphone, Sparkles, Users2, Wallet } from 'lucide-react';
import { useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/**
 * Pagina /intrebari-frecvente — bespoke „Columna Civic Editorial".
 * HUB de FAQ general (răspunsuri scurte verbatim) + carduri-categorii + 4 link-uri spre pagini dedicate.
 */

type Cat = { Icon: typeof BookOpenCheck; title: string; body: string; href: string; link: string; questions: string[] };

const CATEGORIES: readonly Cat[] = [
    {
        Icon: ClipboardCheck,
        title: 'faq_page.cat_1_title',
        body: 'faq_page.cat_1_body',
        href: '/admitere',
        link: 'faq_page.cat_1_link',
        questions: ['faq_page.q1_q', 'faq_page.q6_q'],
    },
    {
        Icon: BookOpenCheck,
        title: 'faq_page.cat_2_title',
        body: 'faq_page.cat_2_body',
        href: '/scoala-primara',
        link: 'faq_page.cat_2_link',
        questions: ['faq_page.q2_q', 'faq_page.q3_q'],
    },
    {
        Icon: Sparkles,
        title: 'faq_page.cat_3_title',
        body: 'faq_page.cat_3_body',
        href: '/extracurriculare',
        link: 'faq_page.cat_3_link',
        questions: ['faq_page.q4_q', 'faq_page.q5_q'],
    },
] as const;

const FAQS = [
    { q: 'faq_page.q1_q', a: 'faq_page.q1_a' },
    { q: 'faq_page.q2_q', a: 'faq_page.q2_a' },
    { q: 'faq_page.q3_q', a: 'faq_page.q3_a' },
    { q: 'faq_page.q4_q', a: 'faq_page.q4_a' },
    { q: 'faq_page.q5_q', a: 'faq_page.q5_a' },
    { q: 'faq_page.q6_q', a: 'faq_page.q6_a' },
] as const;

type Related = { Icon: typeof BookMarked; title: string; body: string; link: string; href: string };

const RELATED: readonly Related[] = [
    { Icon: BookMarked, title: 'faq_page.r_admitere_title', body: 'faq_page.r_admitere_body', link: 'faq_page.r_admitere_link', href: '/admitere' },
    { Icon: Wallet, title: 'faq_page.r_taxe_title', body: 'faq_page.r_taxe_body', link: 'faq_page.r_taxe_link', href: '/taxe' },
    { Icon: Languages, title: 'faq_page.r_cambridge_title', body: 'faq_page.r_cambridge_body', link: 'faq_page.r_cambridge_link', href: '/cambridge-english-exam' },
    { Icon: Users2, title: 'faq_page.r_cpae_title', body: 'faq_page.r_cpae_body', link: 'faq_page.r_cpae_link', href: '/extracurriculare' },
] as const;

export default function IntrebariFrecvente() {
    const t = useTranslations();
    const [openIdx, setOpenIdx] = useState<number | null>(null);

    return (
        <>
            <Head title={t('faq_page.meta_title', 'Întrebări frecvente — Liceul Columna')}>
                <meta name="description" content={t('faq_page.meta_description')} />
            </Head>

            {/* HERO (ALB) */}
            <PageBanner
                title={t('faq_page.title', 'Întrebări frecvente')}
                breadcrumbs={[{ title: t('faq_page.breadcrumb_self', 'Întrebări frecvente') }]}
                description={t('faq_page.lead')}
            />

            {/* Bandă NAVY — 3 categorii (admitere/program/viața) cu listă scurtă + link */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="01"
                    variant="navy"
                    label={t('faq_page.categories_eyebrow', 'Pe scurt')}
                    title={t('faq_page.categories_title', 'Trei direcții pe care le acoperim')}
                    lead={t('faq_page.categories_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 md:grid-cols-3 md:gap-6">
                    {CATEGORIES.map((c, i) => (
                        <Reveal as="article" key={c.title} className="flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-6 sm:p-7">
                            <div className="flex items-center gap-2.5">
                                <span className="grid size-10 place-items-center rounded-[10px] bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                    <c.Icon className="size-5" aria-hidden="true" />
                                </span>
                                <span className="eyebrow text-brand-green">
                                    {String(i + 1).padStart(2, '0')} · {t('faq_page.cat_1_eyebrow', 'Categorie')}
                                </span>
                            </div>
                            <h3 className="display mt-4 text-[clamp(1.1rem,1.6vw,1.3rem)] leading-snug text-[color:var(--brand-navy-foreground)]">
                                {t(c.title)}
                            </h3>
                            <span data-rule className="mt-3 block h-0.5 w-12 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                            <p className="mt-3 text-sm leading-relaxed text-white/85">{t(c.body)}</p>
                            <ul className="mt-4 space-y-1.5">
                                {c.questions.map((q) => (
                                    <li key={q} className="flex gap-2.5 text-[0.8125rem] leading-relaxed text-white/75">
                                        <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-green" aria-hidden="true" />
                                        <span>{t(q)}</span>
                                    </li>
                                ))}
                            </ul>
                            <LocaleLink
                                href={c.href}
                                className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-green transition-colors hover:text-brand-green/80"
                            >
                                {t(c.link)}
                                <ArrowRight className="size-3.5" aria-hidden="true" />
                            </LocaleLink>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Bandă ALBĂ — FAQ accordion cu cele 6 întrebări (verbatim) */}
            <Band variant="light">
                <SectionHeader
                    index="02"
                    label={t('faq_page.faq_eyebrow', 'Lista completă')}
                    title={t('faq_page.faq_title', 'Răspunsuri pentru părinți')}
                    lead={t('faq_page.faq_lead')}
                    className="mb-10"
                />
                <Reveal className="mx-auto grid max-w-5xl auto-rows-max grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                    {FAQS.map((faq, idx) => {
                        const isOpen = openIdx === idx;

                        return (
                            <article
                                key={faq.q}
                                className={`rounded-[14px] border bg-card transition-colors ${isOpen ? 'border-brand-green/50 shadow-[0_20px_40px_-30px_rgba(15,77,119,0.35)]' : 'border-brand-navy/10'}`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setOpenIdx(isOpen ? null : idx)}
                                    aria-expanded={isOpen}
                                    className="flex w-full items-start gap-4 px-5 py-4 text-left transition-colors hover:bg-brand-green/5 sm:px-6 sm:py-5"
                                >
                                    <span className="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-green/15 text-xs font-bold text-brand-green ring-1 ring-brand-green/30">
                                        {idx + 1}
                                    </span>
                                    <span className="flex-1 text-[0.98rem] leading-snug font-semibold text-brand-navy sm:text-[1.05rem]">
                                        {t(faq.q)}
                                    </span>
                                    <ChevronDown
                                        className={`mt-1 size-5 shrink-0 text-brand-green transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}
                                        aria-hidden="true"
                                    />
                                </button>
                                {isOpen && (
                                    <div className="space-y-3 px-5 pb-5 sm:px-6 sm:pb-6">
                                        <span data-rule className="block h-px w-12 bg-brand-green/50" aria-hidden="true" />
                                        <p className="leading-relaxed text-brand-dark/85">{t(faq.a)}</p>
                                    </div>
                                )}
                            </article>
                        );
                    })}
                </Reveal>
            </Band>

            {/* Bandă NAVY — 4 carduri-linkuri către paginile dedicate */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="03"
                    variant="navy"
                    label={t('faq_page.related_eyebrow', 'Pagini dedicate')}
                    title={t('faq_page.related_title', 'Pentru detalii, mergi direct la sursa')}
                    lead={t('faq_page.related_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    {RELATED.map((r) => (
                        <Reveal as="article" key={r.title} className="flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-6">
                            <span className="grid size-11 place-items-center rounded-[10px] bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                <r.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <h3 className="display mt-4 text-[1.125rem] leading-snug text-[color:var(--brand-navy-foreground)]">{t(r.title)}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-white/80">{t(r.body)}</p>
                            <LocaleLink
                                href={r.href}
                                className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-green transition-colors hover:text-brand-green/80"
                            >
                                {t(r.link)}
                                <ArrowRight className="size-3.5" aria-hidden="true" />
                            </LocaleLink>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Bandă ALBĂ — CTA „Nu ai găsit răspunsul?" */}
            <Band variant="light">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="04"
                        align="center"
                        label={t('faq_page.cta_eyebrow', 'Nu ai găsit răspunsul?')}
                        title={t('faq_page.cta_title', 'Scrie-ne sau sună-ne')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('faq_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('faq_page.cta_primary', 'Contacte')}
                        </BrandButton>
                        <BrandButton href="/inregistrarea-student" variant="ghost" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                            {t('faq_page.cta_secondary', 'Înscrie copilul')}
                        </BrandButton>
                    </div>
                    {/* Mici elemente decorative — păstrăm referințele MonitorSmartphone tree-shake-safe */}
                    <div className="mt-10 flex justify-center gap-6 text-brand-gray/40">
                        <MonitorSmartphone className="size-4" aria-hidden="true" />
                    </div>
                </div>
            </Band>
        </>
    );
}
