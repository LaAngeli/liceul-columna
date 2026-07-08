import { Head } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Atom,
    BookOpen,
    Clock,
    Feather,
    Gauge,
    Globe,
    GraduationCap,
    Home,
    ImageIcon,
    Lightbulb,
    Lock,
    Mail,
    PenTool,
    Ruler,
    ServerCrash,
    Sun,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, BrandButton, FourStar, Reveal } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Pagină de eroare UNICĂ pentru tot site-ul public, randată prin Inertia din
 * `Inertia::handleExceptionsUsing()` (AppServiceProvider). Acoperă 403/404/419/429/500/503;
 * orice alt cod cade pe varianta `generic`. Limba e setată în handler din prefixul URL,
 * deci `useTranslations()` rezolvă corect RO/RU/EN chiar și în afara middleware-ului de rute.
 *
 * Ritm de culoare ca restul site-ului: BANDA 1 navy (identitatea erorii) → BANDA 2 albă
 * (explorare), chiar înainte de footer. Ilustrația-emblemă are o animație RELEVANTĂ pe cod
 * (404 = busolă debusolată; 403 refuz; 419 ceas; 429 turometru; 500 glitch; 503 cheie), iar
 * în fundal plutesc simboluri de învățământ (albe). Mișcarea e CSS pur (`app.css`), DOAR sub
 * `prefers-reduced-motion: no-preference`.
 */

const KNOWN: readonly number[] = [403, 404, 419, 429, 500, 503];

/** Iconița-emblemă pe cod (404/necunoscut folosesc busola custom de mai jos). */
const ICONS: Record<string, LucideIcon> = {
    '403': Lock,
    '419': Clock,
    '429': Gauge,
    '500': ServerCrash,
    '503': Wrench,
};

/** Animația relevantă a emblemei pe cod (clasă definită în app.css). */
const ICON_ANIM: Record<string, string> = {
    '403': 'err-anim-shake',
    '419': 'err-anim-spin',
    '429': 'err-anim-rev',
    '500': 'err-anim-glitch',
    '503': 'err-anim-wrench',
};

/** Busolă „debusolată" — acul se rotește eratic căutând nordul (efect logic pentru 404). */
function CompassIllustration() {
    return (
        <svg
            viewBox="0 0 100 100"
            className="size-12"
            fill="none"
            aria-hidden="true"
        >
            <circle
                cx="50"
                cy="50"
                r="45"
                stroke="currentColor"
                strokeOpacity="0.45"
                strokeWidth="3"
            />
            <g
                stroke="currentColor"
                strokeOpacity="0.4"
                strokeWidth="3"
                strokeLinecap="round"
            >
                <line x1="50" y1="7" x2="50" y2="15" />
                <line x1="50" y1="85" x2="50" y2="93" />
                <line x1="7" y1="50" x2="15" y2="50" />
                <line x1="85" y1="50" x2="93" y2="50" />
            </g>
            <g className="err-needle">
                <path
                    d="M50 17 L56.5 50 L50 45.5 L43.5 50 Z"
                    style={{ fill: 'var(--brand-green)' }}
                />
                <path
                    d="M50 83 L43.5 50 L50 54.5 L56.5 50 Z"
                    fill="currentColor"
                    fillOpacity="0.5"
                />
            </g>
            <circle cx="50" cy="50" r="4.5" fill="currentColor" />
        </svg>
    );
}

/** Simboluri de învățământ care plutesc lent în banda navy (albe, pur decorativ). */
const GLYPHS: {
    Icon: LucideIcon;
    cls: string;
    dur: string;
    delay: string;
    op: number;
}[] = [
    {
        Icon: GraduationCap,
        cls: 'top-[18%] left-[8%] size-9',
        dur: '15s',
        delay: '0s',
        op: 0.15,
    },
    {
        Icon: BookOpen,
        cls: 'top-[56%] left-[6%] size-8',
        dur: '13s',
        delay: '1.2s',
        op: 0.11,
    },
    {
        Icon: Feather,
        cls: 'bottom-[12%] left-[17%] size-7',
        dur: '12s',
        delay: '2.1s',
        op: 0.13,
    },
    {
        Icon: Atom,
        cls: 'top-[12%] left-[30%] size-6',
        dur: '10s',
        delay: '1.5s',
        op: 0.14,
    },
    {
        Icon: Globe,
        cls: 'top-[10%] right-[33%] size-6',
        dur: '12s',
        delay: '2.3s',
        op: 0.12,
    },
    {
        Icon: Sun,
        cls: 'top-[15%] right-[9%] size-8',
        dur: '16s',
        delay: '0.6s',
        op: 0.12,
    },
    {
        Icon: Ruler,
        cls: 'top-[52%] right-[6%] size-7',
        dur: '14s',
        delay: '1.9s',
        op: 0.11,
    },
    {
        Icon: PenTool,
        cls: 'right-[16%] bottom-[14%] size-7',
        dur: '13s',
        delay: '1.7s',
        op: 0.12,
    },
    {
        Icon: Lightbulb,
        cls: 'right-[30%] bottom-[30%] size-6',
        dur: '11s',
        delay: '0.4s',
        op: 0.15,
    },
];

