import { Head } from '@inertiajs/react';
import { ArrowRight, BookOpen, Award, GraduationCap, Heart, Mail, MapPin, Newspaper, Phone, ShieldCheck } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Band, BrandButton, Container, Display, FourStar, Reveal, Rhombus, SectionHeader, StatRibbon, ValueChips, type StatItem } from '@/components/public/brand';
import { LeadershipGrid } from '@/components/public/leadership-grid';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';

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
            </Head>

            {/* ───────────────────────── 00 — HERO (Funnel Blazon) ───────────────────────── */}
            <section className="on-navy relative overflow-hidden bg-brand-navy text-[color:var(--brand-navy-foreground)]">
                <div className="dotgrid pointer-events-none absolute inset-0 opacity-[0.12]" aria-hidden="true" />
                <Container className="relative grid items-center gap-10 py-[clamp(3rem,7vw,6rem)] lg:grid-cols-[1.55fr_1fr]">
                    <Reveal className="flex flex-col gap-6">
                        <span className="eyebrow text-white/70">00 — {t('breadcrumb.home', 'Acasă')}</span>
                        <Display as="h1" className="max-w-[14ch] text-[clamp(2.125rem,6.4vw,4rem)] leading-[0.98] text-[color:var(--brand-navy-foreground)]">
                            {t('home.hero_title', 'Succesul copilului începe aici.')}
                        </Display>
                        <span data-rule className="h-1 w-24 origin-left rounded-full bg-brand-green" aria-hidden="true" />
                        <p className="max-w-[40ch] text-[clamp(1.0625rem,1.6vw,1.1875rem)] leading-relaxed font-medium text-white/85">
                            {t('home.hero_lead', 'Educație de calitate de la clasele primare până la liceu — într-un mediu sigur și inspirat, din 1998.')}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <BrandButton href={ENROLL} variant="primary" icon={GraduationCap}>
                                {t('menu.enroll', 'Înscrie copilul')}
                            </BrandButton>
                            <BrandButton href="/contacte" variant="ghost-navy">
                                {t('home.visit', 'Vizitează liceul')}
                            </BrandButton>
                        </div>
                    </Reveal>

                    {/* Card-proof: 3 pași admitere (fără foto stock — panou navy + crest) */}
                    <Reveal className="relative">
                        <img src="/images/logo/columna-white.png" alt="" aria-hidden="true" className="pointer-events-none absolute -top-10 -right-6 w-40 opacity-[0.07]" />
                        <div className="relative rounded-[14px] border border-white/15 bg-white/[0.04] p-6 shadow-2xl backdrop-blur-sm">
                            <span className="eyebrow text-brand-green">{t('home.how_enroll', 'Cum te înscrii')}</span>
                            <ol className="mt-5 space-y-4">
                                {steps.map((s) => (
                                    <li key={s.n} className="flex items-start gap-3">
                                        <span className="numeral text-xl text-brand-green">{s.n}</span>
                                        <span>
                                            <span className="block font-semibold text-[color:var(--brand-navy-foreground)]">{s.title}</span>
                                            <span className="block text-sm text-white/70">{s.desc}</span>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                            <div className="mt-5 h-1.5 w-full overflow-hidden rounded-full bg-white/15" aria-hidden="true">
                                <span className="block h-full w-1/3 rounded-full bg-brand-green" />
                            </div>
                            <p className="mt-2 text-xs text-white/55">{t('home.step_progress', 'Pasul 1 din 3 · 100% online')}</p>
                            <BrandButton href={ENROLL} variant="primary" icon={ArrowRight} className="mt-5 w-full">
                                {t('menu.enroll', 'Înscrie copilul')}
                            </BrandButton>
                        </div>
                    </Reveal>
                </Container>
            </section>

            {/* ───────────────────────── 01 — PRESTIGIU (stat ribbon) ───────────────────────── */}
            <Band variant="light">
                <SectionHeader index="01" label={t('home.k_prestige', 'PRESTIGIU')} title={t('home.prestige_title', 'O instituție de încredere, din 1998')} />
                <div className="mt-8">
                    <StatRibbon items={stats} />
                </div>
            </Band>

            {/* ───────────────────────── 02 — INSTITUȚIA (hub cards) ───────────────────────── */}
            <Band variant="light" className="!pt-0">
                <SectionHeader index="02" label={t('home.k_institution', 'INSTITUȚIA')} title={t('home.institution_title', 'Instituția Privată Liceul „Columna”')} />
                <div className="mt-8 grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                    {institution.map((c) => {
                        const Icon = c.icon;
                        return (
                            <Reveal key={c.href} as="article">
                                <LocaleLink href={c.href} className="group flex h-full flex-col gap-3 rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card p-6 transition-all hover:-translate-y-0.5 hover:border-l-brand-green sm:p-8">
                                    <span className="flex size-11 items-center justify-center rounded-md bg-brand-navy/8 text-brand-navy">
                                        <Icon className="size-5" />
                                    </span>
                                    <Display as="h3" className="text-[1.375rem] text-brand-navy">{c.title}</Display>
                                    <p className="text-brand-gray">{c.desc}</p>
                                    <span className="mt-auto inline-flex items-center gap-1.5 pt-2 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </LocaleLink>
                            </Reveal>
                        );
                    })}
                </div>
            </Band>

            {/* ───────────────────────── 03 — PROGRAME (trei trepte) ───────────────────────── */}
            <Band variant="light" className="border-t keyline !pt-[clamp(3.5rem,8vw,8rem)]">
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
            <Band variant="navy" pattern="dotgrid">
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
                <Band variant="light">
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
            <Band variant="navy">
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

            {/* ───────────────────────── 07 — ECHIPA (conducerea) ───────────────────────── */}
            {leadership.length > 0 && (
                <Band variant="light">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <SectionHeader index="07" label={t('home.k_team', 'ECHIPA')} title={t('home.staff_title', 'Conducerea liceului')} />
                        <BrandButton href="/personal" variant="link" icon={ArrowRight}>{t('home.staff_see_all', 'Vezi toată echipa')}</BrandButton>
                    </div>
                    <LeadershipGrid members={leadership} />
                </Band>
            )}

            {/* ───────────────────────── CTA final ───────────────────────── */}
            <Band variant="navy" pattern="dotgrid">
                <div className="flex flex-col items-center gap-6 text-center">
                    <FourStar className="size-6 text-brand-green" />
                    <Display as="h2" className="max-w-[18ch] text-[clamp(1.75rem,4vw,3rem)] text-[color:var(--brand-navy-foreground)]">
                        {t('home.cta_title', 'Programează o vizită la Liceul Columna')}
                    </Display>
                    <div className="flex flex-col items-center gap-2 text-sm text-white/85 sm:flex-row sm:flex-wrap sm:justify-center sm:gap-5">
                        <a href="tel:+37322742852" className="inline-flex items-center gap-2 py-2 hover:text-white sm:py-0"><Phone className="size-4 text-brand-green" /> {siteContact.phone}</a>
                        <a href={`mailto:${siteContact.email}`} className="inline-flex items-center gap-2 py-2 hover:text-white sm:py-0"><Mail className="size-4 text-brand-green" /> {siteContact.email}</a>
                        <span className="inline-flex items-center gap-2"><MapPin className="size-4 text-brand-green" /> {siteContact.address}</span>
                    </div>
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        <BrandButton href={ENROLL} variant="primary" icon={GraduationCap}>{t('menu.enroll', 'Înscrie copilul')}</BrandButton>
                        <BrandButton href="/contacte" variant="ghost-navy">{t('utility.contact', 'Contacte')}</BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
