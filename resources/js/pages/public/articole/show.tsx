import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
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

            <PageBanner
                title={post.title}
                breadcrumbs={[{ title: categoryLabel, href: post.categoryUrl }, { title: post.title }]}
            />

            <article className="mx-auto max-w-3xl px-6 py-8 sm:py-12">
                {post.date && <p className="text-sm text-muted-foreground">{post.date}</p>}

                {post.image && (
                    <img src={post.image} alt={post.title} className="mt-6 w-full rounded-lg border border-border object-cover" />
                )}

                {/* Conținutul articolului, migrat din columna.org.md */}
                <div className="prose-columna mt-8 [overflow-wrap:anywhere]" dangerouslySetInnerHTML={{ __html: post.content }} />

                <LocaleLink href={post.categoryUrl} className="mt-10 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                    <ArrowLeft className="size-4" /> {t('article.back_to', 'Înapoi la')} {categoryLabel}
                </LocaleLink>
            </article>
        </>
    );
}
