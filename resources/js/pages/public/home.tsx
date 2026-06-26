import { Head } from '@inertiajs/react';
import { ArrowRight, BookOpen, CalendarDays, GraduationCap, Images, Newspaper, Users } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';

const quickLinks = [
    { titleKey: 'nav.admission', title: 'Admitere', descKey: 'quick.admission_desc', description: 'Pașii de înscriere și contactul', href: '/admitere', icon: GraduationCap },
    { titleKey: 'calendar.lessons', title: 'Orarul lecțiilor', descKey: 'quick.schedule_desc', description: 'Orarul pe clase', href: '/orarul-lectiilor', icon: CalendarDays },
    { titleKey: 'utility.library', title: 'Biblioteca online', descKey: 'quick.library_desc', description: 'Cărți, curricula, ghiduri', href: '/biblioteca-online', icon: BookOpen },
    { titleKey: 'nav.staff', title: 'Personal', descKey: 'quick.staff_desc', description: 'Echipa didactică', href: '/personal', icon: Users },
    { titleKey: 'nav.news', title: 'Actualități', descKey: 'quick.news_desc', description: 'Evenimente și anunțuri', href: '/actualitati-si-evenimente', icon: Newspaper },
    { titleKey: 'nav.gallery', title: 'Galerie', descKey: 'quick.gallery_desc', description: 'Momente din viața liceului', href: '/galerie', icon: Images },
];

const schools = [
    { titleKey: 'structure.primary', title: 'Școala primară', href: '/scoala-primara' },
    { titleKey: 'structure.gymnasium', title: 'Școala gimnazială', href: '/scoala-gimnaziala' },
    { titleKey: 'structure.lyceum', title: 'Școala liceală', href: '/scoala-liceala' },
];

interface NewsCard {
    title: string;
    slug: string;
    excerpt: string | null;
    image: string | null;
    date: string | null;
}

export default function Home({ latestNews }: { latestNews: NewsCard[] }) {
    const t = useTranslations();

    return (
        <>
            <Head title={t('breadcrumb.home', 'Acasă')} />

            {/* Hero */}
            <section className="border-b border-border bg-gradient-to-b from-muted/60 to-background">
                <div className="mx-auto max-w-7xl px-6 py-20 text-center">
                    <span className="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                        {t('home.badge', 'IPL „Liceul Columna” · Chișinău')}
                    </span>
                    <h1 className="mx-auto mt-6 max-w-3xl font-serif text-4xl font-bold tracking-tight sm:text-5xl">
                        {t('home.tagline', siteContact.tagline)}
                    </h1>
                    <p className="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                        {t('home.subtitle', 'Educație de calitate de la clasele primare până la liceu, într-un mediu sigur și inspirat.')}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg">
                            <LocaleLink href="/admitere">
                                {t('nav.admission', 'Admitere')} <ArrowRight className="size-4" />
                            </LocaleLink>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <LocaleLink href="/de-ce-columna">{t('about.why', 'De ce Columna?')}</LocaleLink>
                        </Button>
                    </div>
                </div>
            </section>

            {/* Cele trei trepte */}
            <section className="mx-auto max-w-7xl px-6 py-14">
                <div className="grid gap-4 sm:grid-cols-3">
                    {schools.map((school) => (
                        <LocaleLink
                            key={school.href}
                            href={school.href}
                            className="group rounded-lg border border-border bg-card p-6 transition-colors hover:border-primary"
                        >
                            <GraduationCap className="size-7 text-primary" />
                            <h2 className="mt-4 text-lg font-semibold">{t(school.titleKey, school.title)}</h2>
                            <span className="mt-2 inline-flex items-center gap-1 text-sm text-muted-foreground group-hover:text-foreground">
                                {t('action.details', 'Detalii')} <ArrowRight className="size-3.5" />
                            </span>
                        </LocaleLink>
                    ))}
                </div>
            </section>

            {/* Acces rapid */}
            <section className="border-t border-border bg-muted/30">
                <div className="mx-auto max-w-7xl px-6 py-14">
                    <h2 className="font-serif text-2xl font-bold tracking-tight">{t('home.quick_access', 'Acces rapid')}</h2>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {quickLinks.map((link) => {
                            const Icon = link.icon;
                            return (
                                <LocaleLink
                                    key={link.href}
                                    href={link.href}
                                    className="group flex items-start gap-4 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <div>
                                        <h3 className="font-semibold">{t(link.titleKey, link.title)}</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">{t(link.descKey, link.description)}</p>
                                    </div>
                                </LocaleLink>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Ultimele actualități (din registru) */}
            {latestNews.length > 0 && (
                <section className="mx-auto max-w-7xl px-6 py-14">
                    <div className="flex items-center justify-between">
                        <h2 className="font-serif text-2xl font-bold tracking-tight">{t('home.latest_news', 'Ultimele actualități')}</h2>
                        <Button asChild variant="ghost" size="sm">
                            <LocaleLink href="/actualitati-si-evenimente">
                                {t('action.all', 'Toate')} <ArrowRight className="size-4" />
                            </LocaleLink>
                        </Button>
                    </div>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {latestNews.map((post) => (
                            <LocaleLink
                                key={post.slug}
                                href={`/articol/${post.slug}`}
                                className="group flex flex-col overflow-hidden rounded-lg border border-border bg-card transition-colors hover:border-primary"
                            >
                                {post.image ? (
                                    <img src={post.image} alt={post.title} loading="lazy" className="aspect-video w-full object-cover" />
                                ) : (
                                    <div className="flex aspect-video w-full items-center justify-center bg-muted text-muted-foreground">
                                        <Newspaper className="size-8" />
                                    </div>
                                )}
                                <div className="flex flex-1 flex-col p-5">
                                    {post.date && <p className="text-xs text-muted-foreground">{post.date}</p>}
                                    <h3 className="mt-1 font-serif leading-snug font-semibold group-hover:text-primary">{post.title}</h3>
                                    {post.excerpt && <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{post.excerpt}</p>}
                                </div>
                            </LocaleLink>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
