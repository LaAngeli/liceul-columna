import { Head } from '@inertiajs/react';
import { BookOpenCheck, ClipboardCheck, GraduationCap, Handshake, LayoutGrid, Mail, MonitorSmartphone, Percent, Phone, Sparkles, Wallet } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

/**
 * Pagina /taxe — bespoke „Columna Civic Editorial".
 * Pagină ONESTĂ: NU publicăm sume (liceul comunică taxele individual la înscriere).
 * Conținut verbatim din site-ul vechi + reduceri/plată preluate din FAQ-ul /admitere.
 */

const INCLUDES = [
    { Icon: BookOpenCheck, title: 'tuition_page.i1_title', body: 'tuition_page.i1_body' },
    { Icon: LayoutGrid, title: 'tuition_page.i2_title', body: 'tuition_page.i2_body' },
    { Icon: Sparkles, title: 'tuition_page.i3_title', body: 'tuition_page.i3_body' },
    { Icon: MonitorSmartphone, title: 'tuition_page.i4_title', body: 'tuition_page.i4_body' },
] as const;

const BONUSES = [
    { Icon: Percent, title: 'tuition_page.bonus_discount_title', body: 'tuition_page.bonus_discount_body' },
    { Icon: Wallet, title: 'tuition_page.bonus_payment_title', body: 'tuition_page.bonus_payment_body' },
    { Icon: ClipboardCheck, title: 'tuition_page.bonus_no_fee_title', body: 'tuition_page.bonus_no_fee_body' },
] as const;

