import { Head } from '@inertiajs/react';
import { ArrowRight, GraduationCap } from 'lucide-react';
import { Band, BrandButton, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const BODY_KEYS = ['letter.p1', 'letter.p2', 'letter.p3', 'letter.p4', 'letter.p5', 'letter.p6'] as const;

export default function ScrisoareaDirectorului() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('letter.meta_title', 'Scrisoarea directorului — Liceul Columna')}>
                <meta name="description" content={t('letter.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal ALB + crest watermark (ca /admitere) */}
            <PageBanner
                title={t('letter.title', 'Scrisoarea directorului')}
                breadcrumbs={[
                    { title: t('letter.breadcrumb_section', 'Despre liceu') },
                    { title: t('letter.breadcrumb_self', 'Scrisoarea directorului') },
                ]}
                description={t('letter.byline')}
            />

            {/* Secțiune ALBASTRĂ — scrisoarea: rail-sigiliu sticky (emblema rotativă) + foaia de manuscris (sheet pe navy) */}
            <Band variant="navy" pattern="dotgrid">
                <div className="grid items-start gap-8 lg:grid-cols-[18rem_1fr] lg:gap-12">
                    {/* Rail-sigiliu — sticky pe lg: card translucid pe navy, cu emblema rotativă */}
                    <Reveal className="lg:sticky lg:top-28 lg:self-start">
                        <div className="relative overflow-hidden rounded-[18px] border border-white/15 bg-white/[0.05] p-6 text-center sm:p-8">
                            <span data-rule className="absolute inset-x-0 top-0 h-1 origin-left bg-brand-green" aria-hidden="true" />
                            {/* Portretul directorului + textul aferent (nume + funcție) */}
                            <figure className="relative mx-auto aspect-[4/5] w-full overflow-hidden rounded-[14px] border-2 border-brand-green/50 shadow-[0_24px_60px_-30px_rgba(0,0,0,0.6)]">
                                <img
                                    src="/images/profesori/danita-ghenadie.jpg"
                                    alt={t('letter.portrait_alt', 'Portretul lui Daniță Ghenadie, directorul Liceului Columna')}
                                    loading="lazy"
                                    decoding="async"
                                    width={900}
                                    height={600}
                                    className="absolute inset-0 h-full w-full object-cover"
                                    style={{ objectPosition: 'center 25%' }}
                                />
                            </figure>
                            <span className="display mt-5 block text-xl text-[color:var(--brand-navy-foreground)]">{t('letter.author_name', 'Daniță Ghenadie')}</span>
                            <span className="mt-1 block text-sm text-white/75">{t('letter.author_role', 'Director · grad managerial superior')}</span>
                            <div className="mt-5 border-t border-white/15 pt-5">
                                <p className="display text-[clamp(1.0625rem,1.5vw,1.25rem)] text-balance text-[color:var(--brand-navy-foreground)] italic">
                                    {t('letter.slogan', 'Succesul copilului începe aici.')}
                                </p>
                            </div>
                        </div>
                    </Reveal>

                    {/* Scrisoarea — foaie de manuscris (sheet alb pe navy) cu drop cap */}
                    <Reveal
                        as="article"
                        className="relative overflow-hidden rounded-[18px] border keyline bg-card p-7 shadow-[0_40px_100px_-45px_rgba(0,0,0,0.55)] sm:p-10 lg:p-14"
                    >
                        <span
                            className="display pointer-events-none absolute top-3 right-6 text-[clamp(5rem,10vw,8rem)] leading-none text-brand-green/10 select-none"
                            aria-hidden="true"
                        >
                            „
                        </span>

                        {/* Salutație */}
                        <h2 className="display relative text-[clamp(1.5rem,2.8vw,2.125rem)] text-balance text-brand-navy">
                            {t('letter.salutation', 'Dragi elevi, stimați profesori și părinți!')}
                        </h2>
                        <span data-rule className="mt-4 block h-1 w-16 origin-left rounded-full bg-brand-green" aria-hidden="true" />

                        {/* Paragrafele — primul cu drop cap (literă inițială Cervino) */}
                        <div className="mt-8 space-y-6">
                            {BODY_KEYS.map((key, i) => (
                                <p
                                    key={key}
                                    className={cn(
                                        'text-[clamp(1.0625rem,1.55vw,1.1875rem)] leading-relaxed text-brand-dark/85',
                                        i === 0 &&
                                            'first-letter:float-left first-letter:mt-1 first-letter:mr-3 first-letter:text-[clamp(3rem,6vw,4.25rem)] first-letter:leading-[0.7] first-letter:font-bold first-letter:text-brand-navy first-letter:[font-family:var(--font-display)]',
                                    )}
                                >
                                    {t(key)}
                                </p>
                            ))}
                        </div>

                        {/* Final flourish — „Vă așteptăm cu drag!" */}
                        <div className="mt-12 flex items-center gap-4">
                            <FourStar className="size-3 shrink-0 text-brand-green" />
                            <p className="display text-[clamp(1.375rem,2.6vw,1.875rem)] text-balance text-brand-navy italic">
                                {t('letter.closing', 'Vă așteptăm cu drag!')}
                            </p>
                        </div>

                        {/* Semnătura — sign-off de scrisoare, aliniat dreapta */}
                        <div className="mt-10 flex justify-end border-t keyline pt-6">
                            <div className="text-right">
                                <span className="display block text-xl text-brand-navy">{t('letter.author_name', 'Daniță Ghenadie')}</span>
                                <span className="eyebrow text-brand-gray">{t('letter.author_role', 'Director · grad managerial superior')}</span>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </Band>

            {/* Secțiune ALBĂ — CTA „pasul următor" */}
            <Band variant="light">
                <div className="mx-auto max-w-2xl text-center">
                    <SectionHeader
                        index="✦"
                        align="center"
                        label={t('letter.cta_eyebrow', 'Pasul următor')}
                        title={t('letter.cta_title', 'Continuă să ne cunoști.')}
                    />
                    <p className="mt-4 leading-relaxed text-brand-gray">{t('letter.cta_lead')}</p>
                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <BrandButton href="/de-ce-columna" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                            {t('letter.cta_primary', 'De ce Columna')}
                        </BrandButton>
                        <BrandButton href="/contacte" variant="ghost" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                            {t('letter.cta_secondary', 'Programează o vizită')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
