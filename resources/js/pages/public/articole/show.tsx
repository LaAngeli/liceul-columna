import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Container } from '@/components/public/brand';
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
}

export default function ArticolShow({ post }: { post: PostShow }) {
    const t = useTranslations();
    const categoryLabel = post.category === 'blog' ? t('article.blog_title', post.categoryLabel) : t('article.news_title', post.categoryLabel);

    return (
        <>
            <Head title={post.title} />

            <PageBanner title={post.title} breadcrumbs={[{ title: categoryLabel, href: post.categoryUrl }, { title: post.title }]} />

            <Container className="py-[clamp(2.5rem,6vw,5rem)]">
                <article className="mx-auto max-w-[68ch]">
                    {post.date && <p className="eyebrow text-brand-gray">{post.date}</p>}

                    {post.image && (
                        <div className="photo-frame mt-5 overflow-hidden rounded-[12px] border keyline">
                            <img src={post.image} alt={post.title} className="w-full object-cover" />
                        </div>
                    )}

                    {/* Corpul articolului, migrat din columna.org.md (Proxima Nova prin .site-shell) */}
                    <div className="prose-columna mt-8 [overflow-wrap:anywhere]" dangerouslySetInnerHTML={{ __html: post.content }} />

                    <LocaleLink href={post.categoryUrl} className="mt-12 inline-flex min-h-11 items-center gap-1.5 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                        <ArrowLeft className="size-4" /> {t('article.back_to', 'Înapoi la')} {categoryLabel}
                    </LocaleLink>
                </article>
            </Container>
        </>
    );
}
