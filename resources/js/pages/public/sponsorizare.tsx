import { Head } from '@inertiajs/react';
import { ArrowRight, ClipboardCheck, Coins, Download, ExternalLink, FileText, Gavel, Hash, Heart, Landmark, Mail, Scale, ScrollText, Wallet } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/**
 * Pagina /sponsorizare — bespoke „Columna Civic Editorial".
 * Conținut 100% VERBATIM de pe site-ul vechi: Mecanism 2% (legi, IDNO 1004600000818, perioadă) +
 * Sponsorizare directă (cadru legal). Documentele (CET18 + Contract) — în curs de încărcare.
 */

const STEPS = [
    { Icon: Download, title: 'sponsorship_page.step_1_title', body: 'sponsorship_page.step_1_body' },
    { Icon: Hash, title: 'sponsorship_page.step_2_title', body: 'sponsorship_page.step_2_body' },
    { Icon: ClipboardCheck, title: 'sponsorship_page.step_3_title', body: 'sponsorship_page.step_3_body' },
] as const;

const LEGAL_ACTS = [
    { Icon: Scale, type: 'sponsorship_page.legal_1_type', number: 'sponsorship_page.legal_1_number', date: 'sponsorship_page.legal_1_date', body: 'sponsorship_page.legal_1_body' },
    { Icon: Gavel, type: 'sponsorship_page.legal_2_type', number: 'sponsorship_page.legal_2_number', date: 'sponsorship_page.legal_2_date', body: 'sponsorship_page.legal_2_body' },
    { Icon: Landmark, type: 'sponsorship_page.legal_3_type', number: 'sponsorship_page.legal_3_number', date: 'sponsorship_page.legal_3_date', body: 'sponsorship_page.legal_3_body' },
] as const;

const LINKS = [
    { label: 'sponsorship_page.link_1_label', url: 'sponsorship_page.link_1_url' },
    { label: 'sponsorship_page.link_2_label', url: 'sponsorship_page.link_2_url' },
    { label: 'sponsorship_page.link_3_label', url: 'sponsorship_page.link_3_url' },
    { label: 'sponsorship_page.link_4_label', url: 'sponsorship_page.link_4_url' },
] as const;

