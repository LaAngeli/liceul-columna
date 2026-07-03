import { Head } from '@inertiajs/react';
import { BookOpen, Clock, GraduationCap, Users } from 'lucide-react';
import { Band, Display, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

const PILLARS = [
    { key: 'students', Icon: GraduationCap },
    { key: 'parents', Icon: Users },
    { key: 'teachers', Icon: BookOpen },
];

export default function ConsiliulScolar() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('school_council.meta_title', 'Consiliul școlar — Liceul Columna')}>
                <meta name="description" content={t('school_council.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal alb + crest watermark */}
            <PageBanner
                title={t('school_council.title', 'Consiliul școlar')}
                breadcrumbs={[{ title: t('school_council.breadcrumb_self', 'Consiliul școlar') }]}
                description={t('school_council.banner_lead')}
            />

            {/* Secțiune ALBASTRĂ — despre consiliu + foto comunitate */}
            <Band variant="navy" pattern="dotgrid">
                <div className="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">
                    <Reveal>
                        <div className="flex items-center gap-3">
                            <span className="eyebrow text-brand-green">{t('school_council.intro_eyebrow', 'Despre consiliu')}</span>
                            <FourStar className="size-3 text-brand-green" />
                        </div>
                        <Display as="h2" className="mt-3 text-[clamp(1.5rem,3.2vw,2.375rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                            {t('school_council.intro_title', 'Trei voci, o singură comunitate')}
                        </Display>
                        <span data-rule className="mt-5 block h-1 w-20 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="mt-5 max-w-[56ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/85">
                            {t('school_council.intro_lead')}
                        </p>
                    </Reveal>
                    <Reveal>
                        <figure className="relative aspect-[4/3] overflow-hidden rounded-[16px] border border-white/15">
                            <img
                                src="/images/galerie/general/g1.jpg"
                                alt={t('school_council.photo_alt', 'Elevi ai Liceului Columna pe gazonul campusului din Chișinău')}
                                loading="lazy"
                                decoding="async"
                                width={900}
                                height={600}
                                className="absolute inset-0 h-full w-full object-cover"
                                style={{ objectPosition: 'center 45%' }}
                            />
                        </figure>
                    </Reveal>
                </div>
            </Band>

            {/* Secțiune ALBĂ — cele trei voci + notiță componență */}
            <Band variant="light">
                <SectionHeader
                    index="✦"
                    label={t('school_council.voices_eyebrow', 'Cine îl formează')}
                    title={t('school_council.voices_title', 'Cele trei voci ale consiliului')}
                    className="mb-8"
                />
                <div className="grid gap-5 sm:grid-cols-3">
                    {PILLARS.map(({ key, Icon }) => (
                        <Reveal as="div" key={key} className="h-full">
                            <div className="flex h-full flex-col rounded-[16px] border keyline border-l-[5px] border-l-brand-navy bg-card p-6 transition-all hover:border-l-brand-green hover:shadow-[0_16px_36px_-26px_rgba(15,77,119,0.5)]">
                                <span className="flex size-12 items-center justify-center rounded-full bg-brand-navy/8 text-brand-navy">
                                    <Icon className="size-6" />
                                </span>
                                <h3 className="display mt-4 text-xl text-brand-navy">{t(`school_council.pillar_${key}_title`)}</h3>
                                <p className="mt-2 leading-relaxed text-brand-gray">{t(`school_council.pillar_${key}_text`)}</p>
                            </div>
                        </Reveal>
                    ))}
                </div>

                {/* Notiță — componența în curând */}
                <Reveal className="mt-10">
                    <div className="flex items-start gap-3 rounded-[14px] border border-dashed keyline border-l-[5px] border-l-brand-green bg-card p-5 sm:p-6">
                        <Clock className="mt-0.5 size-5 shrink-0 text-brand-green" />
                        <div>
                            <span className="display block text-brand-navy">{t('school_council.composition_title', 'Componența nominală — în curând')}</span>
                            <p className="mt-1 text-sm leading-relaxed text-brand-gray">{t('school_council.composition_note')}</p>
                        </div>
                    </div>
                </Reveal>
            </Band>
        </>
    );
}