export default function Taxe() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('tuition_page.meta_title', 'Taxe și costuri — Liceul Columna')}>
                <meta name="description" content={t('tuition_page.meta_description')} />
            </Head>

            {/* HERO (ALB) */}
            <PageBanner
                title={t('tuition_page.title', 'Taxe și costuri')}
                breadcrumbs={[{ title: t('tuition_page.breadcrumb_self', 'Taxe și costuri') }]}
                description={t('tuition_page.lead')}
            />

            {/* Bandă NAVY — cadrul + notă de onestitate (NU publicăm cifre) */}
            <Band variant="navy" pattern="dotgrid">
                <Reveal className="mx-auto max-w-3xl">
                    <SectionHeader
                        index="01"
                        variant="navy"
                        align="center"
                        label={t('tuition_page.intro_eyebrow', 'Cadru')}
                        title={t('tuition_page.intro_title', 'O singură taxă, comunicată la încheierea contractului')}
                        className="mb-6"
                    />
                    <p className="text-center leading-relaxed text-white/90">{t('tuition_page.intro_p1')}</p>
                    <p className="mt-3 text-center text-sm leading-relaxed text-white/75 italic">{t('tuition_page.intro_p2')}</p>
                </Reveal>
            </Band>

            {/* Bandă ALBĂ — ce include taxa (4 carduri cu iconi) */}
            <Band variant="light">
                <SectionHeader
                    index="02"
                    label={t('tuition_page.includes_eyebrow', 'Ce include taxa')}
                    title={t('tuition_page.includes_title', 'Ce primește copilul pentru taxa de studii')}
                    lead={t('tuition_page.includes_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                    {INCLUDES.map((item) => (
                        <Reveal as="article" key={item.title} className="flex flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-green bg-card p-6">
                            <span className="grid size-11 place-items-center rounded-[10px] bg-brand-green/15 text-brand-green">
                                <item.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <h3 className="display mt-4 text-[1.125rem] leading-snug text-brand-navy">{t(item.title)}</h3>
                            <p className="mt-2 text-sm leading-relaxed text-brand-dark/85">{t(item.body)}</p>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Bandă NAVY — cum afli taxa (2 pași + telefon) */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="03"
                    variant="navy"
                    label={t('tuition_page.how_eyebrow', 'Cum afli taxa actuală')}
                    title={t('tuition_page.how_title', 'Doi pași simpli pentru grila oficială')}
                    lead={t('tuition_page.how_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 md:grid-cols-2 md:gap-6">
                    {/* Pas 1 — secretariat */}
                    <Reveal as="article" className="flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-6">
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-9 place-items-center rounded-full bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                <Phone className="size-4" aria-hidden="true" />
                            </span>
                            <span className="eyebrow text-brand-green">{t('admission_page.stage_1_label', 'Etapa 1')}</span>
                        </div>
                        <h3 className="display mt-3 text-[clamp(1.05rem,1.5vw,1.25rem)] leading-snug text-[color:var(--brand-navy-foreground)]">
                            {t('tuition_page.how_step_1_title', 'Contactează secretariatul')}
                        </h3>
                        <span data-rule className="mt-3 block h-0.5 w-12 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-3 text-sm leading-relaxed text-white/85">{t('tuition_page.how_step_1_body')}</p>

                        {/* Programare telefonică */}
                        <div className="mt-5 flex items-start gap-3 rounded-[12px] border border-brand-green/40 bg-brand-green/[0.08] p-4">
                            <span className="grid size-9 shrink-0 place-items-center rounded-[10px] bg-brand-green/20 text-brand-green">
                                <Phone className="size-4" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <span className="eyebrow text-[0.65rem] text-brand-green">{t('tuition_page.phone_label', 'Programare la secretariat')}</span>
                                <a
                                    href="tel:+37322742852"
                                    className="display mt-1 block text-[clamp(1.125rem,1.7vw,1.25rem)] font-bold text-[color:var(--brand-navy-foreground)] underline-offset-4 transition-colors hover:underline"
                                >
                                    {t('tuition_page.phone_value', '(022) 74 28 52')}
                                </a>
                                <p className="mt-0.5 text-xs text-white/70">{t('tuition_page.phone_note')}</p>
                            </div>
                        </div>
                    </Reveal>

                    {/* Pas 2 — director */}
                    <Reveal as="article" className="flex flex-col rounded-[16px] border border-white/15 bg-white/[0.05] p-6">
                        <div className="flex items-center gap-2.5">
                            <span className="grid size-9 place-items-center rounded-full bg-brand-green/20 text-brand-green ring-1 ring-brand-green/40">
                                <Handshake className="size-4" aria-hidden="true" />
                            </span>
                            <span className="eyebrow text-brand-green">{t('admission_page.stage_2_label', 'Etapa 2')}</span>
                        </div>
                        <h3 className="display mt-3 text-[clamp(1.05rem,1.5vw,1.25rem)] leading-snug text-[color:var(--brand-navy-foreground)]">
                            {t('tuition_page.how_step_2_title', 'Discută cu Dl Director')}
                        </h3>
                        <span data-rule className="mt-3 block h-0.5 w-12 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-3 text-sm leading-relaxed text-white/85">{t('tuition_page.how_step_2_body')}</p>
                        <div className="mt-5">
                            <BrandButton href="/admitere" variant="ghost-navy" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                                {t('tuition_page.cta_secondary', 'Admitere')}
                            </BrandButton>
                        </div>
                    </Reveal>
                </div>
            </Band>

            {/* Bandă ALBĂ — 3 principii (reducere / plată / fără înmatriculare) */}
            <Band variant="light">
                <SectionHeader
                    index="04"
                    label={t('tuition_page.bonus_eyebrow', 'Pentru familii')}
                    title={t('tuition_page.bonus_title', 'Reducere și flexibilitate la plată')}
                    lead={t('tuition_page.bonus_lead')}
                    className="mb-10"
                />
                <div className="grid gap-5 md:grid-cols-3 md:gap-6">
                    {BONUSES.map((b) => (
                        <Reveal as="article" key={b.title} className="flex flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-green bg-card p-6 sm:p-7">
                            <span className="grid size-12 place-items-center rounded-[12px] bg-brand-green/15 text-brand-green ring-1 ring-brand-green/30">
                                <b.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <h3 className="display mt-4 text-[clamp(1.05rem,1.6vw,1.25rem)] leading-snug text-brand-navy">{t(b.title)}</h3>
                            <p className="mt-3 leading-relaxed text-brand-dark/85">{t(b.body)}</p>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Bandă ALBĂ-LIGHT (CTA) — secretariat */}
            <Band variant="light">
                <div className="mx-auto max-w-2xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <SectionHeader
                        index="05"
                        align="center"
                        label={t('tuition_page.cta_eyebrow', 'Ai întrebări despre taxe?')}
                        title={t('tuition_page.cta_title', 'Contactează secretariatul pentru grila actuală')}
                        className="mt-3"
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('tuition_page.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/contacte" variant="primary" icon={Mail} className="w-full justify-center sm:w-auto">
                            {t('tuition_page.cta_primary', 'Contacte')}
                        </BrandButton>
                        <BrandButton href="/admitere" variant="ghost" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                            {t('tuition_page.cta_secondary', 'Admitere')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
