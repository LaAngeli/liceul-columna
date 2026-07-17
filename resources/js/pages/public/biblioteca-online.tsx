import { Head } from '@inertiajs/react';
import { ArrowUpRight, BookOpen, Library, Search, Sparkles, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Band, FourStar, Reveal, SectionHeader, StatRibbon } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Book {
    title: string;
    /** Autor livrat din DB (când e completat în Studio). Frontendul cade pe parsing „Autor — Titlu"
     *  doar pentru materialele legacy importate din columna.org.md, unde autorul e concatenat în titlu. */
    author?: string | null;
    url: string;
}
interface Category {
    key: string;
    title: string;
    kind: 'literature' | 'documents';
    count: number;
    books: Book[];
}
interface Crumb {
    title: string;
    href?: string;
}
interface Props {
    title: string;
    description?: string;
    breadcrumbs?: Crumb[];
    categories: Category[];
    totalBooks: number;
}

/** Carte „aplatizată" pentru căutare/filtrare/grupare. */
interface Entry {
    id: string;
    raw: string; // titlul original (pentru căutare)
    work: string; // titlul lucrării (după „— ") la literatură; altfel titlul întreg
    author: string; // autorul (înainte de „— ") — doar la literatură
    url: string;
    catKey: string;
    kind: 'literature' | 'documents';
    letter: string; // inițiala normalizată (index alfabetic)
    level: 'liceu' | 'gimnaziu' | 'primar' | 'other';
}

const DIACRITICS: Record<string, string> = { Ă: 'A', Â: 'A', Á: 'A', À: 'A', Î: 'I', Í: 'I', Ș: 'S', Ş: 'S', Ț: 'T', Ţ: 'T', É: 'E', È: 'E', Ó: 'O', Ú: 'U' };

function foldLetter(ch: string): string {
    const up = ch.toUpperCase();

    return DIACRITICS[up] ?? up;
}

function detectLevel(raw: string): Entry['level'] {
    const s = raw.toLowerCase();

    if (/\bprimar/.test(s)) {
return 'primar';
}

    if (/gimnaziu|clasele v|v-ix|vi-ix|vii-ix|a v-a/.test(s)) {
return 'gimnaziu';
}

    if (/liceu|clasele x|x-xii/.test(s)) {
return 'liceu';
}

    return 'other';
}

function initialsOf(text: string): string {
    const parts = text.replace(/[,.]/g, ' ').trim().split(/\s+/).filter(Boolean);

    if (parts.length >= 2) {
return (parts[0][0] + parts[1][0]).toUpperCase();
}

    return text.trim().slice(0, 2).toUpperCase();
}

function hash(s: string): number {
    let h = 0;

    for (let i = 0; i < s.length; i++) {
h = (h * 31 + s.charCodeAt(i)) | 0;
}

    return Math.abs(h);
}

/** Mini „cotor de carte" generat din culorile de brand (raft colorat, fără coperți reale). */
function Spine({ label, seed, glyph }: { label: string; seed: string; glyph?: boolean }) {
    const variant = hash(seed) % 3;
    const cls =
        variant === 0
            ? 'bg-surface-navy text-white'
            : variant === 1
              ? 'bg-brand-green text-[color:var(--brand-green-foreground)]'
              : 'bg-brand-navy/8 text-brand-navy border keyline';

    return (
        <span className={cn('relative flex h-14 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[5px]', cls)} aria-hidden="true">
            <span className="absolute inset-y-1.5 left-1.5 w-px bg-current opacity-30" />
            {glyph ? (
                <BookOpen className="size-5 opacity-80" />
            ) : (
                <span className="pl-1 text-sm leading-none tracking-tight" style={{ fontFamily: 'var(--font-display)' }}>
                    {label}
                </span>
            )}
        </span>
    );
}

