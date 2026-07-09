import { Head } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Newspaper, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, FourStar, Reveal } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface PostCard {
    title: string;
    slug: string;
    excerpt: string | null;
    image: string | null;
    date: string | null;
    year: number;
}

const PAGE = 9;

function Thumb({ image, title }: { image: string | null; title: string }) {
    if (image) {
        return (
            <div className="photo-frame aspect-video overflow-hidden">
                <img src={image} alt={title} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]" />
            </div>
        );
    }

    return (
        <div className="flex aspect-video w-full items-center justify-center bg-brand-navy/5 text-brand-navy/40">
            <CalendarDays className="size-8" />
        </div>
    );
}

function PostTile({ post, t }: { post: PostCard; t: (k: string, f?: string) => string }) {
    return (
        <LocaleLink
            href={`/articol/${post.slug}`}
            className="group flex h-full flex-col overflow-hidden rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card transition-[border-color,box-shadow] hover:border-l-brand-green hover:shadow-[0_16px_36px_-26px_rgba(15,77,119,0.5)]"
        >
            <Thumb image={post.image} title={post.title} />
            <div className="flex flex-1 flex-col p-5">
                {post.date && <span className="eyebrow text-brand-gray">{post.date}</span>}
                <h3 className="heading-dynamic mt-1.5 text-lg text-brand-navy">{post.title}</h3>
                {post.excerpt && <p className="mt-2 line-clamp-3 text-sm text-brand-gray">{post.excerpt}</p>}
                <span className="mt-auto inline-flex items-center gap-1.5 pt-3 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                    {t('article.read', 'Citește')} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
        </LocaleLink>
    );
}

