import { Head } from '@inertiajs/react';
import { ArrowRight, ExternalLink, ShieldCheck } from 'lucide-react';
import { Band, BrandButton, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

export default function Acreditari() {
    const t = useTranslations();

    const certificates = [
        {
            src: '/images/acreditari/certificat-acreditare.jpg',
            title: t('accreditation.acc_title', 'Certificat de acreditare'),
            issuer: t('accreditation.acc_issuer'),
            alt: t('accreditation.acc_alt'),
            facts: [
                { label: t('accreditation.f_rating', 'Calificativ'), value: t('accreditation.acc_rating', 'Foarte bine') },
                { label: t('accreditation.f_series', 'Seria și numărul'), value: 'Seria ÎG Nr. 003-20' },
                { label: t('accreditation.f_validity', 'Valabilitate'), value: t('accreditation.acc_validity', '15 iunie 2020 – 28 februarie 2025') },
            ],
        },
        {
            src: '/images/acreditari/certificat-inregistrare.jpg',
            title: t('accreditation.reg_title', 'Certificat de înregistrare'),
            issuer: t('accreditation.reg_issuer'),
            alt: t('accreditation.reg_alt'),
            facts: [
                { label: t('accreditation.f_idno', 'Cod fiscal (IDNO)'), value: '1004600000818' },
                { label: t('accreditation.f_regdate', 'Data înregistrării'), value: t('accreditation.reg_regdate', '28 iunie 2005') },
                { label: t('accreditation.f_series', 'Seria și numărul'), value: 'MD 036448' },
            ],
        },
    ];

    return (
        <>
            <Head title={t('accreditation.meta_title', 'Acreditări — Liceul Columna')}>
                <meta name="description" content={t('accreditation.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark */}
            <PageBanner
                title={t('accreditation.title', 'Acreditări')}
                breadcrumbs={[
                    { title: t('accreditation.breadcrumb_section', 'Despre liceu') },
                    { title: t('accreditation.breadcrumb_self', 'Acreditări') },
                ]}
                description={t('accreditation.lead')}
            />

            {/* Secțiune ALBASTRĂ — certificatele reale, ca foi pe navy */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    label={t('accreditation.eyebrow', 'Documente oficiale')}
                    title={t('accreditation.section_title', 'Certificatele instituției')}
                    className="mb-8"
                />
                <div className="grid gap-6 md:grid-cols-2 lg:gap-8">
                    {certificates.map((c) => (
                        <Reveal as="article" key={c.src} className="flex flex-col overflow-hidden rounded-[18px] border keyline bg-card shadow-[0_30px_70px_-45px_rgba(0,0,0,0.5)]">
                            {/* Imaginea documentului — afișată (ne-clickabilă); deschiderea originalului doar prin linkul de jos */}
                            <div className="border-b keyline bg-brand-navy/[0.03] p-4 sm:p-6">
                                <img
                                    src={c.src}
                                    alt={c.alt}
                                    loading="lazy"
                                    decoding="async"
                                    width={874}
                                    height={1268}
                                    className="mx-auto block max-h-[28rem] w-auto rounded-[8px] border keyline shadow-sm"
                                />
                            </div>
                            {/* Detalii — emitent + fapte verificabile (din document) */}
                            <div className="flex flex-1 flex-col p-6 sm:p-7">
                                <h3 className="display text-xl text-brand-navy">{c.title}</h3>
                                <p className="mt-1 text-sm leading-snug text-brand-gray">{c.issuer}</p>
                                <dl className="mt-5 space-y-2.5 border-t keyline pt-5">
                                    {c.facts.map((f) => (
                                        <div key={f.label} className="flex items-baseline justify-between gap-4 text-sm">
                                            <dt className="shrink-0 text-brand-gray">{f.label}</dt>
                                            <dd className="text-right font-semibold text-brand-navy">{f.value}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <span className="mt-6 inline-block">
                                    <BrandButton href={c.src} external variant="link" icon={ExternalLink}>
                                        {t('accreditation.view', 'Deschide documentul')}
                                    </BrandButton>
                                </span>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA: cum monitorizăm calitatea (CEI) */}
            <Band variant="light">
                <div className="mx-auto max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('accreditation.cta_eyebrow', 'Calitatea, verificată din afară')}
                        title={t('accreditation.cta_title', 'Cum monitorizăm calitatea')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('accreditation.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/centrul-de-evaluare-institutionala" variant="primary" icon={ShieldCheck} className="w-full justify-center sm:w-auto">
                            {t('accreditation.cta_primary', 'Despre evaluarea calității')}
                        </BrandButton>
                        <BrandButton href="/de-ce-columna" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('accreditation.cta_secondary', 'De ce Columna')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