function BookCard({ entry, catLabel, t }: { entry: Entry; catLabel?: string; t: (k: string, f?: string) => string }) {
    const isLit = entry.kind === 'literature';
    const cover = isLit ? initialsOf(entry.author || entry.work) : initialsOf(entry.work);
    const meta = isLit ? entry.author : entry.level !== 'other' ? t(`biblioteca.level.${entry.level}`) : (catLabel ?? '');

    return (
        <a
            href={entry.url}
            target="_blank"
            rel="noopener noreferrer"
            className="group flex items-center gap-3.5 rounded-[12px] border keyline border-l-[5px] border-l-transparent bg-card p-3 transition-all hover:-translate-y-0.5 hover:border-l-brand-green hover:shadow-[0_14px_30px_-22px_rgba(15,77,119,0.5)]"
        >
            <Spine label={cover} seed={entry.raw} glyph={!isLit && !cover} />
            <span className="min-w-0 flex-1">
                <span className="block truncate font-semibold leading-snug text-brand-navy" title={entry.work}>
                    {entry.work}
                </span>
                <span className="mt-0.5 flex items-center gap-1.5 truncate text-xs text-brand-gray">
                    {meta && <span className="truncate">{meta}</span>}
                    {catLabel && isLit && (
                        <>
                            <FourStar className="size-1.5 shrink-0 text-brand-green/70" />
                            <span className="shrink-0">{catLabel}</span>
                        </>
                    )}
                </span>
            </span>
            <ArrowUpRight className="size-4 shrink-0 text-brand-gray transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-brand-green" />
        </a>
    );
}

function BookGrid({ entries, t, withCat }: { entries: Entry[]; t: (k: string, f?: string) => string; withCat?: (k: string) => string }) {
    // grid-cols-1 EXPLICIT: fără el, coloana implicită se dimensionează la max-content-ul
    // celui mai lat card (min-width:auto pe grid items) → toată pagina scrolla orizontal pe mobil.
    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {entries.map((e) => (
                <BookCard key={e.id} entry={e} catLabel={withCat?.(e.catKey)} t={t} />
            ))}
        </div>
    );
}

