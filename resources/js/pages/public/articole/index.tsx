import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Container, Reveal } from '@/components/public/brand';
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

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                {posts.data.length === 0 ? (
                    <p className="text-brand-gray">{t('article.none', 'Nu există articole publicate momentan.')}</p>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <Reveal key={post.slug} as="article" className="h-full">
                                <LocaleLink
                                    href={`/articol/${post.slug}`}
                                    className="group flex h-full flex-col overflow-hidden rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card transition-all hover:-translate-y-0.5 hover:border-l-brand-green"
                                >
                                    {post.image ? (
                                        <div className="photo-frame aspect-video overflow-hidden">
                                            <img src={post.image} alt={post.title} loading="lazy" className="h-full w-full object-cover" />
                                        </div>
                                    ) : (
                                        <div className="flex aspect-video w-full items-center justify-center bg-brand-navy/5 text-brand-navy/40">
                                            <CalendarDays className="size-8" />
                                        </div>
                                    )}
                                    <div className="flex flex-1 flex-col p-5">
                                        {post.date && <span className="eyebrow text-brand-gray">{post.date}</span>}
                                        <h2 className="heading-dynamic mt-1.5 text-lg text-brand-navy">{post.title}</h2>
                                        {post.excerpt && <p className="mt-2 line-clamp-3 text-sm text-brand-gray">{post.excerpt}</p>}
                                        <span className="mt-auto inline-flex items-center gap-1.5 pt-3 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                                            {t('article.read', 'Citește')} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                        </span>
                                    </div>
                                </LocaleLink>
                            </Reveal>
                        ))}
                    </div>
                )}

                {posts.links.length > 3 && (
                    <nav className="mt-12 flex flex-wrap justify-center gap-1.5">
                        {posts.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={cn(
                                        'inline-flex min-h-11 min-w-11 items-center justify-center rounded-[10px] border keyline px-3 text-sm font-medium text-brand-navy transition-colors hover:bg-accent',
                                        link.active && 'border-brand-navy bg-brand-navy text-[color:var(--brand-navy-foreground)]',
                                    )}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={i} className="inline-flex min-h-11 min-w-11 items-center justify-center px-3 text-sm text-brand-gray/60" dangerouslySetInnerHTML={{ __html: link.label }} />
                            ),
                        )}
                    </nav>
                )}
            </Container>
        </>
    );
}
