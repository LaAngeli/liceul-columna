import { Head } from '@inertiajs/react';
import { ChevronDown, ClipboardCheck, FileText, Globe2, Handshake, Mail, Phone, UserCog } from 'lucide-react';
import { useState } from 'react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/**
 * Pagina /admitere — bespoke „Columna Civic Editorial".
 * Conținut 100% VERBATIM de pe site-ul vechi (etape de înmatriculare + 8 FAQ). Fără invenții.
 */

type Faq = {
    q: string;
    intro?: string;
    p1?: string;
    p2?: string;
    list?: string[];
    outro?: string;
};

const FAQS: Faq[] = [
    {
        q: 'admission_page.faq_1_q',
        intro: 'admission_page.faq_1_intro',
        list: [
            'admission_page.faq_1_list_1',
            'admission_page.faq_1_list_2',
            'admission_page.faq_1_list_3',
            'admission_page.faq_1_list_4',
            'admission_page.faq_1_list_5',
        ],
    },
    {
        q: 'admission_page.faq_2_q',
        p1: 'admission_page.faq_2_p1',
        p2: 'admission_page.faq_2_p2',
    },
    {
        q: 'admission_page.faq_3_q',
        p1: 'admission_page.faq_3_p1',
        p2: 'admission_page.faq_3_p2',
    },
    {
        q: 'admission_page.faq_4_q',
        p1: 'admission_page.faq_4_p1',
    },
    {
        q: 'admission_page.faq_5_q',
        intro: 'admission_page.faq_5_intro',
        list: ['admission_page.faq_5_list_1', 'admission_page.faq_5_list_2'],
        outro: 'admission_page.faq_5_outro',
    },
    {
        q: 'admission_page.faq_6_q',
        intro: 'admission_page.faq_6_intro',
        list: ['admission_page.faq_6_list_1', 'admission_page.faq_6_list_2', 'admission_page.faq_6_list_3'],
        outro: 'admission_page.faq_6_outro',
    },
    {
        q: 'admission_page.faq_7_q',
        p1: 'admission_page.faq_7_p1',
        p2: 'admission_page.faq_7_p2',
    },
    {
        q: 'admission_page.faq_8_q',
        p1: 'admission_page.faq_8_p1',
    },
];

