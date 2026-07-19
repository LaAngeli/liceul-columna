import { Head } from '@inertiajs/react';
import { ArrowRight, BookOpen, Award, GraduationCap, DoorOpen, Heart, Mail, MapPin, Newspaper, Phone, ShieldCheck, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, BrandButton, Container, Display, FourStar, Reveal, Rhombus, SectionHeader, StatRibbon, ValueChips } from '@/components/public/brand';
import type {StatItem} from '@/components/public/brand';
import { LeadershipGrid } from '@/components/public/leadership-grid';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';
import { cn } from '@/lib/utils';

interface NewsCard {
    title: string;
    slug: string;
    excerpt: string | null;
    image: string | null;
    date: string | null;
}
interface LeadershipMember {
    name: string;
    role: string;
    slug: string | null;
    photo: string | null;
}

const ENROLL = '/inregistrarea-student';

export default function Home({ latestNews, leadership }: { latestNews: NewsCard[]; leadership: LeadershipMember[] }) {
    const t = useTranslations();
    const years = new Date().getFullYear() - siteContact.founded;

    // Obturatorul hero rulează o singură dată pe sesiune; la revenire (SPA/refresh)
    // atributul data-hero-intro lipsește și hero-ul e static de la primul frame.
    const [shutter] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            return !window.sessionStorage.getItem('columna-hero-intro');
        } catch {
            return false;
        }
    });
    useEffect(() => {
        if (shutter) {
            try {
                window.sessionStorage.setItem('columna-hero-intro', '1');
            } catch {
                // sessionStorage indisponibil — intro-ul va rula la fiecare vizită
            }
        }
    }, [shutter]);

    /* Parallax discret pe fotografie: rAF-loop care citește scrollY DIRECT (nu listener de
       `scroll`, nu CSS scroll-timeline — vezi nota din app.css: range-start-ul scroll-driven
       e capricios și minificatorul strică singura formă acceptată). Rulează DOAR cât hero-ul
       e în viewport (IntersectionObserver), doar pe lg+ și doar sub `no-preference`; altfel
       wrapper-ul rămâne netransformat. Transformul stă pe WRAPPER-ul imaginii, nu pe <img> —
       intrarea `site-hero-settle` țintește imaginea și cele două s-ar suprascrie. Baza
       scale(1.07) creează exact marja pe care translatarea (max 24px) o consumă. */
    const photoParallaxRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = photoParallaxRef.current;

        if (!el) {
            return;
        }

        const motionOk = window.matchMedia('(prefers-reduced-motion: no-preference)');
        const isDesktop = window.matchMedia('(min-width: 1024px)');
        let rafId = 0;
        let lastY = -1;
        let running = false;
        let visible = false;

        const tick = () => {
            if (!running) {
                return;
            }

            const y = window.scrollY;

            if (y !== lastY) {
                lastY = y;
                el.style.transform = `scale(1.07) translateY(${Math.min(y * 0.06, 24)}px)`;
            }

            rafId = requestAnimationFrame(tick);
        };

        const update = () => {
            const want = visible && motionOk.matches && isDesktop.matches;

            if (want === running) {
                return;
            }

            running = want;

            if (running) {
                lastY = -1;
                rafId = requestAnimationFrame(tick);
            } else {
                cancelAnimationFrame(rafId);
                el.style.transform = '';
            }
        };

        const io = new IntersectionObserver((entries) => {
            visible = entries[0].isIntersecting;
            update();
        });

        io.observe(el);
        motionOk.addEventListener('change', update);
        isDesktop.addEventListener('change', update);

        return () => {
            io.disconnect();
            running = false;
            cancelAnimationFrame(rafId);
            motionOk.removeEventListener('change', update);
            isDesktop.removeEventListener('change', update);
        };
    }, []);


    const stats: StatItem[] = [
        { value: String(years), suffix: '+', label: t('home.stat_years', 'ani de experiență, din 1998') },
        { value: '3', icon: 'rhombi', label: t('home.stat_levels', 'trepte: primar · gimnazial · liceal') },
        { value: 'I–XII', label: t('home.stat_grades', 'clase, de la primar la liceu') },
        { value: '2019', accent: true, label: t('home.stat_cambridge', 'centru autorizat Cambridge English') },
    ];

    const institution = [
        { title: t('about.letter', 'Scrisoarea directorului'), desc: t('home.letter_desc', 'Mesajul conducerii despre viziunea și valorile liceului.'), href: '/scrisoarea-directorului', icon: BookOpen, wide: true },
        { title: t('menu.mission', 'Misiune și valori'), desc: t('home.mission_desc', 'Filosofia educațională care ne ghidează în fiecare zi.'), href: '/filosofia-liceului', icon: Heart, wide: false },
    ];

    const programs = [
        { title: t('structure.primary', 'Școala primară'), range: 'I–IV', href: '/scoala-primara' },
        { title: t('structure.gymnasium', 'Școala gimnazială'), range: 'V–IX', href: '/scoala-gimnaziala' },
        { title: t('structure.lyceum', 'Școala liceală'), range: 'X–XII', href: '/scoala-liceala' },
    ];

    const steps = [
        { n: '01', title: t('home.step1', 'Programează o vizită'), desc: t('home.step1_desc', 'Ne cunoaștem și vizitați liceul.') },
        { n: '02', title: t('home.step2', 'Depune actele'), desc: t('home.step2_desc', 'Dosarul de înmatriculare al copilului.') },
        { n: '03', title: t('home.step3', 'Înmatriculare'), desc: t('home.step3_desc', 'Semnați contractul și începeți.') },
    ];

    // Rigla hero → paginile pe care un părinte le cercetează înainte de a se hotărî
    // (funnel-ul propriu-zis de admitere rămâne în secțiunea 04). Terminusul cu steluța
    // verde = „De ce Columna?", capătul firului.
    const heroLinks = [
        { title: t('menu.mission', 'Misiune și valori'), href: '/filosofia-liceului', icon: Heart },
        { title: t('nav.admission', 'Admitere'), href: '/admitere', icon: DoorOpen },
        { title: t('menu.fees', 'Taxe și costuri'), href: '/taxe', icon: Wallet },
    ];

    const proof = [
        { title: t('about.accreditation', 'Acreditări și autorizare'), href: '/acreditari', icon: ShieldCheck },
        { title: t('utility.cambridge', 'Cambridge English'), href: '/cambridge-english-exam', icon: Award },
        { title: t('utility.cei', 'Centrul de Evaluare Instituțională'), href: '/centrul-de-evaluare-institutionala', icon: GraduationCap },
    ];

    return (
        <>
            <Head>
                <title>{t('home.seo_title', 'Liceul „Columna” Chișinău — Înscrie copilul din 1998')}</title>
                <meta name="description" content={t('home.seo_desc', 'Liceu privat în Chișinău din 1998: primar, gimnazial, liceal, centru Cambridge. Programează o vizită și înscrie-ți copilul. Succesul copilului începe aici.')} />
                <link
                    rel="preload"
                    as="image"
                    imageSrcSet="/images/hero/g15-hero-900.webp 900w, /images/hero/g15-hero-1440.webp 1440w, /images/hero/g15-hero-1800.webp 1800w"
                    imageSizes="(min-width: 1024px) 54vw, 100vw"
                    fetchPriority="high"
                />
            </Head>

            {/* ───────────────────────── 00 — HERO („Cadrul & Drumul") ─────────────────────────
                Un singur cadru real: fotografia aeriană cu elevii care salută camera. Obturatorul
                navy se deschide o dată pe sesiune, apoi firul verde al admiterii se desenează
                01→03 și se termină în steluța-terminus — semnătura persistentă a paginii. */}
            <section
                data-hero-intro={shutter ? '' : undefined}
                className="on-navy hero-viewport relative isolate flex flex-col overflow-hidden bg-surface-navy text-[color:var(--brand-navy-foreground)]"
            >
                {/* Foto-eroină: mobil = bloc în flux sus (100vw); desktop = panou pe dreapta
                    (~54vw) unde imaginea de 900px e afișată aproape la mărimea nativă = clară,
                    nu întinsă full-bleed la 2× (unde s-ar înmuia). Textul stă pe navy solid. */}
                <div className="hero-photo relative h-[min(38vh,290px)] w-full shrink-0 overflow-hidden lg:absolute lg:inset-y-0 lg:right-0 lg:left-[46%] lg:h-full lg:w-auto">
                    {/* wrapper-ul parallax (transform din rAF-loop); gradientele rămân SURORI = cusăturile stau pe loc */}
                    <div ref={photoParallaxRef} className="h-full w-full">
                        <img
                            src="/images/hero/g15-hero-1440.webp"
                            srcSet="/images/hero/g15-hero-900.webp 900w, /images/hero/g15-hero-1440.webp 1440w, /images/hero/g15-hero-1800.webp 1800w"
                            sizes="(min-width: 1024px) 54vw, 100vw"
                            alt={t('home.hero_photo_caption', 'Campusul liceului, văzut de sus')}
                            loading="eager"
                            fetchPriority="high"
                            decoding="async"
                            className="h-full w-full object-cover object-[50%_42%]"
                        />
                    </div>
                    {/* fuziune mobilă foto→navy */}
                    <div aria-hidden="true" className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-[color:var(--surface-navy)] to-transparent lg:hidden" />
                    {/* desktop: cusătura stângă se topește în panoul navy (blend, nu tăietură) */}
                    <div aria-hidden="true" className="absolute inset-y-0 left-0 hidden w-40 bg-gradient-to-r from-[color:var(--surface-navy)] to-transparent lg:block" />
                    {/* scrim de jos desktop — lizibilitatea riglei peste foto */}
                    <div aria-hidden="true" className="absolute inset-x-0 bottom-0 hidden h-40 bg-gradient-to-t from-[color:var(--surface-navy)]/85 to-transparent lg:block" />
                </div>
                {/* Textură navy hero — puncte în tonul opus, CONFINATE la zona navy (nu ating foto la
                    niciun ecran). Desktop: panoul de text stânga, până la 46% = muchia fotografiei.
                    Mobil: zona navy de sub blocul foto (începe exact la finalul lui: min(38vh,290px)). */}
                <div aria-hidden="true" className="tx-hero-dots-desktop pointer-events-none absolute inset-y-0 left-0 hidden w-[46%] lg:block" />
                <div aria-hidden="true" className="tx-hero-dots-mobile pointer-events-none absolute inset-x-0 bottom-0 lg:hidden" style={{ top: 'min(38vh, 290px)' }} />
                {/* Crest heraldic — filigran în BANDA LIBERĂ dintre marginea stângă a ecranului și
                    coloana de text, ca să nu mai stea sub text. Lățimea benzii e o funcție exactă de
                    container (`max-width: 75rem` centrat + `padding-inline: 1.5rem`):
                        bandă = (lățime_ecran − 75rem) / 2 + 1.5rem
                    → 360px la 1920, 144px la 1440, dar 40px la 1280 și ZERO sub 1200. De aceea pragul
                    de afișare e 1440px: sub el banda se prăbușește matematic și sigla ar deveni fie o
                    pată de ~50px, fie ar intra peste text. Poziția e FIXĂ (lipită de marginea stângă,
                    centrată vertical), iar mărimea e fluidă — procentuală din bandă, deci scalează cu
                    ecranul, cu plafon în rem și `max-h` ca să nu depășească hero-ul.
                    `100%` (nu `100vw`) intenționat: exclude bara de derulare. Pe responsiv: ASCUNSĂ. */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 left-0 hidden items-center justify-center min-[1440px]:flex"
                    style={{ width: 'calc((100% - 75rem) / 2 + 1.5rem)' }}
                >
                    <img
                        src="/images/logo/columna-crest-white.png"
                        alt=""
                        className="max-h-[46%] w-[70%] max-w-[20rem] object-contain opacity-[0.08] select-none"
                    />
                </div>
                <div aria-hidden="true" className="hero-glow pointer-events-none absolute top-[10%] -left-[4%] hidden size-[32rem] lg:block" />
                {/* Panoul de text absoarbe spațiul rămas (`flex-1`) și își centrează conținutul vertical
                    — înălțimea hero-ului e dictată de viewport (`.hero-viewport`), nu de un clamp fix.
                    Înainte era `lg:min-h-[clamp(560px,76vh,800px)]`: la 76vh rămânea o fâșie din
                    secțiunea următoare vizibilă sub fold. */}
                <Container className="relative z-10 -mt-10 flex flex-1 flex-col justify-center lg:mt-0">
                    <div className="flex flex-col gap-5 pb-10 lg:max-w-[42%] lg:gap-6 lg:pb-44">
                        <span className="hero-stage eyebrow text-white/70 [--stage:0]">{t('home.hero_eyebrow', 'Liceu privat · Chișinău · din 1998')}</span>
                        <Display as="h1" className="hero-stage max-w-[14ch] text-[clamp(2.125rem,6.4vw,4rem)] leading-[0.98] text-[color:var(--brand-navy-foreground)] [--stage:1]">
                            {t('home.hero_title', 'Succesul copilului începe aici.')}
                        </Display>
                        <span aria-hidden="true" className="hero-stage-rule h-1 w-24 origin-left rounded-full bg-brand-green" />
                        <p className="hero-stage max-w-[40ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed font-medium text-white/85 [--stage:2]">
                            {t('home.hero_lead', 'Educație de calitate de la clasele primare până la liceu — într-un mediu sigur și inspirat, din 1998.')}
                        </p>
                        <div className="hero-stage flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center [--stage:3]">
                            <BrandButton href={ENROLL} variant="primary" icon={GraduationCap} className="w-full sm:w-auto">
                                {t('menu.enroll', 'Înscrie copilul')}
                            </BrandButton>
                            <BrandButton href="/programeaza-vizita" variant="ghost-navy" className="w-full sm:w-auto">
                                {t('home.visit', 'Vizitează liceul')}
                            </BrandButton>
                        </div>
                        {/* VARIANTA A — Rigla treptelor: index vertical navigabil care umple panoul cu
                            STRUCTURĂ (nu decor) și arată din prima că școala acoperă tot parcursul I–XII.
                            Reutilizează `programs` (aceleași trei trepte ca secțiunea 03) → zero chei i18n
                            noi și o singură sursă de adevăr pentru denumiri/linkuri. */}
                        {/* Rigla treptelor — index vertical navigabil care umple panoul cu STRUCTURĂ (nu
                            decor) și arată din prima că școala acoperă tot parcursul I–XII. Reutilizează
                            `programs` (aceleași trepte ca secțiunea 03) → o singură sursă de adevăr pentru
                            denumiri/linkuri și zero chei i18n noi. */}
                        <nav
                            aria-label={t('home.programs_title', 'Cele trei trepte de școlaritate')}
                            className="hero-stage mt-1 max-w-[26rem] border-l-2 border-l-brand-green pl-4 sm:pl-5 [--stage:4]"
                        >
                            {programs.map((p, i) => (
                                <LocaleLink
                                    key={p.href}
                                    href={p.href}
                                    className={cn(
                                        'group flex items-baseline gap-4 py-2 text-white/85 transition-colors hover:text-white sm:gap-5 sm:py-2.5',
                                        i > 0 && 'border-t border-white/15',
                                    )}
                                >
                                    <span className="numeral w-14 shrink-0 text-lg text-brand-green sm:w-16 sm:text-xl">{p.range}</span>
                                    <span className="text-sm font-semibold sm:text-[0.95rem]">{p.title}</span>
                                    <ArrowRight className="ml-auto size-4 shrink-0 self-center text-white/40 transition-transform group-hover:translate-x-0.5 group-hover:text-white/80" />
                                </LocaleLink>
                            ))}
                        </nav>
                    </div>
                </Container>
                {/* Chip de proveniență — decorativ (alt-ul imaginii poartă textul pentru SR) */}
                <div
                    aria-hidden="true"
                    className="hero-stage pointer-events-none absolute right-6 bottom-24 z-10 hidden items-center gap-2 rounded-full border border-white/25 bg-[color:var(--surface-navy)]/60 px-3 py-1.5 text-xs text-white/80 supports-[backdrop-filter]:backdrop-blur-sm lg:inline-flex [--stage:7]"
                >
                    <span className="hero-pulse size-2 rounded-full bg-brand-green" />
                    {t('home.hero_photo_caption', 'Campusul liceului, văzut de sus')}
                </div>
                {/* Rigla de destinații-cheie + drumul verde care se termină în steluța „De ce Columna?" */}
                <nav
                    aria-label={t('home.hero_links_label', 'Pagini importante')}
                    className="hero-ruler relative z-10 shrink-0 border-t border-white/15 bg-[color:var(--surface-navy)]/70 supports-[backdrop-filter]:bg-[color:var(--surface-navy)]/55 supports-[backdrop-filter]:backdrop-blur-sm lg:absolute lg:inset-x-0 lg:bottom-0"
                >
                    <span aria-hidden="true" className="hero-path absolute inset-x-0 -top-px h-[2px] origin-left bg-brand-green" />
                    <Container>
                        {/* desktop: 3 destinații + terminus „De ce Columna?" */}
                        <div className="hidden min-h-[76px] items-stretch lg:grid lg:grid-cols-[1fr_1fr_1fr_auto]">
                            {heroLinks.map((l, i) => {
                                const Icon = l.icon;

                                return (
                                    <LocaleLink
                                        key={l.href}
                                        href={l.href}
                                        className={cn('flex min-w-0 items-center gap-3 px-5 transition-colors hover:bg-white/10', i > 0 && 'border-l border-white/15')}
                                    >
                                        <Icon className="size-5 shrink-0 text-brand-green" />
                                        <span className="leading-tight font-semibold">{l.title}</span>
                                    </LocaleLink>
                                );
                            })}
                            <LocaleLink href="/de-ce-columna" className="flex items-center gap-2.5 border-l border-white/15 px-6 font-semibold transition-colors hover:bg-white/10">
                                <FourStar className="hero-star size-4 shrink-0 text-brand-green" />
                                {t('about.why', 'De ce Columna?')}
                                <ArrowRight className="size-4 shrink-0" />
                            </LocaleLink>
                        </div>
                        {/* mobil: 2×2 destinații; steluța „De ce Columna?" rămâne capătul drumului */}
                        <div className="grid grid-cols-2 lg:hidden">
                            {heroLinks.map((l, i) => {
                                const Icon = l.icon;

                                return (
                                    <LocaleLink
                                        key={l.href}
                                        href={l.href}
                                        className={cn('flex items-center gap-2.5 px-4 py-3.5', i % 2 === 1 && 'border-l border-white/15', i >= 2 && 'border-t border-white/15')}
                                    >
                                        <Icon className="size-4 shrink-0 text-brand-green" />
                                        <span className="text-sm font-semibold">{l.title}</span>
                                    </LocaleLink>
                                );
                            })}
                            <LocaleLink href="/de-ce-columna" className="flex items-center gap-2.5 border-l border-t border-white/15 px-4 py-3.5">
                                <FourStar className="hero-star size-4 shrink-0 text-brand-green" />
                                <span className="text-sm font-semibold">{t('about.why', 'De ce Columna?')}</span>
                            </LocaleLink>
                        </div>
                    </Container>
                </nav>
                {/* Panourile obturatorului — doar la prima vizită pe sesiune, deasupra a tot */}
                {shutter && (
                    <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-20">
                        <div className="hero-shutter-top absolute inset-x-0 top-0 h-[42%] bg-[color:var(--surface-navy)]" />
                        <div className="hero-shutter-bottom absolute inset-x-0 top-[42%] bottom-0 bg-[color:var(--surface-navy)]" />
                    </div>
                )}
            </section>

            {/* ───────────────────────── 01 — PRESTIGIU (stat ribbon) ───────────────────────── */}
            <Band variant="light" pattern="mesh">
                <SectionHeader index="01" label={t('home.k_prestige', 'PRESTIGIU')} title={t('home.prestige_title', 'O instituție de încredere, din 1998')} />
                <div className="mt-8">
                    <StatRibbon items={stats} />
                </div>
            </Band>

            {/* ───────────────────────── 02 — INSTITUȚIA (hub cards) ───────────────────────── */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader variant="navy" index="02" label={t('home.k_institution', 'INSTITUȚIA')} title={t('home.institution_title', 'Instituția Privată Liceul „Columna”')} />
                <div className="mt-8 grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                    {institution.map((c) => {
                        const Icon = c.icon;

                        return (
                            <Reveal key={c.href} as="article">
                                <LocaleLink href={c.href} className="group flex h-full flex-col gap-3 rounded-[12px] border border-white/15 bg-white/[0.04] p-6 transition-all hover:-translate-y-0.5 hover:border-brand-green/60 sm:p-8">
                                    <span className="flex size-11 items-center justify-center rounded-md bg-white/10 text-brand-green">
                                        <Icon className="size-5" />
                                    </span>
                                    <Display as="h3" className="text-[1.375rem] text-[color:var(--brand-navy-foreground)]">{c.title}</Display>
                                    <p className="text-white/70">{c.desc}</p>
                                    <span className="mt-auto inline-flex items-center gap-1.5 pt-2 font-semibold text-[color:var(--brand-navy-foreground)] underline decoration-brand-green decoration-2 underline-offset-4">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </LocaleLink>
                            </Reveal>
                        );
                    })}
                </div>
            </Band>

            {/* ───────────────────────── 03 — PROGRAME (trei trepte) ───────────────────────── */}
            <Band variant="light" pattern="mesh">
                <SectionHeader index="03" label={t('home.k_programs', 'PROGRAME')} title={t('home.programs_title', 'Cele trei trepte de școlaritate')} lead={t('home.programs_lead', 'Un parcurs continuu, de la primii pași până la examenele de bacalaureat.')} />
                <div className="mt-8 grid gap-5 sm:grid-cols-3">
                    {programs.map((p, i) => (
                        <Reveal key={p.href} as="article">
                            <LocaleLink href={p.href} className="group flex h-full flex-col gap-4 rounded-[12px] border keyline border-l-[5px] border-l-brand-green bg-card p-6 transition-all hover:-translate-y-0.5 sm:p-7">
                                <span className="flex items-center gap-1 text-brand-green" aria-hidden="true">
                                    {Array.from({ length: i + 1 }).map((_, k) => (
                                        <Rhombus key={k} className="size-4" />
                                    ))}
                                </span>
                                <Display as="h3" className="text-[1.375rem] text-brand-navy">{p.title}</Display>
                                <span className="numeral text-3xl text-brand-navy/30">{p.range}</span>
                                <span className="mt-auto inline-flex items-center gap-1.5 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                                    {t('action.details', 'Detalii')} <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </LocaleLink>
                        </Reveal>
                    ))}
                </div>
            </Band>

            {/* ───────────────────────── 04 — ADMITERE (funnel) ───────────────────────── */}
            <Band variant="navy" pattern="signature" id="admitere" className="scroll-mt-28">
                <div className="grid items-center gap-10 lg:grid-cols-[1fr_1.1fr]">
                    <SectionHeader
                        variant="navy"
                        index="04"
                        label={t('nav.admission', 'Admitere')}
                        title={t('home.admission_title', 'Înscrierea, în trei pași simpli')}
                        lead={t('home.admission_lead', 'Te însoțim la fiecare pas — de la prima vizită până la prima zi de școală.')}
                    />
                    <Reveal className="flex flex-col gap-4">
                        {steps.map((s) => (
                            <div key={s.n} className="flex items-start gap-4 rounded-[12px] border border-white/15 bg-white/[0.04] p-5">
                                <span className="numeral text-3xl text-brand-green">{s.n}</span>
                                <div>
                                    <p className="font-semibold text-[color:var(--brand-navy-foreground)]">{s.title}</p>
                                    <p className="text-sm text-white/70">{s.desc}</p>
                                </div>
                            </div>
                        ))}
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <BrandButton href={ENROLL} variant="primary" icon={GraduationCap}>{t('menu.enroll', 'Înscrie copilul')}</BrandButton>
                            <a href="tel:+37322742852" className="inline-flex items-center gap-2 py-2 -my-2 font-semibold text-white/85 hover:text-white">
                                <Phone className="size-4 text-brand-green" /> {siteContact.phone}
                            </a>
                        </div>
                    </Reveal>
                </div>
            </Band>

            {/* ───────────────────────── 05 — VIAȚA ȘCOLII (news) ───────────────────────── */}
            {latestNews.length > 0 && (
                <Band variant="light" pattern="mesh">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <SectionHeader index="05" label={t('home.k_life', 'VIAȚA ȘCOLII')} title={t('home.latest_news', 'Ultimele actualități')} />
                        <BrandButton href="/actualitati-si-evenimente" variant="link" icon={ArrowRight}>{t('home.all_news', 'Toate știrile')}</BrandButton>
                    </div>
                    <Reveal className="mt-8 grid overflow-hidden rounded-[12px] border keyline sm:grid-cols-2 lg:grid-cols-3">
                        {latestNews.map((post, i) => (
                            <LocaleLink
                                key={post.slug}
                                href={`/articol/${post.slug}`}
                                className={`group flex flex-col border-l-[5px] border-l-brand-navy bg-card transition-colors hover:bg-accent ${i > 0 ? 'border-t keyline sm:border-t-0 sm:border-l-[5px]' : ''} ${i >= 1 ? 'sm:border-l keyline sm:border-l-brand-navy' : ''}`}
                            >
                                {post.image ? (
                                    <div className="photo-frame aspect-video overflow-hidden">
                                        <img src={post.image} alt={post.title} loading="lazy" className="h-full w-full object-cover" />
                                    </div>
                                ) : (
                                    <div className="flex aspect-video items-center justify-center bg-brand-navy/5 text-brand-navy/40">
                                        <Newspaper className="size-8" />
                                    </div>
                                )}
                                <div className="flex flex-1 flex-col gap-2 p-5">
                                    {post.date && <span className="eyebrow text-brand-gray">{post.date}</span>}
                                    <h3 className="heading-dynamic text-lg text-brand-navy">{post.title}</h3>
                                    {post.excerpt && <p className="line-clamp-2 text-sm text-brand-gray">{post.excerpt}</p>}
                                </div>
                            </LocaleLink>
                        ))}
                    </Reveal>
                </Band>
            )}

            {/* ───────────────────────── 06 — DE CE COLUMNA (proof + valori) ───────────────────────── */}
            <Band variant="navy" pattern="mesh">
                <SectionHeader variant="navy" index="06" label={t('about.why', 'De ce Columna?')} title={t('home.why_title', 'De ce Liceul „Columna”')} lead={t('home.why_lead', 'Educăm elevii în spiritul valorilor naționale și general-umane, cu standarde academice riguroase.')} />
                <Reveal className="mt-8">
                    <ValueChips t={t} />
                </Reveal>
                <div className="mt-10 grid gap-4 sm:grid-cols-3">
                    {proof.map((p) => {
                        const Icon = p.icon;

                        return (
                            <LocaleLink key={p.href} href={p.href} className="group flex items-center gap-3 rounded-[12px] border border-white/15 bg-white/[0.04] p-5 transition-colors hover:border-brand-green/60">
                                <Icon className="size-6 shrink-0 text-brand-green" />
                                <span className="font-semibold text-[color:var(--brand-navy-foreground)]">{p.title}</span>
                                <ArrowRight className="ml-auto size-4 text-white/60 transition-transform group-hover:translate-x-0.5" />
                            </LocaleLink>
                        );
                    })}
                </div>
            </Band>

            {/* ───────────────────────── 07 — ECHIPA (tot personalul) ───────────────────────── */}
            {leadership.length > 0 && (
                <Band variant="light" pattern="mesh">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <SectionHeader index="07" label={t('home.k_team', 'ECHIPA')} title={t('home.staff_title', 'Personal')} />
                        <BrandButton href="/personal" variant="link" icon={ArrowRight}>{t('home.staff_see_all', 'Vezi toată echipa')}</BrandButton>
                    </div>
                    <LeadershipGrid members={leadership} />
                </Band>
            )}

            {/* ── Punte navy: rotunjește ECHIPA + reface ritmul navy↔deschis între ECHIPA (deschis)
                și CTA (deschis). Bandă slabă ca înălțime, o afirmație caldă despre rolul echipei. */}
            <Band variant="navy" pattern="mesh" className="py-10 sm:py-14">
                <div className="flex flex-col items-center gap-4 text-center">
                    <FourStar className="size-5 text-brand-green" />
                    <Display as="p" className="max-w-[26ch] text-[clamp(1.375rem,3vw,2rem)] leading-[1.1] text-[color:var(--brand-navy-foreground)]">
                        {t('home.team_bridge', 'La Columna, fiecare copil are o echipă întreagă de partea lui.')}
                    </Display>
                </div>
            </Band>

            {/* ───────────────────────── CTA final ─────────────────────────
                Bandă DESCHISĂ care încheie pagina înainte de footerul navy (ritmul navy↔deschis). */}
            <Band variant="light" pattern="signature">
                <div className="flex flex-col items-center gap-6 text-center">
                    <FourStar className="size-6 text-brand-green" />
                    <Display as="h2" className="max-w-[18ch] text-[clamp(1.75rem,4vw,3rem)] text-brand-navy">
                        {t('home.cta_title', 'Programează o vizită la Liceul Columna')}
                    </Display>
                    <div className="flex flex-col items-center gap-2 text-sm text-brand-gray sm:flex-row sm:flex-wrap sm:justify-center sm:gap-5">
                        <a href="tel:+37322742852" className="inline-flex items-center gap-2 py-2 hover:text-brand-navy sm:py-0"><Phone className="size-4 text-brand-green" /> {siteContact.phone}</a>
                        <a href={`mailto:${siteContact.email}`} className="inline-flex items-center gap-2 py-2 hover:text-brand-navy sm:py-0"><Mail className="size-4 text-brand-green" /> {siteContact.email}</a>
                        <span className="inline-flex items-center gap-2"><MapPin className="size-4 text-brand-green" /> {siteContact.address}</span>
                    </div>
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <BrandButton href={ENROLL} variant="primary" icon={GraduationCap}>{t('menu.enroll', 'Înscrie copilul')}</BrandButton>
                        <BrandButton href="/contacte" variant="ghost">{t('utility.contact', 'Contacte')}</BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
