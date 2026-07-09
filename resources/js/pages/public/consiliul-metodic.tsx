import { Head } from '@inertiajs/react';
import { LocaleLink } from '@/components/locale-link';
import { Band, Display, FourStar, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Tr = (k: string, f?: string) => string;

const MEMBERS = [
    { name: 'Pascaru Irina', photo: 'pascaru-irina', slug: 'pascaru-irina', fn: 'president', role: 'm1' },
    { name: 'Demerji Sergiu', photo: 'demerji-sergiu', slug: 'demerji-sergiu', fn: 'secretary', role: 'm2' },
    { name: 'Buga Alina', photo: 'buga-alina', slug: 'buga-alina', fn: 'member', role: 'm3' },
    { name: 'Bujor-Cobili Carolina', photo: 'bujor-cobili-carolina', slug: 'bujor-cobili-carolina', fn: 'member', role: 'm4' },
    { name: 'Ciocoi Aliona', photo: 'barbacaru-aliona', slug: 'ciocoi-aliona', fn: 'member', role: 'm5' },
    { name: 'Cociug Silvia', photo: 'cociug-silvia', slug: 'cociug-silvia', fn: 'member', role: 'm6' },
    { name: 'Colesnic Liliana', photo: 'colesnic-liliana', slug: 'colesnic-liliana', fn: 'member', role: 'm7' },
];

const ATTRIB = ['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9'];

function FnBadge({ t, fn, className }: { t: Tr; fn: string; className?: string }) {
    const lead = fn === 'president' || fn === 'secretary';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide uppercase',
                lead ? 'bg-brand-green text-[color:var(--brand-green-foreground)]' : 'bg-brand-navy/8 text-brand-navy',
                className,
            )}
        >
            {t(`council.fn_${fn}`)}
        </span>
    );
}

