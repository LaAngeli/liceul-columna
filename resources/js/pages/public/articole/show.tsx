import { Head } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CalendarDays, Clock } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Band, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';

interface PostShow {
    title: string;
    category: string;
    categoryLabel: string;
    categoryUrl: string;
    image: string | null;
    content: string;
    date: string | null;
    readingMinutes: number;
}
interface Related {
    title: string;
    slug: string;
    image: string | null;
    date: string | null;
}

export default function ArticolShow({ post, related = [] }: { post: PostShow; related?: Related[] }) {
    const t = useTranslations();
    const categoryLabel = post.category === 'blog' ? t('article.blog_title', post.categoryLabel) : t('article.news_title', post.categoryLabel);

    return (
        <>
            <Head title={post.title} />

            <PageBanner title={post.title} breadcrumbs={[{ title: categoryLabel, href: post.categoryUrl }, { title: post.title }]} description={t('article.show_lead', 'Articol din actualitățile Liceului „Columna".')} />

            <Band variant="light" className="!pb-0">
                <article className="mx-auto max-w-3xl">
                    {/* Meta editorial */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-brand-gray">
                        {post.date && (
                            <span className="inline-flex items-center gap-1.5">
                                <CalendarDays className="size-4 text-brand-green" /> {post.date}
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="size-4 text-brand-green" /> <span className="numeral">{post.readingMinutes}</span> {t('article.min_read', 'min de citit')}
                        </span>
                        <LocaleLink
                            href={post.categoryUrl}
                            className="inline-flex items-center rounded-full bg-brand-navy/8 px-3 py-1 text-xs font-semibold text-brand-navy transition-colors hover:bg-surface-navy hover:text-[color:var(--brand-navy-foreground)]"
                        >
                            {categoryLabel}
                        </LocaleLink>
                    </div>

                    {/* Imaginea-hero (lățime largă, editorial) */}
                    {post.image && (
                        <div className="photo-frame mt-6 overflow-hidden rounded-[14px] border keyline">
                            <img src={post.image} alt={post.title} className="w-full object-cover" />
                        </div>
                    )}

                    {/* Corpul articolului (coloană lizibilă, Proxima Nova prin .site-shell) */}
                    <div className="prose-columna mx-auto mt-8 max-w-[65ch] [overflow-wrap:anywhere]" dangerouslySetInnerHTML={{ __html: post.content }} />

                    <div className="mx-auto mt-12 max-w-[65ch] border-t keyline pt-6">
                        <LocaleLink href={post.categoryUrl} className="inline-flex min-h-11 items-center gap-1.5 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4 hover:decoration-[3px]">
                            <ArrowLeft className="size-4" /> {t('article.back_to', 'Înapoi la')} {categoryLabel}
                        </LocaleLink>
                    </div>
                </article>
            </Band>

            {/* Articole similare */}
            {related.length > 0 && (
                <Band variant="light" className="!pt-[clamp(2.5rem,5vw,4rem)]">
                    <div className="border-t keyline pt-[clamp(2rem,4vw,3rem)]">
                        <SectionHeader index="02" label={categoryLabel} title={t('article.related', 'Articole similare')} className="mb-6" />
                        <div className="grid gap-5 sm:grid-cols-3">
                            {related.map((r) => (
                                <LocaleLink
                                    key={r.slug}
                                    href={`/articol/${r.slug}`}
                                    className="group flex h-full flex-col overflow-hidden rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card transition-[border-color] hover:border-l-brand-green"
                                >
                                    {r.image ? (
                                        <div className="photo-frame aspect-video overflow-hidden">
                                            <img src={r.image} alt={r.title} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]" />
                                        </div>
                                    ) : (
                                        <div className="flex aspect-video items-center justify-center bg-brand-navy/5 text-brand-navy/40">
                                            <CalendarDays className="size-7" />
                                        </div>
                                    )}
                                    <div className="flex flex-1 flex-col p-4">
                                        {r.date && <span className="eyebrow text-brand-gray">{r.date}</span>}
                                        <h3 className="heading-dynamic mt-1.5 text-base text-brand-navy">{r.title}</h3>
                                        <span className="mt-auto inline-flex items-center gap-1.5 pt-3 text-sm font-semibold text-brand-navy">
                                            {t('article.read', 'Citește')} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                        </span>
                                    </div>
                                </LocaleLink>
                            ))}
                        </div>
                    </div>
                </Band>
            )}
        </>
    );
}