/** Pagini utile (banda albă) — etichete reutilizate din i18n-ul de navigație, rute reale. */
const EXPLORE: {
    href: string;
    Icon: LucideIcon;
    titleKey: string;
    titleFallback: string;
    descKey: string;
    descFallback: string;
}[] = [
    {
        href: '/',
        Icon: Home,
        titleKey: 'breadcrumb.home',
        titleFallback: 'Acasă',
        descKey: 'error_page.explore.home',
        descFallback: 'Pagina principală a liceului',
    },
    {
        href: '/admitere',
        Icon: GraduationCap,
        titleKey: 'nav.admission',
        titleFallback: 'Admitere',
        descKey: 'error_page.explore.admission',
        descFallback: 'Pași, acte necesare și înscriere online',
    },
    {
        href: '/galerie',
        Icon: ImageIcon,
        titleKey: 'nav.gallery',
        titleFallback: 'Galerie',
        descKey: 'error_page.explore.gallery',
        descFallback: 'Momente din viața școlii noastre',
    },
    {
        href: '/contacte',
        Icon: Mail,
        titleKey: 'utility.contact',
        titleFallback: 'Contacte',
        descKey: 'error_page.explore.contact',
        descFallback: 'Adresă, telefon și formular de contact',
    },
];

/** Întârziere de intrare (cascadă) ca variabilă CSS inline. */
const rise = (ms: number): CSSProperties =>
    ({ '--err-delay': `${ms}ms` }) as CSSProperties;