export default function BibliotecaOnline({ title, description, breadcrumbs = [], categories, totalBooks }: Props) {
    const t = useTranslations();
    const [query, setQuery] = useState('');
    const [activeCat, setActiveCat] = useState(categories[0]?.key ?? '');
    const [letter, setLetter] = useState<string | null>(null);

    const catLabel = (key: string) => {
        const cat = categories.find((c) => c.key === key);

        return t(`biblioteca.cat.${key}`, cat?.title ?? key);
    };

    // Aplatizăm o singură dată catalogul în „intrări" îmbogățite.
    const entries = useMemo<Entry[]>(() => {
        const out: Entry[] = [];

        for (const cat of categories) {
            cat.books.forEach((b, i) => {
                let author = '';
                let work = b.title;

                if (cat.kind === 'literature') {
                    // Sursă preferată: câmpul `author` din DB (materiale adăugate prin Studio).
                    // Fallback pentru legacy (import columna.org.md): parsare „Autor — Titlu".
                    if (b.author && b.author.trim() !== '') {
                        author = b.author.trim();
                        work = b.title.trim();
                    } else {
                        const parts = b.title.split(' — ');

                        if (parts.length >= 2) {
                            author = parts[0].trim();
                            work = parts.slice(1).join(' — ').trim();
                        }
                    }
                }

                const letterSource = cat.kind === 'literature' ? author || work : work;
                out.push({
                    id: `${cat.key}-${i}`,
                    raw: b.title,
                    work,
                    author,
                    url: b.url,
                    catKey: cat.key,
                    kind: cat.kind,
                    letter: foldLetter(letterSource.charAt(0)),
                    level: cat.kind === 'documents' ? detectLevel(b.title) : 'other',
                });
            });
        }

        return out;
    }, [categories]);

    const litEntries = useMemo(() => entries.filter((e) => e.kind === 'literature'), [entries]);

    // Index alfabetic + autori populari (doar literatură).
    const letters = useMemo(() => Array.from(new Set(litEntries.map((e) => e.letter))).sort((a, b) => a.localeCompare(b, 'ro')), [litEntries]);
    const featured = useMemo(() => {
        const counts = new Map<string, number>();

        for (const e of litEntries) {
            if (e.author) {
counts.set(e.author, (counts.get(e.author) ?? 0) + 1);
}
        }

        return [...counts.entries()]
            .sort((a, b) => b[1] - a[1])
            .slice(0, 8)
            .map(([author]) => author);
    }, [litEntries]);
    const authorCount = useMemo(() => new Set(litEntries.map((e) => e.author).filter(Boolean)).size, [litEntries]);

    const q = query.trim().toLowerCase();
    const searching = q.length > 0;

    const searchResults = useMemo(() => (searching ? entries.filter((e) => e.raw.toLowerCase().includes(q)) : []), [entries, q, searching]);

    const activeCategory = categories.find((c) => c.key === activeCat);
    const isLiteratureView = activeCategory?.kind === 'literature';

    // Vizualizarea de răsfoire (fără căutare): intrările categoriei active, eventual filtrate pe literă.
    const browseEntries = useMemo(() => {
        if (searching || !activeCategory) {
return [];
}

        const inCat = entries.filter((e) => e.catKey === activeCat);

        if (isLiteratureView && letter) {
return inCat.filter((e) => e.letter === letter);
}

        return inCat;
    }, [searching, activeCategory, entries, activeCat, isLiteratureView, letter]);

    // Grupare: literatură pe literă; documente pe treaptă (dacă există mai multe).
    const groups = useMemo(() => {
        if (searching || !activeCategory) {
return [];
}

        if (isLiteratureView) {
            if (letter) {
return [{ key: letter, label: letter, items: browseEntries }];
}

            return letters.map((l) => ({ key: l, label: l, items: browseEntries.filter((e) => e.letter === l) })).filter((g) => g.items.length > 0);
        }

        // Grupăm pe treaptă DOAR când categoria are și liceu, și gimnaziu (curriculum/ghiduri);
        // altfel (ex. reperele metodologice) rămâne o listă plată.
        const hasLevelSplit = browseEntries.some((e) => e.level === 'liceu') && browseEntries.some((e) => e.level === 'gimnaziu');

        if (!hasLevelSplit) {
return [{ key: 'all', label: '', items: browseEntries }];
}

        const order: Entry['level'][] = ['liceu', 'gimnaziu', 'primar', 'other'];
        const present = order.filter((lv) => browseEntries.some((e) => e.level === lv));

        return present.map((lv) => ({ key: lv, label: t(`biblioteca.level.${lv}`, lv), items: browseEntries.filter((e) => e.level === lv) }));
    }, [searching, activeCategory, isLiteratureView, letter, letters, browseEntries, t]);

    const selectCat = (key: string) => {
        setQuery('');
        setLetter(null);
        setActiveCat(key);
    };
    const selectLetter = (l: string | null) => {
        setQuery('');
        setActiveCat(categories.find((c) => c.kind === 'literature')?.key ?? activeCat);
        setLetter(l);
    };

    const stats = [
        { value: String(totalBooks), label: t('biblioteca.titles', 'titluri') },
        { value: String(categories.length), label: t('biblioteca.categories', 'colecții') },
        { value: String(authorCount), label: t('biblioteca.authors', 'autori') },
        { value: 'PDF', label: t('biblioteca.free_access', 'Acces liber'), accent: true },
    ];

    const resultWord = (n: number) => (n === 1 ? t('biblioteca.results_one', 'rezultat') : t('biblioteca.results_many', 'rezultate'));

    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={breadcrumbs} description={t('biblioteca.lead', description)} />

            {/* Bandă NAVY — statistici + căutare + chip-uri; căutarea rămâne `bg-card` (input alb
                pentru claritatea tastării), chip-urile adaptate pentru fundal navy. */}
            <Band variant="navy" pattern="mesh" className="!py-[clamp(2rem,4vw,3.5rem)]">
                <StatRibbon items={stats} />

                {/* Căutare */}
                <div className="mt-8">
                    <label className="relative block">
                        <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-brand-gray" />
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('biblioteca.search_ph', 'Caută după autor sau titlu…')}
                            className="h-13 w-full rounded-[12px] border keyline bg-card pr-12 pl-12 text-base text-brand-navy shadow-sm outline-none transition-colors placeholder:text-brand-gray/70 focus:border-brand-green focus:ring-2 focus:ring-brand-green/30"
                            aria-label={t('biblioteca.search_ph', 'Caută după autor sau titlu…')}
                        />
                        {query && (
                            <button
                                type="button"
                                onClick={() => setQuery('')}
                                className="absolute top-1/2 right-3 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-brand-gray transition-colors hover:bg-brand-navy/8 hover:text-brand-navy"
                                aria-label={t('biblioteca.clear', 'Resetează')}
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </label>

                    {/* Chips de categorie */}
                    <div className="mt-4 flex flex-wrap gap-2">
                        {categories.map((c) => {
                            const active = !searching && c.key === activeCat;

                            return (
                                <button
                                    key={c.key}
                                    type="button"
                                    onClick={() => selectCat(c.key)}
                                    className={cn(
                                        'inline-flex min-h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold transition-colors',
                                        active
                                            ? 'border-brand-green bg-brand-green text-[color:var(--brand-green-foreground)]'
                                            : 'border-white/20 bg-white/[0.06] text-[color:var(--brand-navy-foreground)] hover:border-white/40 hover:bg-white/10',
                                    )}
                                >
                                    {catLabel(c.key)}
                                    <span className={cn('numeral text-xs', active ? 'text-[color:var(--brand-green-foreground)]/80' : 'text-white/60')}>{c.count}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            </Band>

            <Band variant="light" pattern="mesh">
                {searching ? (
                    /* ---------------------------------------------------- rezultate căutare */
                    <Reveal>
                        <div className="mb-6 flex flex-wrap items-baseline justify-between gap-3 border-b keyline pb-4">
                            <h2 className="display text-[clamp(1.25rem,2.4vw,1.75rem)] text-brand-navy">
                                <span className="numeral text-brand-green">{searchResults.length}</span> {resultWord(searchResults.length)}
                            </h2>
                            <button type="button" onClick={() => setQuery('')} className="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                                <X className="size-4" /> {t('biblioteca.clear', 'Resetează')}
                            </button>
                        </div>
                        {searchResults.length > 0 ? (
                            <BookGrid entries={searchResults} t={t} withCat={catLabel} />
                        ) : (
                            <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed keyline bg-card px-6 py-16 text-center">
                                <Library className="size-9 text-brand-navy/30" />
                                <p className="max-w-sm text-brand-gray">{t('biblioteca.no_results', 'Niciun titlu nu corespunde căutării tale.')}</p>
                            </div>
                        )}
                    </Reveal>
                ) : (
                    /* ---------------------------------------------------- răsfoire pe categorie */
                    <div>
                        <SectionHeader
                            index="01"
                            label={`${activeCategory?.count ?? 0} ${t('biblioteca.titles', 'titluri')}`}
                            title={catLabel(activeCat)}
                            className="mb-6"
                        />

                        {/* Autori populari + index A–Z (doar literatură) */}
                        {isLiteratureView && (
                            <div className="mb-7 space-y-4">
                                {featured.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 pr-1 text-xs font-semibold tracking-wide text-brand-gray uppercase">
                                            <Sparkles className="size-3.5 text-brand-green" /> {t('biblioteca.featured', 'Autori populari')}
                                        </span>
                                        {featured.map((a) => (
                                            <button
                                                key={a}
                                                type="button"
                                                onClick={() => setQuery(a.split(',')[0])}
                                                className="inline-flex min-h-8 items-center rounded-full border keyline bg-card px-3 text-xs font-semibold text-brand-navy transition-colors hover:border-brand-green hover:bg-brand-green/10"
                                            >
                                                {a.split(',')[0]}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <div className="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => selectLetter(null)}
                                        className={cn(
                                            'inline-flex min-h-8 items-center rounded-md px-2.5 text-sm font-semibold transition-colors',
                                            !letter ? 'bg-surface-navy text-[color:var(--brand-navy-foreground)]' : 'text-brand-navy hover:bg-brand-navy/8',
                                        )}
                                    >
                                        {t('biblioteca.all_authors', 'Toți')}
                                    </button>
                                    {letters.map((l) => (
                                        <button
                                            key={l}
                                            type="button"
                                            onClick={() => selectLetter(l)}
                                            className={cn(
                                                'inline-flex size-8 items-center justify-center rounded-md text-sm font-semibold transition-colors',
                                                letter === l ? 'bg-brand-green text-[color:var(--brand-green-foreground)]' : 'text-brand-navy hover:bg-brand-navy/8',
                                            )}
                                            style={{ fontFamily: 'var(--font-display)' }}
                                        >
                                            {l}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Grupuri (literă / treaptă) */}
                        <div className="space-y-9">
                            {groups.map((g) => (
                                <section key={g.key} aria-label={g.label || undefined}>
                                    {g.label && (
                                        <div className="mb-4 flex items-center gap-3">
                                            <span className="numeral text-2xl text-brand-navy" style={{ fontFamily: 'var(--font-display)' }}>
                                                {g.label}
                                            </span>
                                            <span className="h-px flex-1 keyline border-t" aria-hidden="true" />
                                            <span className="text-xs text-brand-gray">{g.items.length}</span>
                                        </div>
                                    )}
                                    <BookGrid entries={g.items} t={t} />
                                </section>
                            ))}
                        </div>
                    </div>
                )}

                {/* Notă legală (drepturi de autor) */}
                <p className="mt-12 max-w-3xl border-l-2 border-l-brand-green/50 pl-4 text-xs leading-relaxed text-brand-gray">
                    {t('biblioteca.disclaimer')}
                </p>
            </Band>
        </>
    );
}
