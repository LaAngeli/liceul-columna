import { useEffect, useState } from 'react';
import { useCountUp } from '@/hooks/use-count-up';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const R = 52;
const CIRCUMFERENCE = 2 * Math.PI * R;
// Cadran de tip vitezometru: arc vizibil de 270° (gol de 90° centrat jos), scala 1–10.
const ARC = 0.75 * CIRCUMFERENCE;

/**
 * Cadranul mediei (0–10) — semnătura variantei „spotlight". Umple un arc navy de 270° până la
 * poziția mediei, cu semnul VERDE al pragului de promovare (nota 5) în vârf — unicul accent de
 * brand, pe reperul care poartă informație. Numărul din centru numără crescător la montare, iar
 * arcul crește de la 0 (motion-safe → inert sub `prefers-reduced-motion`).
 *
 * Reîntărire vizuală a numărului deja în text → SVG-ul e `aria-hidden`.
 */
export function GradeDial({
    average,
    trendSymbol,
    trendClass,
    trendTitle,
    className,
}: {
    average: number | null;
    trendSymbol?: string | null;
    trendClass?: string;
    trendTitle?: string;
    className?: string;
}) {
    const t = useTranslations();
    const animated = useCountUp(average);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const id = requestAnimationFrame(() => setMounted(true));

        return () => cancelAnimationFrame(id);
    }, []);

    const pct = average === null ? 0 : Math.max(0, Math.min(1, average / 10));
    // Offset animabil: de la ARC (arc gol) la ARC*(1-pct) (arc plin până la medie).
    const offset = mounted ? ARC * (1 - pct) : ARC;

    const decimals = average !== null ? (String(average).split('.')[1]?.length ?? 0) : 0;
    const shown = animated !== null ? animated.toFixed(decimals) : null;
    const [intPart, decPart] = shown !== null ? shown.split('.') : ['—', undefined];

    return (
        <div className={cn('relative size-32 shrink-0', className)}>
            <svg viewBox="0 0 120 120" className="size-full" aria-hidden="true">
                {/* Șina (arc de 270°, gol centrat jos) */}
                <circle
                    cx="60"
                    cy="60"
                    r={R}
                    fill="none"
                    className="text-muted"
                    stroke="currentColor"
                    strokeWidth="9"
                    strokeLinecap="round"
                    strokeDasharray={`${ARC} ${CIRCUMFERENCE}`}
                    transform="rotate(135 60 60)"
                />
                {/* Umplerea navy — crește lin de la 0 la poziția mediei */}
                <circle
                    cx="60"
                    cy="60"
                    r={R}
                    fill="none"
                    className="text-brand-navy transition-[stroke-dashoffset] duration-1000 ease-out motion-reduce:transition-none"
                    stroke="currentColor"
                    strokeWidth="9"
                    strokeLinecap="round"
                    strokeDasharray={`${ARC} ${CIRCUMFERENCE}`}
                    strokeDashoffset={offset}
                    transform="rotate(135 60 60)"
                />
                {/* Semnul verde al pragului de promovare (nota 5 = vârful arcului) */}
                <line x1="60" y1="4" x2="60" y2="17" className="text-brand-green" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round">
                    <title>{`${t('cabinet.cockpit_pass_line')} · 5`}</title>
                </line>
            </svg>
            <div className="absolute inset-0 flex items-center justify-center">
                <span className="flex items-end leading-none">
                    <span className="text-3xl font-bold tabular-nums">{intPart}</span>
                    {decPart !== undefined && (
                        <span className="pb-0.5 text-lg font-semibold tabular-nums text-muted-foreground">.{decPart}</span>
                    )}
                    {trendSymbol && (
                        <span className={cn('pb-1 pl-0.5 text-xs font-semibold', trendClass)} title={trendTitle}>
                            {trendSymbol}
                        </span>
                    )}
                </span>
            </div>
        </div>
    );
}
