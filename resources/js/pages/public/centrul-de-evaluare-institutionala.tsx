import { Head } from '@inertiajs/react';
import { ArrowRight, Check, FileText, LayoutDashboard } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

const FRAMEWORK = [
    { title: 'cei.obj_title', intro: 'cei.obj_intro', items: ['cei.obj_1', 'cei.obj_2', 'cei.obj_3', 'cei.obj_4', 'cei.obj_5'] },
    { title: 'cei.dom_title', intro: null, items: ['cei.dom_1', 'cei.dom_2', 'cei.dom_3', 'cei.dom_4', 'cei.dom_5'] },
    { title: 'cei.attr_title', intro: null, items: ['cei.attr_1', 'cei.attr_2', 'cei.attr_3', 'cei.attr_4'] },
    { title: 'cei.req_title', intro: null, items: ['cei.req_1', 'cei.req_2', 'cei.req_3', 'cei.req_4', 'cei.req_5', 'cei.req_6', 'cei.req_7'] },
] as const;

const DOCS = ['cei.doc1_title', 'cei.doc2_title'] as const;
const BENEFICIARIES = ['cei.ben_p1', 'cei.ben_p2', 'cei.ben_p3'] as const;

export default function CentrulDeEvaluareInstitutionala() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('cei.meta_title', 'Centrul de Evaluare Instituțională — Liceul Columna')}>
                <meta name="description" content={t('cei.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('cei.title', 'Centrul de Evaluare Instituțională')}
                breadcrumbs={[{ title: t('cei.breadcrumb_self', 'CEI') }]}
                description={t('cei.lead')}
            />

            {/* Secțiune ALBASTRĂ — motto-ul latin + misiunea (inscripție ceremonială) */}
            <Band variant="navy" pattern="dotgrid">
                <Reveal className="mx-auto max-w-3xl text-center">
                    <FourStar className="mx-auto size-5 text-brand-green" />
                    <p className="display mt-5 text-[clamp(1.5rem,4vw,2.75rem)] tracking-wide text-[color:var(--brand-navy-foreground)]">
                        {t('cei.motto', 'NON SCHOLAE, SED VITAE DISCIMUS')}
                    </p>
                    <p className="mt-3 text-sm text-white/70 italic">{t('cei.motto_meaning')}</p>
                    <span data-rule className="mx-auto mt-6 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                    <p className="mx-auto mt-6 max-w-[62ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/85">{t('cei.mission')}</p>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — sistemul SIERȘ: 4 carduri-listă (obiective / domenii / atribuții / cerințe) */}
            <Band variant="light">
                <SectionHeader
                    index="✦"
                    label={t('cei.framework_eyebrow', 'Cum evaluăm')}
                    title={t('cei.framework_title', 'Sistemul instituțional de evaluare — SIERȘ')}
                    lead={t('cei.framework_lead')}
                    className="mb-10"
                />
                <div className="grid gap-6 md:grid-cols-2">
                    {FRAMEWORK.map((card) => (
                        <Reveal as="article" key={card.title} className="flex flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-navy bg-card p-6 sm:p-7">
                            <h3 className="display text-xl text-brand-navy">{t(card.title)}</h3>
                            {card.intro && <p className="mt-2 text-sm leading-relaxed text-brand-gray">{t(card.intro)}</p>}
                            <ul className="mt-4 space-y-2.5">
                                {card.items.map((it) => (
                                    <li key={it} className="flex gap-2.5 text-[0.95rem] leading-relaxed text-brand-dark/85">
                                        <Check className="mt-0.5 size-4 shrink-0 text-brand-green" aria-hidden="true" />
                                        <span>{t(it)}</span>
                                    </li>
                                ))}
                            </ul>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBASTRĂ — documentele reglatoare SIERȘ */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('cei.docs_eyebrow', 'Cadrul metodologic')}
                    title={t('cei.docs_title', 'Documente reglatoare SIERȘ')}
                    lead={t('cei.docs_intro')}
                    className="mb-8"
                />
                <div className="grid gap-5 md:grid-cols-2">
                    {DOCS.map((doc) => (
                        <Reveal as="article" key={doc} className="flex items-start gap-4 rounded-[16px] border border-white/15 bg-white/[0.05] p-6">
                            <span className="grid size-12 shrink-0 place-items-center rounded-[12px] bg-brand-green/15 text-brand-green">
                                <FileText className="size-6" />
                            </span>
                            <div>
                                <span className="eyebrow text-brand-green">{t('cei.doc_label', 'Document instituțional')}</span>
                                <h3 className="mt-1 text-[1.125rem] leading-snug font-semibold text-[color:var(--brand-navy-foreground)]">{t(doc)}</h3>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — informarea beneficiarilor + acces la cabinet */}
            <Band variant="light">
                <div className="mx-auto max-w-3xl">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('cei.ben_eyebrow', 'Transparență')}
                        title={t('cei.ben_title', 'Informarea beneficiarilor')}
                        className="mb-8"
                    />
                    <div className="space-y-5 text-[clamp(1.125rem,1.5vw,1.25rem)] leading-relaxed text-brand-dark/85">
                        {BENEFICIARIES.map((p) => (
                            <p key={p}>{t(p)}</p>
                        ))}
                    </div>
                    <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/dashboard" variant="primary" icon={LayoutDashboard} className="w-full justify-center sm:w-auto">
                            {t('cei.cta_primary', 'Cabinetul online')}
                        </BrandButton>
                        <BrandButton href="/acreditari" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('cei.cta_secondary', 'Acreditări')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
