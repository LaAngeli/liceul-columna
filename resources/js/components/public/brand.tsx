/**
 * Primitive de design „Columna Civic Editorial" (site public).
 * Sistemul: navy = ground, Cervino = display, keyline 1px navy/12%, section-index rail,
 * verdele DOAR ca fill/accent (niciodată text pe alb). Toate trăiesc sub `.site-shell`.
 */
import { Link } from '@inertiajs/react';
import {   useEffect, useRef, useState } from 'react';
import type {ComponentType, ReactNode} from 'react';
import { LocaleLink } from '@/components/locale-link';
import { cn } from '@/lib/utils';

/* ------------------------------------------------------------------ layout */

export function Container({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('site-container', className)}>{children}</div>;
}

/** Bandă full-bleed, ritm vertical editorial, variantă navy/deschis. */
export function Band({
    children,
    variant = 'light',
    className,
    pattern,
    id,
}: {
    children: ReactNode;
    variant?: 'light' | 'navy';
    className?: string;
    /**
     * Textura de fundal a benzii (site public):
     * - `mesh` = plasă rafinată de micro-puncte, ancorată în colț (sistemul universal);
     * - `signature` = plasa + constelația de brand (steluțe) — pentru benzile-semnătură;
     * - `dotgrid` = vechea plasă verde (păstrată pt. paginile care o folosesc deja);
     * - `none`/absent = fără textură.
     */
    pattern?: 'mesh' | 'signature' | 'dotgrid' | 'none';
    id?: string;
}) {
    const navy = variant === 'navy';
    const textured = pattern === 'mesh' || pattern === 'signature';

    return (
        <section
            id={id}
            className={cn(
                'relative py-[clamp(3.5rem,8vw,8rem)]',
                navy ? 'on-navy bg-surface-navy text-[color:var(--brand-navy-foreground)]' : 'bg-background text-[color:var(--brand-dark)]',
                className,
            )}
        >
            {pattern === 'dotgrid' && navy && (
                <div className="dotgrid pointer-events-none absolute inset-0 opacity-[0.14]" aria-hidden="true" />
            )}
            {textured && (
                <div className={cn('pointer-events-none absolute inset-0', navy ? 'tx-mesh-navy' : 'tx-mesh-lite')} aria-hidden="true" />
            )}
            {pattern === 'signature' && (
                <div className={cn('pointer-events-none absolute inset-0', navy ? 'tx-constellation-navy' : 'tx-constellation-lite')} aria-hidden="true" />
            )}
            <Container className="relative">{children}</Container>
        </section>
    );
}

/* ------------------------------------------------------ section-index rail */

/** Antet de secțiune cu rail-ul de index: „01 —" + etichetă + linie 1px + steluță. */
export function SectionHeader({
    index,
    label,
    title,
    lead,
    variant = 'light',
    align = 'start',
    className,
}: {
    index: string;
    label: string;
    title?: ReactNode;
    lead?: ReactNode;
    variant?: 'light' | 'navy';
    align?: 'start' | 'center';
    className?: string;
}) {
    const navy = variant === 'navy';

    return (
        <Reveal className={cn('flex flex-col gap-4', align === 'center' && 'items-center text-center', className)}>
            <div className={cn('flex items-center gap-3', align === 'center' && 'justify-center')}>
                <span className={cn('eyebrow', navy ? 'text-[color:var(--brand-navy-foreground)]' : 'text-brand-navy')}>
                    {index} — {label}
                </span>
                <span data-rule className={cn('h-px w-12 origin-left', navy ? 'bg-white/30' : 'keyline border-t')} aria-hidden="true" />
                <FourStar className={cn('size-3', navy ? 'text-brand-green' : 'text-brand-green')} />
            </div>
            {title && (
                <h2 className={cn('display text-[clamp(1.5rem,3vw,2.375rem)]', navy ? 'text-[color:var(--brand-navy-foreground)]' : 'text-brand-navy')}>
                    {title}
                </h2>
            )}
            {lead && (
                <p className={cn('max-w-[60ch] text-[clamp(1.125rem,1.6vw,1.25rem)] leading-relaxed', navy ? 'text-white/85' : 'text-brand-gray')}>
                    {lead}
                </p>
            )}
        </Reveal>
    );
}

/* ------------------------------------------------------------- typography */

export function Display({
    children,
    as: As = 'h2',
    className,
    dynamic = false,
}: {
    children: ReactNode;
    as?: 'h1' | 'h2' | 'h3' | 'p' | 'span';
    className?: string;
    dynamic?: boolean;
}) {
    return <As className={cn(dynamic ? 'heading-dynamic' : 'display', className)}>{children}</As>;
}

/* ----------------------------------------------------------------- emblem */