export default function ArticoleIndex({ pageTitle, category, posts }: { pageTitle: string; category: string; posts: PostCard[] }) {
    const t = useTranslations();
    const title = category === 'blog' ? t('article.blog_title', pageTitle) : t('article.news_title', pageTitle);

    const [query, setQuery] = useState('');
    const [year, setYear] = useState<number | 'all'>('all');
    const [limit, setLimit] = useState(PAGE);

    const years = useMemo(() => Array.from(new Set(posts.map((p) => p.year))).sort((a, b) => b - a), [posts]);

    const q = query.trim().toLowerCase();
    const filtered = useMemo(
        () => posts.filter((p) => (year === 'all' || p.year === year) && (!q || `${p.title} ${p.excerpt ?? ''}`.toLowerCase().includes(q))),
        [posts, year, q],
    );

    const isDefault = !q && year === 'all';
    const featured = isDefault && filtered.length > 0 ? filtered[0] : null;
    const grid = featured ? filtered.slice(1) : filtered;
    const visible = grid.slice(0, limit);

    const resetLimit = () => setLimit(PAGE);
    const resultWord = filtered.length === 1 ? t('article.results_one', 'articol') : t('article.results_many', 'articole');

    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={[{ title }]} description={t('article.list_lead', 'Anunțuri, evenimente și momente din viața Liceului Columna.')} />

            {/* Bandă NAVY — articolul featured (cel mai recent) ca „hero" secundar imersiv;
                textele adaptate pentru fundal navy (foreground alb, subtext alb/70). */}
            {featured && (
                <Band variant="navy" pattern="mesh" className="!py-[clamp(2.5rem,5vw,4rem)]">
                    <Reveal>
                        <LocaleLink href={`/articol/${featured.slug}`} className="group grid overflow-hidden rounded-[16px] border border-white/15 bg-white/[0.04] lg:grid-cols-2">
                            <Thumb image={featured.image} title={featured.title} />
                            <div className="flex flex-col justify-center gap-3 p-6 sm:p-9">
                                <span className="eyebrow inline-flex items-center gap-2 text-[color:var(--brand-navy-foreground)]">
                                    <FourStar className="size-3 text-brand-green" /> {t('article.featured', 'Cel mai recent')}
                                </span>
                                <h2 className="display text-[clamp(1.5rem,3vw,2.25rem)] text-[color:var(--brand-navy-foreground)]">{featured.title}</h2>
                                {featured.date && <span className="text-sm text-white/70">{featured.date}</span>}
                                {featured.excerpt && <p className="line-clamp-3 leading-relaxed text-white/80">{featured.excerpt}</p>}
                                <span className="mt-2 inline-flex w-fit items-center gap-2 rounded-[12px] bg-brand-green px-5 py-2.5 font-semibold text-[color:var(--brand-green-foreground)] shadow-sm transition-all group-hover:brightness-[1.04]">
                                    {t('article.read_article', 'Citește articolul')} <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                </span>
                            </div>
                        </LocaleLink>
                    </Reveal>
                </Band>
            )}

            {/* Bandă DESCHISĂ — căutare + filtru + grila de articole rămase (pe fundal light,
                mai potrivit editorial pentru scanning). */}
            <Band variant="light" pattern="mesh" className="!py-[clamp(2.5rem,5vw,4rem)]">
                {posts.length === 0 ? (
                    <p className="text-brand-gray">{t('article.none', 'Nu există articole publicate momentan.')}</p>
                ) : (
                    <>
                        {/* Căutare + filtru pe an */}
                        <div className="mb-8 flex flex-col gap-4">
                            <label className="relative block max-w-xl">
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-brand-gray" />
                                <input
                                    type="search"
                                    value={query}
                                    onChange={(e) => {
                                        setQuery(e.target.value);
                                        resetLimit();
                                    }}
                                    placeholder={t('article.search_ph', 'Caută în articole…')}
                                    className="h-12 w-full rounded-[12px] border keyline bg-card pr-11 pl-12 text-base text-brand-navy shadow-sm outline-none transition-colors placeholder:text-brand-gray/70 focus:border-brand-green focus:ring-2 focus:ring-brand-green/30"
                                    aria-label={t('article.search_ph', 'Caută în articole…')}
                                />
                                {query && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setQuery('');
                                            resetLimit();
                                        }}
                                        className="absolute top-1/2 right-3 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-brand-gray hover:bg-brand-navy/8 hover:text-brand-navy"
                                        aria-label="×"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </label>

                            {years.length > 1 && (
                                <div className="flex flex-wrap gap-2">
                                    {(['all', ...years] as const).map((y) => {
                                        const active = year === y;

                                        return (
                                            <button
                                                key={y}
                                                type="button"
                                                onClick={() => {
                                                    setYear(y as number | 'all');
                                                    resetLimit();
                                                }}
                                                className={cn(
                                                    'inline-flex min-h-9 items-center rounded-full border px-3.5 text-sm font-semibold transition-colors',
                                                    active ? 'border-brand-navy bg-surface-navy text-[color:var(--brand-navy-foreground)]' : 'keyline bg-card text-brand-navy hover:border-brand-navy',
                                                )}
                                            >
                                                {y === 'all' ? t('article.all_years', 'Toți anii') : <span className="numeral">{y}</span>}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}

                            {!isDefault && (
                                <p className="text-sm text-brand-gray">
                                    <span className="numeral font-semibold text-brand-navy">{filtered.length}</span> {resultWord}
                                </p>
                            )}
                        </div>

                        {/* Grila de articole */}
                        {grid.length === 0 && !featured ? (
                            <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed keyline bg-card px-6 py-16 text-center">
                                <Newspaper className="size-9 text-brand-navy/30" />
                                <p className="max-w-sm text-brand-gray">{t('article.no_results', 'Niciun articol nu corespunde căutării tale.')}</p>
                            </div>
                        ) : (
                            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {visible.map((post) => (
                                    <article key={post.slug} className="h-full">
                                        <PostTile post={post} t={t} />
                                    </article>
                                ))}
                            </div>
                        )}

                        {visible.length < grid.length && (
                            <div className="mt-10 flex justify-center">
                                <button
                                    type="button"
                                    onClick={() => setLimit((l) => l + PAGE)}
                                    className="inline-flex min-h-11 items-center gap-2 rounded-[12px] border border-brand-navy px-6 font-semibold text-brand-navy transition-colors hover:bg-surface-navy hover:text-[color:var(--brand-navy-foreground)]"
                                >
                                    {t('article.load_more', 'Arată mai multe')}
                                    <span className="numeral text-sm opacity-70">
                                        {visible.length} / {grid.length}
                                    </span>
                                </button>
                            </div>
                        )}
                    </>
                )}
            </Band>
        </>
    );
}