export default function Sponsorizare() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('sponsorship_page.meta_title', 'Sponsorizare — Liceul Columna')}>
                <meta name="description" content={t('sponsorship_page.meta_description')} />
            </Head>

            {/* HERO (ALB) */}
            <PageBanner
                title={t('sponsorship_page.title', 'Sponsorizare')}
                breadcrumbs={[{ title: t('sponsorship_page.breadcrumb_self', 'Sponsorizare') }]}
                description={t('sponsorship_page.lead')}
            />

            {/* Bandă NAVY — Mecanismul 2% (cadru legal verbatim + IDNO evidențiat) */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="01"
                    variant="navy"
                    label={t('sponsorship_page.mech_eyebrow', 'Cadru legal')}
                    title={t('sponsorship_page.mech_title', 'Mecanismul 2%')}
                    lead={t('sponsorship_page.mech_lead')}
                    className="mb-10"
                />

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:gap-10">
                    {/* Stânga — paragrafele verbatim */}
                    <Reveal className="space-y-4">
                        <p className="text-sm leading-relaxed text-white/85">{t('sponsorship_page.mech_p1')}</p>
                        <p className="text-sm leading-relaxed text-white/85">{t('sponsorship_page.mech_p2')}</p>
                        <p className="text-sm leading-relaxed text-white/85">{t('sponsorship_page.mech_p3')}</p>
                        <p className="mt-4 rounded-[12px] border-l-[3px] border-l-brand-green bg-white/[0.04] p-4 text-sm leading-relaxed text-white/85 italic">
                            {t('sponsorship_page.mech_endorse')}
                        </p>
                    </Reveal>

                    {/* Dreapta — cele 3 acte normative care fundamentează Mecanismul 2% */}
                    <div className="flex flex-col gap-3">
                        <div className="mb-1 flex items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('sponsorship_page.legal_eyebrow', 'Acte normative')}</span>
                            <span className="h-px flex-1 bg-brand-green/30" aria-hidden="true" />
                        </div>
                        {LEGAL_ACTS.map((act) => (
                            <Reveal as="article" key={act.number} className="flex items-start gap-3 rounded-[14px] border border-white/15 bg-white/[0.05] p-4 transition-colors hover:border-brand-green/40 hover:bg-white/[0.08]">
                                <span className="grid size-10 shrink-0 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green ring-1 ring-brand-green/30">
                                    <act.Icon className="size-5" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <span className="block text-[0.65rem] font-bold tracking-wider text-brand-green uppercase">
                                        {t(act.type)}
                                    </span>
                                    <div className="mt-0.5 flex flex-wrap items-baseline gap-x-2">
                                        <span className="display text-[1.125rem] font-bold text-[color:var(--brand-navy-foreground)]">{t(act.number)}</span>
                                        <span className="text-xs text-white/70">· {t(act.date)}</span>
                                    </div>
                                    <p className="mt-1 text-xs leading-relaxed text-white/75">{t(act.body)}</p>
                                </div>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </Band>

            {/* Bandă ALBĂ — 3 pași concreți + link-uri externe */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="02"
                    label={t('sponsorship_page.steps_eyebrow', 'Pași concreți')}
                    title={t('sponsorship_page.steps_title', 'Trei pași simpli pentru a direcționa 2%')}
                    lead={t('sponsorship_page.steps_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 md:grid-cols-3 md:gap-6">
                    {STEPS.map((s, i) => (
                        <Reveal as="article" key={s.title} className="flex flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-green bg-card p-6 sm:p-7">
                            <div className="flex items-center gap-3">
                                <span className="grid size-11 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                    <s.Icon className="size-5" aria-hidden="true" />
                                </span>
                                <span className="font-display text-2xl font-bold text-brand-green/60 tabular-nums">{String(i + 1).padStart(2, '0')}</span>
                            </div>
                            <h3 className="display mt-4 text-[1.125rem] leading-snug text-brand-navy">{t(s.title)}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-brand-dark/85">{t(s.body)}</p>
                        </Reveal>
                    ))}
                </div>

                {/* Subsecțiune — link-uri externe */}
                <div className="mt-14">
                    <SectionHeader
                        index="✦"
                        label={t('sponsorship_page.links_eyebrow', 'Resurse oficiale')}
                        title={t('sponsorship_page.links_title', 'Pagini de referință pentru mecanismul 2%')}
                        lead={t('sponsorship_page.links_lead')}
                        className="mb-7"
                    />
                    <div className="grid gap-3 md:grid-cols-2">
                        {LINKS.map((l) => (
                            <Reveal as="article" key={l.label}>
                                <a
                                    href={t(l.url)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-start gap-3 rounded-[12px] border keyline bg-card p-4 transition-colors hover:border-brand-green/40 hover:bg-brand-green/5"
                                >
                                    <span className="grid size-9 shrink-0 place-items-center rounded-[8px] bg-brand-green/15 text-brand-green">
                                        <ExternalLink className="size-4" aria-hidden="true" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-semibold text-brand-navy">{t(l.label)}</span>
                                        <span className="mt-0.5 block truncate text-xs text-brand-gray">{t(l.url).replace(/^https?:\/\//, '')}</span>
                                    </span>
                                </a>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </Band>

            {/* Bandă NAVY — Donații directe (verbatim) + downloads */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader
                    index="03"
                    variant="navy"
                    label={t('sponsorship_page.direct_eyebrow', 'Donații directe')}
                    title={t('sponsorship_page.direct_title', 'Sponsorizare și finanțare')}
                    lead={t('sponsorship_page.direct_lead')}
                    className="mb-10"
                />
                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:gap-10">
                    <Reveal className="space-y-4">
                        <p className="leading-relaxed text-white/85">{t('sponsorship_page.direct_p1')}</p>
                        <p className="text-sm leading-relaxed text-white/75 italic">{t('sponsorship_page.direct_p2')}</p>
                    </Reveal>

                    {/* Documente — PDF-uri descărcabile (preluate verbatim de pe site-ul vechi) */}
                    <div className="flex flex-col gap-3">
                        <span className="eyebrow text-brand-green">{t('sponsorship_page.downloads_eyebrow', 'Documente')}</span>
                        <Reveal>
                            <a
                                href="/downloads/sponsorizare/formular-CET18.pdf"
                                target="_blank"
                                rel="noopener noreferrer"
                                download
                                className="group flex items-start gap-3 rounded-[14px] border border-white/15 bg-white/[0.04] p-5 transition-colors hover:border-brand-green/40 hover:bg-brand-green/[0.06]"
                            >
                                <span className="grid size-10 shrink-0 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                    <ScrollText className="size-5" aria-hidden="true" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block font-semibold text-[color:var(--brand-navy-foreground)]">{t('sponsorship_page.doc_cet18_label', 'Formularul CET18')}</span>
                                    <span className="mt-1 block text-xs text-white/70">{t('sponsorship_page.doc_cet18_note', 'PDF · 519 KB · Descarcă')}</span>
                                </span>
                                <Download className="mt-1 size-4 shrink-0 text-brand-green opacity-60 transition-opacity group-hover:opacity-100" aria-hidden="true" />
                            </a>
                        </Reveal>
                        <Reveal>
                            <a
                                href="/downloads/sponsorizare/contract-sponsorizare.pdf"
                                target="_blank"
                                rel="noopener noreferrer"
                                download
                                className="group flex items-start gap-3 rounded-[14px] border border-white/15 bg-white/[0.04] p-5 transition-colors hover:border-brand-green/40 hover:bg-brand-green/[0.06]"
                            >
                                <span className="grid size-10 shrink-0 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                    <FileText className="size-5" aria-hidden="true" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block font-semibold text-[color:var(--brand-navy-foreground)]">{t('sponsorship_page.doc_contract_label', 'Contractul de sponsorizare')}</span>
                                    <span className="mt-1 block text-xs text-white/70">{t('sponsorship_page.doc_contract_note', 'PDF · 274 KB · Descarcă')}</span>
                                </span>
                                <Download className="mt-1 size-4 shrink-0 text-brand-green opacity-60 transition-opacity group-hover:opacity-100" aria-hidden="true" />
                            </a>
                        </Reveal>
                    </div>
                </div>
            </Band>

            {/* Bandă ALBĂ — CTA */}
            <Band variant="light" pattern="signature">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="04"
                        align="center"
                        label={t('sponsorship_page.cta_eyebrow', 'Mulțumim pentru sprijin')}
                        title={t('sponsorship_page.cta_title', 'Întrebări despre cum să susții Liceul?')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('sponsorship_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('sponsorship_page.cta_primary', 'Contacte')}
                        </BrandButton>
                        <a
                            href="https://2procente.info/ro/"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center justify-center gap-2 rounded-[10px] border border-brand-navy/20 px-5 py-2.5 text-sm font-semibold text-brand-navy transition-colors hover:bg-brand-navy/5"
                        >
                            <ArrowRight className="size-4" aria-hidden="true" />
                            {t('sponsorship_page.cta_secondary', 'Vezi 2procente.info')}
                        </a>
                    </div>
                    {/* Decorative — keep refs tree-shake-safe */}
                    <div className="mt-10 flex justify-center gap-6 text-brand-gray/40">
                        <Heart className="size-4" aria-hidden="true" />
                        <Coins className="size-4" aria-hidden="true" />
                        <Wallet className="size-4" aria-hidden="true" />
                    </div>
                </div>
            </Band>
        </>
    );
}
