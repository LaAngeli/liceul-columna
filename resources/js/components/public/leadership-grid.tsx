/**
 * Grila „Conducerea liceului" (homepage, secțiunea 07).
 * - 4 plăci pe orizontală, cu bare (spine) de înălțime EGALĂ (grid items-stretch + h-full;
 *   rolul e plafonat la 2 rânduri, deci înălțimea e fixată de director).
 * - Daniță Ghenadie (members[0]) e PINNED mereu pe prima poziție; celelalte 3 sloturi
 *   rotesc LIVE membri aleși random din restul echipei, fiecare adus în loc al unuia ascuns
 *   (fără dubluri vizibile).
 * - Animație: crossfade SECVENȚIAL — slotul 1 dispare treptat și apare altul, apoi slotul 2,
 *   apoi slotul 3; după ce ciclul se închide se așteaptă ~5s și se reia. Doar tranziții de
 *   `opacity` pe sloturi FIXE (compozit GPU, fără reflow/layout shift → performant).
 * - Bune practici: rulează DOAR în viewport (IntersectionObserver), se oprește la hover/focus,
 *   respectă `prefers-reduced-motion` (un singur amestec, fără rotație).
 */
import { useEffect, useRef, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export interface LeadershipMember {
    name: string;
    role: string;
    slug: string | null;
    photo: string | null;
}

const ROTATE_SLOTS = 3;
const FADE_MS = 380; // durata fade-out / fade-in
const STAGGER_MS = 1100; // pauză între sloturi în cadrul unui ciclu (> 2× FADE → secvențial)
const CYCLE_PAUSE_MS = 6000; // pauză după închiderea ciclului
const START_DELAY_MS = 2500; // întârziere inițială (lasă pagina să se așeze)

function shuffle<T>(arr: T[]): T[] {
    const r = [...arr];
    for (let i = r.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [r[i], r[j]] = [r[j], r[i]];
    }
    return r;
}

function Plate({ m }: { m: LeadershipMember }) {
    const getInitials = useInitials();
    const inner = (
        <>
            {m.photo ? (
                <div className="photo-frame aspect-[4/5] overflow-hidden rounded-[10px] border keyline">
                    <img src={m.photo} alt={m.name} loading="lazy" className="h-full w-full object-cover" />
                </div>
            ) : (
                <div className="flex aspect-[4/5] items-center justify-center rounded-[10px] border keyline bg-brand-navy/5">
                    <span className="text-3xl font-bold text-brand-navy/40" style={{ fontFamily: 'var(--font-display)' }}>{getInitials(m.name)}</span>
                </div>
            )}
            <div className="mt-3">
                <h3 className="heading-dynamic text-base text-brand-navy">{m.name}</h3>
                <p className="eyebrow mt-1 line-clamp-2 text-brand-gray">{m.role}</p>
            </div>
        </>
    );
    const cls = 'flex h-full flex-col border-l-[5px] border-l-brand-navy pl-4 transition-colors';
    return m.slug ? (
        <LocaleLink href={`/${m.slug}`} className={cn(cls, 'group hover:border-l-brand-green')}>
            {inner}
        </LocaleLink>
    ) : (
        <div className={cls}>{inner}</div>
    );
}

export function LeadershipGrid({ members }: { members: LeadershipMember[] }) {
    const director = members[0];
    const pool = members.slice(1);

    // Init determinist (SSR-safe); randomizăm după montare ca să evităm hydration mismatch.
    const [shown, setShown] = useState<LeadershipMember[]>(() => pool.slice(0, ROTATE_SLOTS));
    const [op, setOp] = useState<number[]>(() => Array(ROTATE_SLOTS).fill(1));
    const shownRef = useRef<LeadershipMember[]>(pool.slice(0, ROTATE_SLOTS));
    // „Sac" de membri de introdus în ciclul curent = cei care NU sunt vizibili acum. Nimeni nu
    // reapare până nu a fost afișată toată echipa; la golirea sacului se reumple (ciclu nou).
    const bagRef = useRef<LeadershipMember[]>([]);
    const pausedRef = useRef(false);
    const inViewRef = useRef(false);
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        // Primul amestec: 3 vizibili + restul în „sac" (de introdus în ciclul curent, fără repetare).
        const deck = shuffle(pool);
        const first = deck.slice(0, ROTATE_SLOTS);
        shownRef.current = first;
        setShown(first);
        bagRef.current = deck.slice(ROTATE_SLOTS);

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || pool.length <= ROTATE_SLOTS) return;

        const el = rootRef.current;
        let io: IntersectionObserver | undefined;
        if (el) {
            io = new IntersectionObserver((entries) => {
                inViewRef.current = entries[0]?.isIntersecting ?? false;
            }, { threshold: 0.25 });
            io.observe(el);
        }

        let stepTimer = 0;
        let fadeTimer = 0;
        let cursor = 0;

        // Următorul membru de afișat = scos din „sac". Când sacul se golește (toată echipa a fost
        // afișată în ciclul curent), îl reumplem DOAR cu cei care NU sunt vizibili acum → nimeni nu
        // se repetă până nu trece tot ciclul; abia apoi pot reapărea (ciclu nou).
        const nextFromBag = (): LeadershipMember | undefined => {
            if (!bagRef.current.length) {
                const shownNames = new Set(shownRef.current.map((m) => m.name));
                bagRef.current = shuffle(pool.filter((m) => !shownNames.has(m.name)));
            }
            return bagRef.current.shift();
        };

        const swapSlot = (slot: number) => {
            const next = nextFromBag();
            if (!next) return;

            setOp((prev) => prev.map((v, i) => (i === slot ? 0 : v))); // fade-out
            fadeTimer = window.setTimeout(() => {
                setShown((prev) => {
                    const arr = prev.map((m, i) => (i === slot ? next : m));
                    shownRef.current = arr;
                    return arr;
                });
                setOp((prev) => prev.map((v, i) => (i === slot ? 1 : v))); // fade-in
            }, FADE_MS);
        };

        const step = () => {
            if (!inViewRef.current || pausedRef.current) {
                stepTimer = window.setTimeout(step, 700); // amânăm fără să avansăm ciclul
                return;
            }
            const slot = cursor;
            swapSlot(slot);
            const wasLast = slot === ROTATE_SLOTS - 1;
            cursor = (slot + 1) % ROTATE_SLOTS;
            stepTimer = window.setTimeout(step, wasLast ? CYCLE_PAUSE_MS : STAGGER_MS);
        };

        stepTimer = window.setTimeout(step, START_DELAY_MS);

        return () => {
            window.clearTimeout(stepTimer);
            window.clearTimeout(fadeTimer);
            io?.disconnect();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (!director) return null;

    return (
        <div
            ref={rootRef}
            className="mt-8 grid grid-cols-2 items-stretch gap-4 sm:gap-5 lg:grid-cols-4"
            onMouseEnter={() => {
                pausedRef.current = true;
            }}
            onMouseLeave={() => {
                pausedRef.current = false;
            }}
            onFocusCapture={() => {
                pausedRef.current = true;
            }}
            onBlurCapture={() => {
                pausedRef.current = false;
            }}
        >
            <article className="h-full">
                <Plate m={director} />
            </article>
            {shown.map((m, i) => (
                <article key={i} className="h-full" style={{ opacity: op[i], transition: `opacity ${FADE_MS}ms ease`, willChange: 'opacity' }}>
                    <Plate m={m} />
                </article>
            ))}
        </div>
    );
}