export default function Admitere() {
    const t = useTranslations();
    const [openIdx, setOpenIdx] = useState<number | null>(null);

    return (
        <>
            <Head title={t('admission_page.meta_title', 'Admitere — Liceul Columna')}>
                <meta name="description" content={t('admission_page.meta_description')} />
            </Head>

            {/* HERO (ALB) — PageBanner standard */}
            <PageBanner
                title={t('admission_page.title', 'Admitere')}
                breadcrumbs={[{ title: t('admission_page.breadcrumb_self', 'Admitere') }]}
                description={t('admission_page.lead')}
            />

            {/* Bandă NAVY — cele 2 etape, cu blocul de programare */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="01"
                    variant="navy"
                    label={t('admission_page.stages_eyebrow', 'Procedura')}
                    title={t('admission_page.stages_title', 'Etapele de înmatriculare')}
                    lead={t('admission_page.stages_lead')}
                    className="mb-12"
                />

                {/* 2 etape + card programare în lățime completă — design compact, ritm vertical mai strâns */}
                <div className="grid gap-4 md:grid-cols-2 md:gap-5">
                    {/* Etapa 1 — card compact */}
                    <Reveal as="article" className="relative flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-5 sm:p-6">
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-9 place-items-center rounded-full bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                <ClipboardCheck className="size-4" aria-hidden="true" />
                            </span>
                            <span className="eyebrow text-brand-green">{t('admission_page.stage_1_label', 'Etapa 1')}</span>
                        </div>
                        <h3 className="display mt-3 text-[clamp(1.05rem,1.5vw,1.25rem)] leading-snug text-[color:var(--brand-navy-foreground)]">
                            {t('admission_page.stage_1_title', 'Vizita de cunoaștere a Liceului')}
                        </h3>
                        <span data-rule className="mt-3 block h-0.5 w-12 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-3 text-sm leading-relaxed text-white/90">{t('admission_page.stage_1_p1')}</p>
                        <p className="mt-2 text-sm leading-relaxed text-white/80">{t('admission_page.stage_1_p2')}</p>
                        <p className="mt-3 text-sm font-semibold text-[color:var(--brand-navy-foreground)]">{t('admission_page.stage_1_visit_intro')}</p>
                        <ul className="mt-2 space-y-1.5">
                            {([1, 2, 3, 4] as const).map((n) => (
                                <li key={n} className="flex gap-2.5 text-[0.8125rem] leading-relaxed text-white/80">
                                    <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-green" aria-hidden="true" />
                                    <span>{t(`admission_page.stage_1_visit_${n}`)}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-sm leading-relaxed text-white/80">{t('admission_page.stage_1_p3')}</p>
                        <p className="mt-2 text-sm leading-relaxed text-white/80">{t('admission_page.stage_1_p4')}</p>
                    </Reveal>

                    {/* Etapa 2 + card programare integrat */}
                    <Reveal as="article" className="relative flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-5 sm:p-6">
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-9 place-items-center rounded-full bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                <Handshake className="size-4" aria-hidden="true" />
                            </span>
                            <span className="eyebrow text-brand-green">{t('admission_page.stage_2_label', 'Etapa 2')}</span>
                        </div>
                        <h3 className="display mt-3 text-[clamp(1.05rem,1.5vw,1.25rem)] leading-snug text-[color:var(--brand-navy-foreground)]">
                            {t('admission_page.stage_2_title', 'Micro ședință cu Dl Director')}
                        </h3>
                        <span data-rule className="mt-3 block h-0.5 w-12 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-3 text-sm leading-relaxed text-white/90">{t('admission_page.stage_2_body')}</p>

                        {/* Programare telefonică — integrată ca block evidențiat în card */}
                        <div className="mt-5 flex items-start gap-3 rounded-[12px] border border-brand-green/40 bg-brand-green/[0.08] p-4">
                            <span className="grid size-9 shrink-0 place-items-center rounded-[10px] bg-brand-green/20 text-brand-green">
                                <Phone className="size-4" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <span className="eyebrow text-[0.65rem] text-brand-green">{t('admission_page.stage_phone_label', 'Programare la secretariat')}</span>
                                <a
                                    href="tel:+37322742852"
                                    className="display mt-1 block text-[clamp(1.125rem,1.7vw,1.25rem)] font-bold text-[color:var(--brand-navy-foreground)] underline-offset-4 transition-colors hover:underline"
                                >
                                    {t('admission_page.stage_phone_value', '(022) 74 28 52')}
                                </a>
                                <p className="mt-0.5 text-xs text-white/70">{t('admission_page.stage_phone_note')}</p>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Band>

            {/* Bandă ALBĂ — „Ce primesc familiile în prima întâlnire" (vizual: foto + 4 highlights) */}
            <Band variant="light" pattern="mesh">
                <Reveal className="grid items-center gap-10 lg:grid-cols-[1fr_1.1fr] lg:gap-14">
                    <figure className="relative aspect-[4/3] overflow-hidden rounded-[18px] border border-brand-navy/10 shadow-[0_30px_70px_-50px_rgba(15,77,119,0.45)]">
                        <img
                            src="/images/galerie/general/g11.jpg"
                            alt={t('admission_page.visit_photo_alt')}
                            loading="lazy"
                            decoding="async"
                            width={900}
                            height={675}
                            className="absolute inset-0 h-full w-full object-cover"
                            style={{ objectPosition: 'center 50%' }}
                        />
                    </figure>
                    <div>
                        <SectionHeader
                            index="02"
                            label={t('admission_page.visit_eyebrow', 'În cadrul vizitei')}
                            title={t('admission_page.visit_title', 'Ce primesc familiile în prima întâlnire')}
                            lead={t('admission_page.visit_lead')}
                            className="mb-7"
                        />
                        <ul className="space-y-3.5">
                            {([1, 2, 3, 4] as const).map((n) => (
                                <li key={n} className="flex gap-3.5 text-[0.95rem] leading-relaxed text-brand-dark/85">
                                    <span className="mt-1.5 size-2 shrink-0 rounded-full bg-brand-green" aria-hidden="true" />
                                    <span>{t(`admission_page.stage_1_visit_${n}`)}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>
            </Band>

            {/* Bandă NAVY — FAQ accordion (8 întrebări) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="03"
                    variant="navy"
                    label={t('admission_page.faq_eyebrow', 'Întrebări frecvente')}
                    title={t('admission_page.faq_title', 'Răspunsuri pentru părinți')}
                    lead={t('admission_page.faq_lead')}
                    className="mb-10"
                />
                <Reveal className="mx-auto grid max-w-5xl auto-rows-max grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
                    {FAQS.map((faq, idx) => {
                        const isOpen = openIdx === idx;

                        return (
                            <article
                                key={faq.q}
                                className={`rounded-[14px] border bg-white/[0.04] transition-colors ${isOpen ? 'border-brand-green/40 bg-white/[0.07]' : 'border-white/15'}`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setOpenIdx(isOpen ? null : idx)}
                                    aria-expanded={isOpen}
                                    className="flex w-full items-start gap-4 px-5 py-4 text-left transition-colors hover:bg-white/[0.04] sm:px-6 sm:py-5"
                                >
                                    <span className="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-brand-green/15 text-xs font-bold text-brand-green ring-1 ring-brand-green/30">
                                        {idx + 1}
                                    </span>
                                    <span className="flex-1 text-[0.98rem] leading-snug font-semibold text-[color:var(--brand-navy-foreground)] sm:text-[1.05rem]">
                                        {t(faq.q)}
                                    </span>
                                    <ChevronDown
                                        className={`mt-1 size-5 shrink-0 text-brand-green transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}
                                        aria-hidden="true"
                                    />
                                </button>
                                {isOpen && (
                                    <div className="space-y-3 px-5 pb-5 sm:px-6 sm:pb-6">
                                        <span data-rule className="block h-px w-12 bg-brand-green/40" aria-hidden="true" />
                                        {faq.intro && <p className="text-white/85">{t(faq.intro)}</p>}
                                        {faq.p1 && <p className="leading-relaxed text-white/85">{t(faq.p1)}</p>}
                                        {faq.p2 && <p className="leading-relaxed text-white/85">{t(faq.p2)}</p>}
                                        {faq.list && (
                                            <ul className="space-y-2.5">
                                                {faq.list.map((key) => (
                                                    <li key={key} className="flex gap-3 text-sm leading-relaxed text-white/85">
                                                        <span className="mt-2 size-1.5 shrink-0 rounded-full bg-brand-green" aria-hidden="true" />
                                                        <span>{t(key)}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {faq.outro && <p className="leading-relaxed text-white/85">{t(faq.outro)}</p>}
                                    </div>
                                )}
                            </article>
                        );
                    })}
                </Reveal>
            </Band>

            {/* Bandă ALBĂ — CTA „Înscrie-ți copilul" */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="04"
                        align="center"
                        label={t('admission_page.cta_eyebrow', 'Următorul pas')}
                        title={t('admission_page.cta_title', 'Înscrie-ți copilul')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('admission_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/inregistrarea-student" variant="primary" icon={FileText} className="w-full justify-center sm:w-auto">
                            {t('admission_page.cta_primary', 'Formular de înscriere')}
                        </BrandButton>
                        <BrandButton href="/contacte" variant="ghost" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('admission_page.cta_secondary', 'Contacte')}
                        </BrandButton>
                    </div>
                    {/* Mici elemente decorative — păstrăm referințele Globe2/UserCog ca să rămână tree-shake-safe */}
                    <div className="mt-10 flex justify-center gap-6 text-brand-gray/40">
                        <Globe2 className="size-4" aria-hidden="true" />
                        <UserCog className="size-4" aria-hidden="true" />
                    </div>
                </div>
            </Band>
        </>
    );
}
