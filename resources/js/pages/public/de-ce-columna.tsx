import { Head } from '@inertiajs/react';
import { ArrowRight, GraduationCap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Band, BrandButton, Container, Display, FourStar, Reveal, Rhombus, SectionHeader, ValueChips } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Tr = (k: string, f?: string) => string;

/* ----------------------------------------------------- Signature: valorile în jurul logoului */

const VALUE_KEYS = ['credinta', 'onoare', 'libertate', 'unire', 'munca', 'natiune', 'adevar'] as const;

function ValuesConstellation({ t }: { t: Tr }) {
    const [active, setActive] = useState<string | null>(null);
    // Reduced-motion: animațiile sunt sărite; conținutul apare static și halo-ul nu se rotește.
    const [reduceMotion] = useState(() => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const [drawn, setDrawn] = useState(reduceMotion);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = ref.current;

        if (!el || drawn) {
            return;
        }

        const io = new IntersectionObserver(
            (entries) => {
                if (entries[0]?.isIntersecting) {
                    setDrawn(true);
                    io.disconnect();
                }
            },
            { threshold: 0.3 },
        );
        io.observe(el);

        return () => io.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const name = (key: string) => t(`values.${key}`, key);
    const gloss = active ? t(`why.values.gloss.${active}`) : t('why.values.hint', 'Atinge o valoare ca să afli ce înseamnă pentru noi.');

    // Geometrie circulară: stelele EXACT pe orbita ringului; etichetele RADIAL în afară, perfect aliniate cu spițele.
    const STAR_RADIUS = 36;
    const LABEL_RADIUS = 47;
    const positionAt = (i: number, r: number) => {
        const angle = -90 + (i * 360) / VALUE_KEYS.length;
        const rad = (angle * Math.PI) / 180;

        return { x: 50 + Math.cos(rad) * r, y: 50 + Math.sin(rad) * r };
    };

    return (
        <div className="mt-10">
            {/* Layout circular — doar pe md+ */}
            <div className="hidden md:block">
                <div ref={ref} className="relative mx-auto aspect-square w-full max-w-xl">
                    {/* Halo + spițe (SVG) */}
                    <svg viewBox="0 0 100 100" className="absolute inset-0 h-full w-full text-white/30" aria-hidden="true">
                        {/* Halo punctat — fade-in la reveal, apoi rotație continuă lentă (80s/turul); oprit sub reduced-motion */}
                        <g
                            style={{
                                transformOrigin: '50% 50%',
                                opacity: drawn ? 1 : 0,
                                transition: 'opacity 1.4s ease',
                                animation: reduceMotion ? 'none' : 'spin 80s linear infinite',
                            }}
                        >
                            <circle
                                cx="50"
                                cy="50"
                                r={STAR_RADIUS}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="0.2"
                                strokeDasharray="0.5 1.2"
                                opacity="0.75"
                            />
                        </g>
                        {/* Spițe — se desenează stagger din centru către fiecare stea, exact pe orbita ringului */}
                        {VALUE_KEYS.map((_, i) => {
                            const { x, y } = positionAt(i, STAR_RADIUS);

                            return (
                                <line
                                    key={i}
                                    x1={50}
                                    y1={50}
                                    x2={x}
                                    y2={y}
                                    stroke="currentColor"
                                    strokeWidth="0.15"
                                    pathLength={1}
                                    strokeDasharray={1}
                                    strokeDashoffset={drawn ? 0 : 1}
                                    style={{ transition: 'stroke-dashoffset 0.7s ease', transitionDelay: `${0.4 + i * 0.1}s` }}
                                />
                            );
                        })}
                    </svg>

                    {/* Logo central — emblema Columna în nimbul ei */}
                    <div
                        className="absolute top-1/2 left-1/2"
                        style={{
                            opacity: drawn ? 1 : 0,
                            transform: `translate(-50%, -50%) scale(${drawn ? 1 : 0.85})`,
                            transition: 'opacity 0.7s ease, transform 0.7s ease',
                        }}
                    >
                        <span className="grid place-items-center rounded-full bg-brand-navy p-3 ring-1 ring-white/20 shadow-[0_0_40px_-10px_rgba(155,195,30,0.35)]">
                            <img src="/images/logo/columna-crest-white.png" alt="" aria-hidden="true" className="h-20 w-auto opacity-95 lg:h-24" />
                        </span>
                    </div>

                    {/* Stelele interactive — poziționate EXACT pe orbita ringului (capătul fiecărei spițe) */}
                    {VALUE_KEYS.map((key, i) => {
                        const { x, y } = positionAt(i, STAR_RADIUS);
                        const isActive = active === key;

                        return (
                            <button
                                key={`star-${key}`}
                                type="button"
                                onMouseEnter={() => setActive(key)}
                                onFocus={() => setActive(key)}
                                onClick={() => setActive(key)}
                                className="absolute flex size-11 items-center justify-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:ring-offset-2 focus-visible:ring-offset-[color:var(--brand-navy)]"
                                style={{
                                    left: `${x}%`,
                                    top: `${y}%`,
                                    opacity: drawn ? 1 : 0,
                                    transform: `translate(-50%, -50%) scale(${drawn ? 1 : 0.8})`,
                                    transition: 'opacity 0.55s ease, transform 0.55s ease',
                                    transitionDelay: `${0.6 + i * 0.1}s`,
                                }}
                                aria-label={name(key)}
                            >
                                <FourStar className={cn('transition-all', isActive ? 'size-7 text-brand-green' : 'size-5 text-brand-green/70')} />
                            </button>
                        );
                    })}

                    {/* Etichetele — RADIAL în afara stelelor (continuă vectorul spițelor); aliniate perfect, indiferent de unghi */}
                    {VALUE_KEYS.map((key, i) => {
                        const { x, y } = positionAt(i, LABEL_RADIUS);
                        const isActive = active === key;

                        return (
                            <span
                                key={`label-${key}`}
                                className={cn(
                                    'pointer-events-none absolute rounded-md px-2 py-0.5 text-[0.8rem] font-semibold tracking-[0.12em] whitespace-nowrap uppercase transition-colors',
                                    isActive ? 'bg-brand-green text-[color:var(--brand-dark)]' : 'text-white/85',
                                )}
                                style={{
                                    left: `${x}%`,
                                    top: `${y}%`,
                                    opacity: drawn ? 1 : 0,
                                    transform: `translate(-50%, -50%) scale(${drawn ? 1 : 0.8})`,
                                    fontFamily: 'var(--font-display)',
                                    transition: 'opacity 0.55s ease, transform 0.55s ease, background-color 0.2s ease, color 0.2s ease',
                                    transitionDelay: `${0.7 + i * 0.1}s`,
                                }}
                            >
                                {name(key)}
                            </span>
                        );
                    })}
                </div>

                {/* Glosă (caption) — se schimbă la valoarea activă */}
                <p className="mx-auto mt-6 min-h-[2.5rem] max-w-xl text-center text-[clamp(1.0625rem,1.6vw,1.25rem)] leading-relaxed text-white/85" aria-live="polite">
                    {active && <span className="font-semibold text-brand-green">{name(active)} — </span>}
                    {gloss}
                </p>
            </div>

            {/* Fallback mobil / reduced-motion: emblemă + lista heraldică completă */}
            <div className="md:hidden">
                <div className="mb-6 flex justify-center">
                    <span className="grid place-items-center rounded-full bg-brand-navy p-2 ring-1 ring-white/20">
                        <img src="/images/logo/columna-crest-white.png" alt="" aria-hidden="true" className="h-16 w-auto opacity-90" />
                    </span>
                </div>
                <ValueChips t={t} className="justify-center" />
                <ul className="mt-6 space-y-3">
                    {VALUE_KEYS.map((key) => (
                        <li key={key} className="border-l-2 border-l-brand-green/50 pl-3">
                            <span className="block text-sm font-semibold text-[color:var(--brand-navy-foreground)]" style={{ fontFamily: 'var(--font-display)' }}>
                                {name(key)}
                            </span>
                            <span className="text-sm text-white/80">{t(`why.values.gloss.${key}`)}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/* ----------------------------------------------------- pagina */

const REASONS = [
    { n: '01', key: 'r1', photo: '/images/galerie/general/g1.jpg', focal: 'center 38%' },
    { n: '02', key: 'r2', href: '/centrul-de-evaluare-institutionala', photo: '/images/galerie/general/g13.jpg', focal: 'center 42%' },
    { n: '03', key: 'r3', href: '/scoala-liceala/dotari', photo: '/images/galerie/scoala-liceala/6.jpg', focal: 'center 60%' },
    { n: '04', key: 'r4', href: '/personal', photo: '/images/profesori/bujor-cobili-carolina.jpg', focal: 'center 25%' },
    { n: '05', key: 'r5', photo: '/images/galerie/general/g14.jpg', focal: 'center 55%' },
];

const STEPS = [
    { numeral: 'I', label: 'why.path.primary_label', body: 'why.path.primary_body', alt: 'why.path.primary_alt', photo: '/images/galerie/scoala-primara/3.jpg', focal: 'center 50%' },
    { numeral: 'II', label: 'why.path.middle_label', body: 'why.path.middle_body', alt: 'why.path.middle_alt', photo: '/images/galerie/scoala-gimnaziala/2.jpg', focal: 'center 65%' },
    { numeral: 'III', label: 'why.path.high_label', body: 'why.path.high_body', alt: 'why.path.high_alt', photo: '/images/galerie/scoala-liceala/6.jpg', focal: 'center 50%' },
];

export default function DeCeColumna() {
    const t = useTranslations();

    return (
        <>
            <Head title={t('why.meta.title', 'De ce Columna — Succesul copilului începe aici')}>
                <meta name="description" content={t('why.meta.description')} />
            </Head>

            {/* HERO — fundal: campusul aerian (g15) cu scrim navy direcțional; conținut single-column pe stânga */}
            <section className="on-navy relative isolate overflow-hidden bg-brand-navy text-[color:var(--brand-navy-foreground)]">
                {/* Fundal — g15 transparentizat: campusul respiră în dreapta, navy domină stânga (unde stă textul) */}
                <img
                    src="/images/galerie/general/g15.jpg"
                    alt=""
                    aria-hidden="true"
                    loading="eager"
                    fetchPriority="high"
                    decoding="async"
                    width={900}
                    height={600}
                    className="absolute inset-0 h-full w-full object-cover opacity-30"
                    style={{ objectPosition: 'center 35%' }}
                />
                {/* Scrim navy direcțional (105°): opac la stânga (text AA garantat), tot mai translucid spre dreapta */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(100deg, var(--brand-navy) 0%, color-mix(in oklch, var(--brand-navy) 88%, transparent) 30%, color-mix(in oklch, var(--brand-navy) 55%, transparent) 65%, color-mix(in oklch, var(--brand-navy) 30%, transparent) 100%)',
                    }}
                />
                {/* Talpă: navy plin spre baza secțiunii (lizibilitate linie legală + tranziție curată către §01) */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 bottom-0 h-32"
                    style={{ background: 'linear-gradient(to top, var(--brand-navy) 0%, transparent 100%)' }}
                />
                <div className="dotgrid pointer-events-none absolute inset-0 opacity-[0.10]" aria-hidden="true" />

                <Container className="relative py-[clamp(3rem,7vw,5.5rem)]">
                    <div className="grid items-center gap-[clamp(2rem,4vw,3.5rem)] lg:grid-cols-[1.3fr_0.7fr]">
                        <Reveal>
                            <div className="flex flex-wrap items-center gap-3">
                                <span className="eyebrow text-brand-green">01</span>
                                <span data-rule className="h-px w-12 origin-left bg-white/30" aria-hidden="true" />
                                <span className="eyebrow text-white/70">{t('why.hero.eyebrow', 'De ce Columna')}</span>
                                <FourStar className="size-3 text-brand-green" />
                            </div>
                            <Display as="h1" className="mt-4 text-[clamp(1.9rem,4.6vw,3.5rem)] text-balance text-[color:var(--brand-navy-foreground)]">
                                {t('why.hero.title', 'Aici copilul tău nu e un număr. E un nume pe care îl rostim cu drag.')}
                            </Display>
                            <span data-rule className="mt-5 block h-1 w-24 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                            <p className="mt-5 max-w-[54ch] text-[clamp(1.0625rem,1.7vw,1.25rem)] leading-relaxed text-white/90">{t('why.hero.lead')}</p>
                            <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                <BrandButton href="/admitere" variant="primary" icon={GraduationCap} className="w-full justify-center sm:w-auto">
                                    {t('why.hero.cta_primary', 'Vino la admitere')}
                                </BrandButton>
                                <BrandButton href="/contacte" variant="ghost-navy" icon={ArrowRight} className="w-full justify-center sm:w-auto">
                                    {t('why.hero.cta_secondary', 'Programează o vizită')}
                                </BrandButton>
                            </div>
                            <div className="mt-7 flex items-center gap-3 text-white/80" aria-label={t('why.stats.cycle_label', 'Cele trei trepte, sub un singur acoperiș')}>
                                {['I–IV', 'V–IX', 'X–XII'].map((lvl, i) => (
                                    <span key={lvl} className="flex items-center gap-3">
                                        {i > 0 && <span className="h-px w-4 bg-white/20" aria-hidden="true" />}
                                        <span className="inline-flex items-center gap-1.5">
                                            <Rhombus className="size-3 text-brand-green" />
                                            <span className="numeral text-sm">{lvl}</span>
                                        </span>
                                    </span>
                                ))}
                            </div>
                        </Reveal>

                        {/* Card translucid de semnătură — sus „1998", mijloc emblema rotativă (auth-emblem), jos invitația directorului */}
                        <Reveal>
                            <div className="rounded-[18px] border border-white/15 bg-white/[0.06] p-6 sm:p-7 lg:flex lg:min-h-[clamp(24rem,34vw,30rem)] lg:flex-col lg:justify-between">
                                {/* Sus: anul fondării */}
                                <div>
                                    <span className="numeral block text-[clamp(2.25rem,4.5vw,3rem)] leading-none text-brand-green">1998</span>
                                    <span className="eyebrow mt-1 block text-white/70">{t('why.hero.card_founded', 'Anul fondării · Chișinău')}</span>
                                </div>
                                {/* Mijloc: emblema Columna cu scutul care se rotește 3D pe axa Y (același element folosit pe paginile de auth) */}
                                <div className="my-6 flex justify-center lg:my-0">
                                    <span className="auth-emblem block h-[10.5rem] w-[10.5rem] sm:h-48 sm:w-48" aria-hidden="true">
                                        <span className="auth-emblem__coin" />
                                    </span>
                                </div>
                                {/* Jos: citatul directorului */}
                                <div className="border-t border-white/15 pt-5">
                                    <p className="flex items-start gap-2 text-[1.0625rem] leading-snug text-white/95">
                                        <FourStar className="mt-1 size-4 shrink-0 text-brand-green" />
                                        <span className="italic">{t('why.hero.card_signature', '„Vă așteptăm cu drag!"')}</span>
                                    </p>
                                    <p className="mt-1 text-sm text-white/75">{t('why.hero.card_director', '— Daniță Ghenadie, Director')}</p>
                                </div>
                            </div>
                        </Reveal>
                    </div>
                </Container>

                {/* Subsol hero — linia legală încadrată de două keyline-uri (forma fostului fleuron, dar cu text în loc de steluță) */}
                <div className="relative z-10 flex items-center justify-center gap-4 pb-[clamp(1.25rem,3vw,2rem)]">
                    <span className="hidden h-px w-12 bg-white/15 sm:block" aria-hidden="true" />
                    <p
                        className="px-2 text-center text-xs tracking-wide text-white/85"
                        style={{ textShadow: '0 1px 3px color-mix(in oklch, var(--brand-navy) 75%, transparent)' }}
                    >
                        {t('why.cta.legal', 'IPL „Liceul Columna", Chișinău · fondat 1998.')}
                    </p>
                    <span className="hidden h-px w-12 bg-white/15 sm:block" aria-hidden="true" />
                </div>
            </section>

            {/* 01 — cinci motive (editorial „zig-zag" cu fotografii reale) */}
            <Band variant="light">
                <SectionHeader index="01" label={t('why.reasons.eyebrow', 'Motivele noastre')} title={t('why.reasons.title', 'Cinci motive, spuse simplu')} lead={t('why.reasons.lead')} className="mb-12" />
                <ol className="space-y-14 lg:space-y-20">
                    {REASONS.map((r, i) => {
                        const reversed = i % 2 === 1;

                        return (
                            <Reveal key={r.key} as="li" className="grid items-center gap-6 sm:gap-10 lg:grid-cols-2 lg:gap-14">
                                <figure className={cn('relative aspect-[3/2] overflow-hidden rounded-[16px] border keyline', reversed && 'lg:order-2')}>
                                    <img
                                        src={r.photo}
                                        alt={t(`why.reasons.${r.key}.alt`)}
                                        loading="lazy"
                                        decoding="async"
                                        width={900}
                                        height={600}
                                        className="absolute inset-0 h-full w-full object-cover"
                                        style={{ objectPosition: r.focal }}
                                    />
                                </figure>
                                <div className={cn('relative', reversed && 'lg:order-1')}>
                                    <div className="flex items-baseline gap-3">
                                        <span className="numeral text-[clamp(3rem,6vw,4.5rem)] leading-none text-brand-green/40">{r.n}</span>
                                        <span data-rule className="h-px w-12 origin-left bg-brand-navy/25" aria-hidden="true" />
                                        <FourStar className="size-3 text-brand-green" />
                                    </div>
                                    <h3 className="display mt-5 text-[clamp(1.4rem,2.6vw,2rem)] text-balance text-brand-navy">{t(`why.reasons.${r.key}.head`)}</h3>
                                    <p className="mt-4 text-[clamp(1.0625rem,1.55vw,1.1875rem)] leading-relaxed text-brand-dark/85">{t(`why.reasons.${r.key}.body`)}</p>
                                    {r.href && (
                                        <span className="mt-6 inline-block">
                                            <BrandButton href={r.href} variant="link">
                                                {t(`why.reasons.${r.key}.link`)}
                                            </BrandButton>
                                        </span>
                                    )}
                                </div>
                            </Reveal>
                        );
                    })}
                </ol>
            </Band>

            {/* 02 — constelația valorilor (SIGNATURE — logo central + 7 valori în cerc, animat la scroll) */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader
                    index="02"
                    variant="navy"
                    align="center"
                    label={t('why.values.eyebrow', 'Șira spinării')}
                    title={t('why.values.title', 'Șapte valori care cresc oameni întregi')}
                    lead={t('why.values.lead')}
                />
                <ValuesConstellation t={t} />
                <Reveal className="mt-10 flex items-center justify-center gap-3" aria-hidden="true">
                    <span className="h-px w-12 bg-white/15" />
                    <FourStar className="size-3 text-brand-green/70" />
                    <span className="h-px w-12 bg-white/15" />
                </Reveal>
            </Band>

            {/* 03 — parcursul I → XII (timeline editorial: 3 trepte cu foto reală + numeral roman) */}
            <Band variant="light">
                <SectionHeader index="03" label={t('why.path.eyebrow', 'Parcursul')} title={t('why.path.title', 'Un singur drum, de la prima literă la diplomă')} lead={t('why.path.lead')} className="mb-12" />
                <ol className="space-y-10 md:space-y-14">
                    {STEPS.map((step) => (
                        <Reveal key={step.label} as="li" className="grid items-center gap-6 md:gap-10 lg:grid-cols-[1.15fr_1fr]">
                            <figure className="relative aspect-[16/9] overflow-hidden rounded-[16px] border keyline">
                                <img
                                    src={step.photo}
                                    alt={t(step.alt)}
                                    loading="lazy"
                                    decoding="async"
                                    width={900}
                                    height={505}
                                    className="absolute inset-0 h-full w-full object-cover"
                                    style={{ objectPosition: step.focal }}
                                />
                                <div
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-0"
                                    style={{ background: 'linear-gradient(135deg, color-mix(in oklch, var(--brand-navy) 70%, transparent) 0%, transparent 45%)' }}
                                />
                                <span
                                    className="numeral absolute top-3 left-4 text-[clamp(2.25rem,4.5vw,3.5rem)] leading-none text-white sm:top-5 sm:left-6"
                                    style={{ textShadow: '0 2px 14px color-mix(in oklch, var(--brand-navy) 75%, transparent)' }}
                                >
                                    {step.numeral}
                                </span>
                            </figure>
                            <div>
                                <div className="flex items-center gap-2 text-brand-green">
                                    <Rhombus className="size-4" />
                                    <Rhombus className="size-3" />
                                    <Rhombus className="size-2" />
                                </div>
                                <h3 className="display mt-4 text-[clamp(1.3rem,2.4vw,1.875rem)] text-balance text-brand-navy">{t(step.label)}</h3>
                                <span data-rule className="mt-4 block h-px w-16 origin-left bg-brand-green" aria-hidden="true" />
                                <p className="mt-4 text-[clamp(1.0625rem,1.55vw,1.1875rem)] leading-relaxed text-brand-dark/85">{t(step.body)}</p>
                            </div>
                        </Reveal>
                    ))}
                </ol>
            </Band>

            {/* 04 — Cambridge English (sigiliu editorial: medalion centrat „2019" + cifră 20 000 + paragraf) */}
            <Band variant="navy" pattern="dotgrid">
                <SectionHeader index="04" variant="navy" align="center" label={t('why.cambridge.eyebrow', 'Standard internațional')} title={t('why.cambridge.title', 'Engleza, la nivel recunoscut în lume')} className="mb-10" />
                <Reveal className="mx-auto grid max-w-4xl items-center gap-8 sm:grid-cols-[auto_1fr] sm:gap-12">
                    {/* sigiliu „2019" — medalion circular */}
                    <div className="mx-auto flex flex-col items-center justify-center rounded-full border-2 border-brand-green/45 ring-4 ring-white/5 p-6 sm:p-8" style={{ background: 'radial-gradient(circle at center, color-mix(in oklch, var(--brand-navy) 55%, transparent) 0%, transparent 70%)' }}>
                        <FourStar className="size-5 text-brand-green" />
                        <span className="numeral mt-2 text-[clamp(2.75rem,5vw,4rem)] leading-none text-brand-green">2019</span>
                        <span className="eyebrow mt-2 block text-center text-white/80">Cambridge English</span>
                        <FourStar className="mt-2 size-3 text-brand-green/60" />
                    </div>
                    {/* text + numeral 20 000 inline + CTA */}
                    <div>
                        <p className="text-[clamp(1.0625rem,1.55vw,1.1875rem)] leading-relaxed text-white/90">{t('why.cambridge.body')}</p>
                        <div className="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-2">
                            <span className="numeral text-[clamp(1.75rem,3.5vw,2.5rem)] leading-none text-[color:var(--brand-navy-foreground)]">20 000</span>
                            <span className="eyebrow text-white/70">{t('why.cambridge.recognized', 'universități, colegii și angajatori')}</span>
                        </div>
                        <span className="mt-6 inline-block">
                            <BrandButton href="/cambridge-english-exam" variant="link-navy">
                                {t('why.cambridge.cta', 'Despre Cambridge English')}
                            </BrandButton>
                        </span>
                    </div>
                </Reveal>
            </Band>

            {/* 05 — scrisoarea directorului */}
            <Band variant="light">
                <SectionHeader index="05" label={t('why.letter.eyebrow', 'De la conducere')} title={t('why.letter.title', 'Un cuvânt de la director')} className="mb-8" />
                <Reveal>
                    <div className="overflow-hidden rounded-[18px] border keyline bg-card shadow-[0_24px_60px_-44px_rgba(15,77,119,0.55)] md:grid md:grid-cols-[minmax(0,18rem)_1fr]">
                        {/* portret — umple înălțimea cardului pe md+ */}
                        <div className="relative aspect-[16/10] sm:aspect-[2/1] md:aspect-auto md:h-full md:min-h-[23rem]">
                            <img
                                src="/images/profesori/danita-ghenadie.jpg"
                                alt={t('why.letter.signature', 'Daniță Ghenadie, Director')}
                                loading="lazy"
                                className="absolute inset-0 h-full w-full object-cover object-[center_22%]"
                            />
                            <span className="absolute inset-x-0 bottom-0 h-1 bg-brand-green md:hidden" aria-hidden="true" />
                        </div>

                        {/* citat + atribuire */}
                        <div className="flex flex-col justify-center gap-4 p-7 sm:p-9 lg:p-11">
                            <span className="display text-[3.5rem] leading-[0.5] text-brand-green/35" aria-hidden="true">„</span>
                            <blockquote>
                                <p className="text-[clamp(1.0625rem,1.55vw,1.3125rem)] leading-relaxed text-brand-dark/90">{t('why.letter.body')}</p>
                            </blockquote>
                            <div className="mt-2 flex flex-wrap items-center gap-x-6 gap-y-3 border-t keyline pt-5">
                                <span className="border-l-[3px] border-l-brand-green pl-3">
                                    <span className="display block text-lg leading-tight text-brand-navy">{t('why.letter.name', 'Daniță Ghenadie')}</span>
                                    <span className="eyebrow text-brand-gray">{t('why.letter.role', 'Director')}</span>
                                </span>
                                <span className="ml-auto">
                                    <BrandButton href="/scrisoarea-directorului" variant="link">
                                        {t('why.letter.link', 'Citește scrisoarea integrală')}
                                    </BrandButton>
                                </span>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </Band>

        </>
    );
}