/** Steluța cu 4 colțuri — fleuron-ul recurent al sistemului. */
export function FourStar({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" className={cn('inline-block', className)} fill="currentColor" aria-hidden="true">
            <path d="M12 0c.8 6.6 4.6 10.4 11.2 11.2-6.6.8-10.4 4.6-11.2 11.2-.8-6.6-4.6-10.4-11.2-11.2C7.4 10.4 11.2 6.6 12 0z" />
        </svg>
    );
}

/** Rombul — purtătorul sensului „3 trepte" (primar/gimnazial/liceal). */
export function Rhombus({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" className={cn('inline-block', className)} fill="currentColor" aria-hidden="true">
            <path d="M12 1.5 22.5 12 12 22.5 1.5 12z" />
        </svg>
    );
}

/* ----------------------------------------------------------------- button */

type BtnVariant = 'primary' | 'ghost' | 'ghost-navy' | 'link' | 'link-navy';

const BTN: Record<BtnVariant, string> = {
    primary: 'bg-brand-green text-[color:var(--brand-green-foreground)] font-semibold shadow-sm hover:brightness-[1.04]',
    ghost: 'border border-brand-navy text-brand-navy font-semibold hover:bg-surface-navy hover:text-[color:var(--brand-navy-foreground)]',
    'ghost-navy': 'border border-white/70 text-[color:var(--brand-navy-foreground)] font-semibold hover:bg-white/10',
    link: 'text-brand-navy font-semibold underline decoration-brand-green decoration-2 underline-offset-4 hover:decoration-[3px]',
    'link-navy': 'text-[color:var(--brand-navy-foreground)] font-semibold underline decoration-brand-green decoration-2 underline-offset-4',
};

export function BrandButton({
    children,
    href,
    external,
    onClick,
    variant = 'primary',
    icon: Icon,
    className,
    type = 'button',
    disabled = false,
}: {
    children: ReactNode;
    href?: string;
    external?: boolean;
    onClick?: () => void;
    variant?: BtnVariant;
    icon?: ComponentType<{ className?: string }>;
    className?: string;
    type?: 'button' | 'submit';
    disabled?: boolean;
}) {
    const isLink = variant === 'link' || variant === 'link-navy';
    // whitespace-nowrap doar pe varianta pill (nu pe link, care trebuie să curgă în text):
    // fără el, un flex item cu text ce are un spațiu poate primi o lățime „preferată" mai mică
    // decât max-content și se rupe pe 2 rânduri chiar când ar avea loc pe unul (constatat cu
    // RU „Запись на визит" în CTA-ul din header — text mai scurt ca RO, dar se rupea la 153px).
    const base = isLink
        ? 'inline-flex min-h-11 items-center gap-1.5 transition-all'
        : 'inline-flex min-h-11 items-center justify-center gap-2 rounded-[12px] px-5 py-2.5 whitespace-nowrap transition-all active:scale-[0.98]';
    const cls = cn(base, BTN[variant], disabled && 'pointer-events-none opacity-60', className);
    const inner = (
        <>
            {children}
            {Icon && <Icon className="size-4 shrink-0" />}
        </>
    );

    if (href) {
        if (external || href.startsWith('http') || href.startsWith('tel:') || href.startsWith('mailto:') || href.startsWith('/admin')) {
            return (
                <a href={href} className={cls} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
                    {inner}
                </a>
            );
        }

        if (href.startsWith('/dashboard') || href.startsWith('/login')) {
            return (
                <Link href={href} className={cls}>
                    {inner}
                </Link>
            );
        }

        return (
            <LocaleLink href={href} className={cls}>
                {inner}
            </LocaleLink>
        );
    }

    return (
        <button type={type} onClick={onClick} disabled={disabled} className={cls}>
            {inner}
        </button>
    );
}

/* ----------------------------------------------------------------- reveal */