export default function ConsiliulMetodic() {
    const t = useTranslations();
    const president = MEMBERS[0];
    const rest = MEMBERS.slice(1);

    return (
        <>
            <Head title={t('council.meta_title', 'Consiliul Metodic — Liceul Columna')}>
                <meta name="description" content={t('council.meta_description')} />
            </Head>

            {/* HERO — standardizat (PageBanner): fundal alb + crest watermark */}
            <PageBanner
                title={t('council.title', 'Consiliul Metodic')}
                breadcrumbs={[{ title: t('council.breadcrumb_self', 'Consiliul Metodic') }]}
                description={t('council.banner_lead')}
            />

            {/* Secțiune ALBASTRĂ — rolul & scopul consiliului */}
            <Band variant="navy" pattern="mesh">
                <Reveal className="flex items-center justify-center gap-3">
                    <span data-rule className="h-px w-12 origin-right bg-white/30" aria-hidden="true" />
                    <span className="eyebrow text-brand-green">{t('council.intro_eyebrow', 'Rolul consiliului')}</span>
                    <FourStar className="size-3 text-brand-green" />
                </Reveal>
                <Reveal className="mx-auto mt-6 max-w-2xl space-y-5">
                    <p className="text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/85">{t('council.intro_p1')}</p>
                    <p className="text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/85">{t('council.intro_p2')}</p>
                    <p className="text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed text-white/85">{t('council.intro_p3')}</p>
                </Reveal>
                <Reveal className="mt-8 flex justify-center">
                    <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/[0.05] px-4 py-2 text-sm text-white/85">
                        <FourStar className="size-3.5 shrink-0 text-brand-green" />
                        {t('council.founding_label', 'Înființat prin Ordinul nr. 108A · 30.08.2022')}
                    </span>
                </Reveal>
            </Band>

            {/* Secțiune ALBĂ — componența nominală (Președinte featured + restul în grilă) */}
            <Band variant="light" pattern="mesh">
                <SectionHeader
                    index="✦"
                    label={t('council.members_eyebrow', 'Cine suntem')}
                    title={t('council.members_title', 'Componența nominală')}
                    className="mb-8"
                />

                {/* Președinte — card featured */}
                <Reveal>
                    <LocaleLink
                        href={`/${president.slug}`}
                        className="group mb-4 flex flex-col items-center gap-5 rounded-[18px] border keyline bg-card p-6 text-center transition-all hover:-translate-y-0.5 hover:shadow-[0_22px_50px_-32px_rgba(15,77,119,0.5)] sm:flex-row sm:gap-7 sm:text-left"
                    >
                        <span className="relative block size-28 shrink-0 overflow-hidden rounded-full border-2 keyline ring-2 ring-transparent transition-all group-hover:ring-brand-green">
                            <img
                                src={`/images/profesori/${president.photo}.jpg`}
                                alt={president.name}
                                loading="lazy"
                                decoding="async"
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                        </span>
                        <div>
                            <FnBadge t={t} fn={president.fn} />
                            <span className="display mt-2 block text-2xl text-brand-navy">{president.name}</span>
                            <span className="mt-1 block text-sm leading-snug text-brand-gray">{t(`council.${president.role}`)}</span>
                        </div>
                    </LocaleLink>
                </Reveal>

                {/* Restul membrilor */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {rest.map((m) => (
                        <Reveal as="div" key={m.slug} className="h-full">
                            <LocaleLink
                                href={`/${m.slug}`}
                                className="group flex h-full flex-col items-center rounded-[16px] border keyline bg-card p-5 text-center transition-all hover:-translate-y-1 hover:shadow-[0_18px_40px_-28px_rgba(15,77,119,0.5)]"
                            >
                                <span className="relative block size-20 shrink-0 overflow-hidden rounded-full border-2 keyline ring-2 ring-transparent transition-all group-hover:ring-brand-green">
                                    <img
                                        src={`/images/profesori/${m.photo}.jpg`}
                                        alt={m.name}
                                        loading="lazy"
                                        decoding="async"
                                        className="absolute inset-0 h-full w-full object-cover"
                                    />
                                </span>
                                <FnBadge t={t} fn={m.fn} className="mt-4" />
                                <span className="display mt-2 text-[1.125rem] leading-tight text-brand-navy">{m.name}</span>
                                <span className="mt-1 text-sm leading-snug text-balance text-brand-gray">{t(`council.${m.role}`)}</span>
                            </LocaleLink>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* Secțiune ALBASTRĂ — atribuțiile consiliului */}
            <Band variant="navy" pattern="signature">
                <SectionHeader
                    index="✦"
                    variant="navy"
                    align="center"
                    label={t('council.atrib_eyebrow', 'Ce facem')}
                    title={t('council.atrib_title', 'Atribuțiile Consiliului Metodic')}
                    className="mx-auto max-w-3xl"
                />
                <ol className="mx-auto mt-10 grid max-w-4xl gap-x-8 gap-y-5 sm:grid-cols-2">
                    {ATTRIB.map((a, i) => (
                        <Reveal as="li" key={a} className="flex gap-4">
                            <span className="numeral flex size-9 shrink-0 items-center justify-center rounded-full bg-white/[0.06] text-sm text-brand-green ring-1 ring-white/15">
                                {i + 1}
                            </span>
                            <span className="leading-relaxed text-white/85">{t(`council.${a}`)}</span>
                        </Reveal>
                    ))}
                </ol>
            </Band>

            {/* ── Punte deschisă cu citat: rotunjește pagina cu misiunea consiliului metodic
                (dezvoltarea profesorului = creșterea elevului). Aceeași formulă ca punțile de pe
                homepage și taxe, dar în variantă light — banda anterioară e deja navy. */}
            <Band variant="light" pattern="mesh" className="py-10 sm:py-14">
                <div className="flex flex-col items-center gap-4 text-center">
                    <FourStar className="size-5 text-brand-green" />
                    <Display as="p" className="max-w-[26ch] text-[clamp(1.375rem,3vw,2rem)] leading-[1.1] text-brand-navy">
                        {t('council.closing_quote', 'Un profesor care învață mereu — un elev care crește mereu.')}
                    </Display>
                </div>
            </Band>
        </>
    );
}