export default function ErrorPage({ status }: { status: number }) {
    const t = useTranslations();
    const key = KNOWN.includes(status) ? String(status) : 'generic';
    const isCompass = key === '404' || key === 'generic';
    const Icon = ICONS[key];

    return (
        <>
            <Head
                title={`${t('error_page.eyebrow', 'Eroare')} ${status} — ${t(`error_page.status.${key}.title`)}`}
            >
                <meta name="robots" content="noindex, follow" />
            </Head>

            {/* BANDA 1 — navy: identitatea erorii, cu simboluri educaționale care plutesc lent. */}
            <Band
                variant="navy"
                pattern="mesh"
                className="relative flex min-h-[60vh] items-center overflow-hidden"
            >
                {/* Strat de simboluri educaționale (albe) — tocă, carte, peniță, atom, glob, soare, riglă, creion, idee. */}
                <div
                    className="pointer-events-none absolute inset-0 overflow-hidden"
                    aria-hidden="true"
                >
                    {GLYPHS.map((g) => (
                        <span
                            key={g.cls}
                            data-err-float
                            className={cn('absolute text-white', g.cls)}
                            style={
                                {
                                    opacity: g.op,
                                    '--err-dur': g.dur,
                                    '--err-delay': g.delay,
                                    '--err-op': String(g.op),
                                } as CSSProperties
                            }
                        >
                            <g.Icon
                                className="size-full"
                                strokeWidth={1.5}
                                aria-hidden="true"
                            />
                        </span>
                    ))}
                </div>

                <div className="relative z-10 mx-auto flex max-w-2xl flex-col items-center text-center">
                    {/* Emblemă cu ilustrație animată, relevantă tipului de eroare. */}
                    <span
                        data-err-rise
                        style={rise(0)}
                        className="grid size-20 place-items-center rounded-[1.375rem] bg-white/[0.06] text-white ring-1 ring-white/15"
                    >
                        {isCompass || !Icon ? (
                            <CompassIllustration />
                        ) : (
                            <Icon
                                className={cn(
                                    'size-10 text-brand-green',
                                    ICON_ANIM[key],
                                )}
                                strokeWidth={1.75}
                                aria-hidden="true"
                            />
                        )}
                    </span>

                    <span
                        data-err-numeral
                        style={rise(80)}
                        className="numeral mt-6 text-[clamp(4.5rem,18vw,9rem)] leading-none text-[color:var(--brand-navy-foreground)]"
                    >
                        {status}
                    </span>

                    <span
                        data-err-rise
                        style={rise(150)}
                        className="mt-4 flex items-center gap-3"
                    >
                        <span
                            className="h-px w-10 bg-white/30"
                            aria-hidden="true"
                        />
                        <FourStar className="size-3 text-brand-green" />
                        <span className="eyebrow text-brand-green">
                            {t('error_page.eyebrow', 'Eroare')} {status}
                        </span>
                        <FourStar className="size-3 text-brand-green" />
                        <span
                            className="h-px w-10 bg-white/30"
                            aria-hidden="true"
                        />
                    </span>

                    <h1
                        data-err-rise
                        style={rise(210)}
                        className="display mt-5 text-[clamp(1.75rem,4vw,2.75rem)] text-[color:var(--brand-navy-foreground)]"
                    >
                        {t(`error_page.status.${key}.title`)}
                    </h1>
                    <p
                        data-err-rise
                        style={rise(270)}
                        className="mt-4 max-w-[52ch] text-[clamp(1rem,1.6vw,1.125rem)] leading-relaxed text-white/85"
                    >
                        {t(`error_page.status.${key}.body`)}
                    </p>

                    <div
                        data-err-rise
                        style={rise(350)}
                        className="mt-8 flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-center"
                    >
                        <BrandButton
                            href="/"
                            variant="primary"
                            icon={Home}
                            className="w-full justify-center sm:w-auto"
                        >
                            {t('error_page.home', 'Pagina principală')}
                        </BrandButton>
                        <BrandButton
                            onClick={() => window.history.back()}
                            variant="ghost-navy"
                            icon={ArrowLeft}
                            className="w-full justify-center sm:w-auto"
                        >
                            {t('error_page.back', 'Pagina anterioară')}
                        </BrandButton>
                    </div>
                </div>
            </Band>

            {/* BANDA 2 — albă: pagini utile pentru a-ți relua drumul, chiar înainte de footer. */}
            <Band variant="light" pattern="signature">
                <Reveal className="mx-auto max-w-2xl text-center">
                    <div className="flex items-center justify-center gap-3">
                        <span
                            className="h-px w-8 bg-brand-navy/15"
                            aria-hidden="true"
                        />
                        <FourStar className="size-3 text-brand-green" />
                        <span className="eyebrow text-brand-navy">
                            {t('error_page.helpful', 'Sau continuă spre')}
                        </span>
                        <FourStar className="size-3 text-brand-green" />
                        <span
                            className="h-px w-8 bg-brand-navy/15"
                            aria-hidden="true"
                        />
                    </div>
                    <h2 className="display mt-4 text-[clamp(1.5rem,3vw,2.25rem)] text-brand-navy">
                        {t('error_page.explore.title', 'Sau explorează liceul')}
                    </h2>
                    <p className="mt-3 leading-relaxed text-brand-gray">
                        {t(
                            'error_page.explore.lead',
                            'Câteva pagini care te-ar putea ajuta să găsești ce căutai.',
                        )}
                    </p>
                </Reveal>

                <Reveal className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {EXPLORE.map((c) => (
                        <LocaleLink
                            key={c.href}
                            href={c.href}
                            className="group keyline flex h-full flex-col rounded-[12px] border border-l-[5px] border-l-brand-navy bg-card p-6 transition-all hover:-translate-y-0.5 hover:border-l-brand-green"
                        >
                            <span className="grid size-11 place-items-center rounded-xl bg-brand-navy/[0.06] text-brand-navy transition-colors group-hover:bg-brand-green/15">
                                <c.Icon className="size-5" aria-hidden="true" />
                            </span>
                            <span className="mt-4 flex items-center justify-between gap-2">
                                <h3 className="display text-[1.25rem] text-brand-navy">
                                    {t(c.titleKey, c.titleFallback)}
                                </h3>
                                <ArrowRight
                                    className="size-4 shrink-0 -translate-x-1 text-brand-green opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                    aria-hidden="true"
                                />
                            </span>
                            <p className="mt-1.5 text-sm leading-relaxed text-brand-gray">
                                {t(c.descKey, c.descFallback)}
                            </p>
                        </LocaleLink>
                    ))}
                </Reveal>
            </Band>
        </>
    );
}
