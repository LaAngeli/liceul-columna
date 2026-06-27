import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface PostCard {
    title: string;
    slug: string;
    excerpt: string | null;
    image: string | null;
    date: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

export default function ArticoleIndex({ pageTitle, category, posts }: { pageTitle: string; category: string; posts: Paginated<PostCard> }) {
    const t = useTranslations();
    const title = category === 'blog' ? t('article.blog_title', pageTitle) : t('article.news_title', pageTitle);

    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={[{ title }]} />

            <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
                {posts.data.length === 0 ? (
                    <p className="text-muted-foreground">{t('article.none', 'Nu există articole publicate momentan.')}</p>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <LocaleLink
                                key={post.slug}
                                href={`/articol/${post.slug}`}
                                className="group flex flex-col overflow-hidden rounded-lg border border-border bg-card transition-colors hover:border-primary"
                            >
                                {post.image ? (
                                    <img src={post.image} alt={post.title} loading="lazy" className="aspect-video w-full object-cover" />
                                ) : (
                                    <div className="flex aspect-video w-full items-center justify-center bg-muted text-muted-foreground">
                                        <CalendarDays className="size-8" />
                                    </div>
                                )}
                                <div className="flex flex-1 flex-col p-5">
                                    {post.date && <p className="text-xs text-muted-foreground">{post.date}</p>}
                                    <h2 className="mt-1 font-serif text-lg leading-snug font-semibold group-hover:text-primary">{post.title}</h2>
                                    {post.excerpt && <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">{post.excerpt}</p>}
                                    <span className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                        {t('article.read', 'Citește')} <ArrowRight className="size-3.5" />
                                    </span>
                                </div>
                            </LocaleLink>
                        ))}
                    </div>
                )}

                {posts.links.length > 3 && (
                    <nav className="mt-10 flex flex-wrap justify-center gap-1.5 sm:gap-1">
                        {posts.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={cn(
                                        'rounded-md border border-border px-3 py-3 text-sm transition-colors hover:bg-accent sm:py-1.5',
                                        link.active && 'border-primary bg-primary text-primary-foreground',
                                    )}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md px-3 py-3 text-sm text-muted-foreground sm:py-1.5"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </section>
        </>
    );
}
