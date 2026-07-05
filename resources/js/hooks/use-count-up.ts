import { useEffect, useState } from 'react';

/**
 * Numără crescător de la 0 la `target` la montare (efect de „fișă care se completează").
 * Nativ — `requestAnimationFrame`, fără librărie de animație.
 *
 * - Respectă `prefers-reduced-motion`: sare direct la valoarea finală, fără animație.
 * - `target === null` → întoarce `null` (starea „fără medie încă").
 * - Ease-out cubic pentru o oprire lină pe valoarea finală.
 *
 * Toate actualizările de stare se fac în callback-uri `requestAnimationFrame` (niciodată sincron
 * în corpul efectului) — primul cadru al buclei pornește de la ~0, deci nu apare flicker.
 *
 * @param target   Valoarea finală (ex. media generală), sau null dacă lipsește.
 * @param duration Durata animației în ms (implicit 700).
 */
export function useCountUp(target: number | null, duration = 700): number | null {
    const [value, setValue] = useState<number | null>(() => {
        if (target === null) {
            return null;
        }

        // Prima randare: 0 când vom anima (numărătoarea pornește curat de la 0), dar direct valoarea
        // finală când mișcarea e dezactivată (reduced-motion) sau lipsește window.
        const prefersReduced =
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        return prefersReduced ? target : 0;
    });

    useEffect(() => {
        if (target === null) {
            const id = requestAnimationFrame(() => setValue(null));

            return () => cancelAnimationFrame(id);
        }

        const prefersReduced =
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduced || duration <= 0) {
            const id = requestAnimationFrame(() => setValue(target));

            return () => cancelAnimationFrame(id);
        }

        let start: number | null = null;
        let raf = requestAnimationFrame(function tick(now: number): void {
            if (start === null) {
                start = now;
            }

            const t = Math.min(1, (now - start) / duration);
            // Ease-out cubic: rapid la început, se așază lin pe valoarea finală.
            const eased = 1 - Math.pow(1 - t, 3);
            setValue(target * eased);

            if (t < 1) {
                raf = requestAnimationFrame(tick);
            } else {
                setValue(target);
            }
        });

        return () => cancelAnimationFrame(raf);
    }, [target, duration]);

    return value;
}