/** Scroll-reveal (fade-up, o singură dată). Sub reduced-motion CSS-ul lasă conținutul vizibil. */
export function Reveal({
    children,
    className,
    as: As = 'div',
    rule = false,
}: {
    children: ReactNode;
    className?: string;
    as?: 'div' | 'section' | 'li' | 'article';
    rule?: boolean;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [seen, setSeen] = useState(false);
    useEffect(() => {
        const el = ref.current;

        if (!el || seen) {
return;
}

        const io = new IntersectionObserver(
            (entries) => {
                for (const e of entries) {
                    if (e.isIntersecting) {
                        el.classList.add('is-in');
                        el.querySelectorAll('[data-rule]').forEach((r) => r.classList.add('is-in'));
                        setSeen(true);
                        io.disconnect();
                    }
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
        );
        io.observe(el);

        return () => io.disconnect();
    }, [seen]);

    return (
        <As ref={ref as never} data-reveal="" {...(rule ? { 'data-rule': '' } : {})} className={className}>
            {children}
        </As>
    );
}

/* ------------------------------------------------------------ value chips */

const VALUE_KEYS = ['credinta', 'onoare', 'libertate', 'unire', 'munca', 'natiune', 'adevar'] as const;
const VALUE_FALLBACK: Record<string, string> = {
    credinta: 'Credință', onoare: 'Onoare', libertate: 'Libertate', unire: 'Unire', munca: 'Munca', natiune: 'Națiune', adevar: 'Adevăr',
};

/** Cele 7 valori ca banner heraldic: chips navy/verde/contur, separate de steluțe. */
export function ValueChips({ t, className }: { t: (k: string, f?: string) => string; className?: string }) {
    return (
        <ul className={cn('flex flex-wrap items-center gap-x-2 gap-y-3', className)}>
            {VALUE_KEYS.map((key, i) => {
                const fill = i % 3;
                const chip =
                    fill === 0
                        ? 'bg-surface-navy text-[color:var(--brand-navy-foreground)]'
                        : fill === 1
                          ? 'bg-brand-green text-[color:var(--brand-green-foreground)]'
                          : 'border border-white/60 text-[color:var(--brand-navy-foreground)]';

                return (
                    <li key={key} className="flex items-center gap-2">
                        <span className={cn('rounded-md px-3 py-1.5 text-[0.8125rem] font-semibold tracking-[0.12em] uppercase', chip)} style={{ fontFamily: 'var(--font-display)' }}>
                            {t(`values.${key}`, VALUE_FALLBACK[key])}
                        </span>
                        {i < VALUE_KEYS.length - 1 && <FourStar className="size-2.5 text-brand-green" />}
                    </li>
                );
            })}
        </ul>
    );
}

/* ------------------------------------------------------------ stat ribbon */

export interface StatItem {
    value: string; // ex. "1998", "3", "27"
    suffix?: string; // ex. "+", "%"
    label: string;
    accent?: boolean; // bloc verde ceremonial
    icon?: 'rhombi'; // marker 3-trepte
}

/** Ribbon edge-to-edge divizat de keyline-uri 1px; numerale Cervino cu count-up o dată. */
export function StatRibbon({ items, className }: { items: StatItem[]; className?: string }) {
    return (
        <Reveal
            className={cn('grid grid-cols-1 overflow-hidden rounded-[12px] border keyline sm:grid-cols-2', items.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3', className)}
        >
            {items.map((s, i) => (
                <div
                    key={s.label}
                    className={cn(
                        'flex flex-col gap-2 p-6 sm:p-8',
                        i % 2 === 1 && 'border-t keyline sm:border-t-0 sm:border-l',
                        i >= 2 && 'border-t keyline lg:border-t-0 lg:border-l',
                        s.accent ? 'bg-brand-green text-[color:var(--brand-green-foreground)]' : 'bg-card',
                    )}
                >
                    <div className="flex items-end gap-2">
                        {s.icon === 'rhombi' && (
                            <span className="mb-2 flex items-center gap-0.5 text-brand-green" aria-hidden="true">
                                <Rhombus className="size-3" /> <Rhombus className="size-3.5" /> <Rhombus className="size-3" />
                            </span>
                        )}
                        <CountUp value={s.value} className={cn('numeral text-[clamp(2.5rem,6vw,4.5rem)]', s.accent ? 'text-[color:var(--brand-green-foreground)]' : 'text-brand-navy')} />
                        {s.suffix && <span className="numeral mb-2 text-[clamp(1.5rem,3vw,2.25rem)] text-brand-green">{s.suffix}</span>}
                    </div>
                    <span className={cn('text-sm', s.accent ? 'text-[color:var(--brand-green-foreground)]/80' : 'text-brand-gray')}>{s.label}</span>
                </div>
            ))}
        </Reveal>
    );
}

/** Numeral cu count-up la prima intrare în viewport (respectă reduced-motion). */
function CountUp({ value, className }: { value: string; className?: string }) {
    const ref = useRef<HTMLSpanElement>(null);
    // Anii (ex. „2019") nu fac count-up de la 0 — doar contoarele reale (27, 3).
    const isYear = /^(19|20)\d{2}$/.test(value);
    const numeric = !isYear && /^\d+$/.test(value) ? parseInt(value, 10) : null;
    const [display, setDisplay] = useState(numeric === null ? value : '0');
    useEffect(() => {
        if (numeric === null) {
return;
}

        const el = ref.current;

        if (!el) {
return;
}

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDisplay(String(numeric));

            return;
        }

        const io = new IntersectionObserver((entries) => {
            for (const e of entries) {
                if (e.isIntersecting) {
                    const start = performance.now();
                    const dur = 900;
                    const tick = (now: number) => {
                        const p = Math.min(1, (now - start) / dur);
                        const eased = 1 - Math.pow(1 - p, 3);
                        setDisplay(String(Math.round(eased * numeric)));

                        if (p < 1) {
requestAnimationFrame(tick);
}
                    };
                    requestAnimationFrame(tick);
                    io.disconnect();
                }
            }
        });
        io.observe(el);

        return () => io.disconnect();
    }, [numeric]);

    return (
        <span ref={ref} className={className}>
            {display}
        </span>
    );
}
