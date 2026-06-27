import { Head } from '@inertiajs/react';
import { ArrowRight, Award, BookOpen, GraduationCap, HandHeart, Heart, Languages, Mail, Newspaper, Phone, Users } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';

interface NewsCard {
    title: string;
    slug: string;
    excerpt: string | null;
    image: string | null;
    date: string | null;
}

interface LeadershipMember {
    name: string;
    role: string;
    slug: string | null;
    photo: string | null;
}

const institutionCards = [
    { titleKey: 'about.letter', title: 'Scrisoarea Directorului', href: '/scrisoarea-directorului', icon: BookOpen },
    { titleKey: 'about.philosophy', title: 'Filosofia Liceului', href: '/filosofia-liceului', icon: Heart },
];

const featuredCards = [
    { titleKey: 'home.featured_news', title: 'Actualități și evenimente', descKey: 'home.featured_news_desc', description: 'Evenimente, anunțuri și momente din viața liceului', href: '/actualitati-si-evenimente', icon: Newspaper },
    { titleKey: 'home.featured_admission', title: 'Admitere', descKey: 'home.featured_admission_desc', description: 'Pași și informații despre înmatriculare', href: '/admitere', icon: GraduationCap },
    { titleKey: 'home.featured_cambridge', title: 'Cambridge English Exam', descKey: 'home.featured_cambridge_desc', description: 'Cursuri de pregătire și certificare internațională', href: '/cambridge-english-exam', icon: Award },
    { titleKey: 'home.featured_sponsorship', title: 'Sponsorizare', descKey: 'home.featured_sponsorship_desc', description: 'Mecanismul 2% și posibilități de a sprijini liceul', href: '/sponsorizare', icon: HandHeart },
];

const stats = [
    { value: '27', labelKey: 'home.stat_experience', label: 'ani de experiență' },
    { value: '55', labelKey: 'home.stat_teachers', label: 'cadre didactice' },
    { value: '100', labelKey: 'home.stat_university', label: '% elevi admiși la universitate' },
    { value: '5', labelKey: 'home.stat_languages', label: 'limbi străine studiate' },
];

export default function Home({ latestNews, leadership }: { latestNews: NewsCard[]; leadership: LeadershipMember[] }) {
    const t = useTranslations();
    const getInitials = useInitials();

    return (
        <>
            <Head title={t('breadcrumb.home', 'Acasă')} />

            {/* Hero */}
            <section className="border-b border-border bg-gradient-to-b from-muted/60 to-background">
                <div className="mx-auto max-w-7xl px-4 py-12 text-center sm:px-6 sm:py-20">
                    <span className="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                        {t('home.badge', 'IPL „Liceul Columna” · Chișinău')}
                    </span>
                    <h1 className="mx-auto mt-6 max-w-3xl font-serif text-4xl font-bold tracking-tight sm:text-5xl">
                        {t('home.hero_title', 'Succesul copilului începe aici')}
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

            {/* Instituția Privată Liceul „Columna" */}
            <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
                <h2 className="text-center font-serif text-2xl font-bold tracking-tight sm:text-3xl">
                    {t('home.institution_title', 'Instituția Privată Liceul „Columna”')}
                </h2>
                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                    {institutionCards.map((card) => {
                        const Icon = card.icon;
                        return (
                            <LocaleLink
                                key={card.href}
                                href={card.href}
                                className="group flex items-start gap-4 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary sm:p-6"
                            >
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon className="size-6" />
                                </span>
                                <div>
                                    <h3 className="font-serif text-lg font-semibold">{t(card.titleKey, card.title)}</h3>
                                    <span className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </div>
                            </LocaleLink>
                        );
                    })}
                </div>
            </section>

            {/* Featured 4 carduri */}
            <section className="border-t border-border bg-muted/30">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {featuredCards.map((card) => {
                            const Icon = card.icon;
                            return (
                                <LocaleLink
                                    key={card.href}
                                    href={card.href}
                                    className="group flex flex-col rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary sm:p-6"
                                >
                                    <span className="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="mt-4 font-semibold">{t(card.titleKey, card.title)}</h3>
                                    <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{t(card.descKey, card.description)}</p>
                                    <span className="mt-3 inline-flex items-center gap-1 text-sm text-primary">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                                    </span>
                                </LocaleLink>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Ultimele actualități (din registru) */}
            {latestNews.length > 0 && (
                <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
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

            {/* De ce Liceul „Columna" + statistici */}
            <section className="border-t border-border bg-primary text-primary-foreground">
                <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
                    <h2 className="text-center font-serif text-2xl font-bold tracking-tight sm:text-3xl">
                        {t('home.why_title', 'De ce Liceul „Columna”')}
                    </h2>
                    <p className="mx-auto mt-3 max-w-3xl text-center text-primary-foreground/85">
                        {t('home.why_lead', 'Deoarece educăm elevii în spiritul valorilor naționale și general-umane')}
                    </p>
                    <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {stats.map((stat) => (
                            <div key={stat.labelKey} className="text-center">
                                <div className="font-serif text-4xl font-bold sm:text-5xl">{stat.value}</div>
                                <p className="mt-2 text-sm text-primary-foreground/85">{t(stat.labelKey, stat.label)}</p>
                            </div>
                        ))}
                    </div>
                    <p className="mt-8 flex items-center justify-center gap-2 text-sm text-primary-foreground/80">
                        <Languages className="size-4 shrink-0" />
                        {t('home.languages_list', 'Româna, Engleza, Germana, Franceza, Rusa')}
                    </p>
                </div>
            </section>

            {/* Personal — conducerea */}
            {leadership.length > 0 && (
                <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14">
                    <h2 className="font-serif text-2xl font-bold tracking-tight">{t('home.staff_title', 'Personal')}</h2>
                    <p className="mt-3 max-w-3xl text-muted-foreground">{t('home.staff_intro', 'Profesorii noștri…')}</p>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {leadership.map((member) => {
                            const inner = (
                                <>
                                    {member.photo ? (
                                        <img src={member.photo} alt={member.name} loading="lazy" className="size-16 rounded-full object-cover" />
                                    ) : (
                                        <span className="flex size-16 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary">
                                            {getInitials(member.name)}
                                        </span>
                                    )}
                                    <div className="mt-3">
                                        <p className="font-medium leading-tight">{member.name}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">{member.role}</p>
                                    </div>
                                </>
                            );
                            return member.slug ? (
                                <LocaleLink
                                    key={member.name}
                                    href={`/${member.slug}`}
                                    className="group flex flex-col items-center rounded-lg border border-border bg-card p-4 text-center transition-colors hover:border-primary"
                                >
                                    {inner}
                                </LocaleLink>
                            ) : (
                                <div
                                    key={member.name}
                                    className="flex flex-col items-center rounded-lg border border-border bg-card p-4 text-center"
                                >
                                    {inner}
                                </div>
                            );
                        })}
                    </div>
                    <div className="mt-8 text-center">
                        <Button asChild variant="outline">
                            <LocaleLink href="/personal">
                                {t('home.staff_see_all', 'Vezi toată echipa')} <ArrowRight className="size-4" />
                            </LocaleLink>
                        </Button>
                    </div>
                </section>
            )}

            {/* Contactează-ne */}
            <section className="border-t border-border bg-muted/40">
                <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 text-center">
                    <h2 className="font-serif text-2xl font-bold tracking-tight sm:text-3xl">
                        {t('home.contact_title', 'Contactează-ne')}
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-muted-foreground">
                        {t('home.contact_subtitle', 'Suntem convinși…')}
                    </p>
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-4 text-sm">
                        <a href={`tel:${siteContact.phone.replace(/[^+\d]/g, '')}`} className="inline-flex items-center gap-2 hover:text-primary">
                            <Phone className="size-4" /> {siteContact.phone}
                        </a>
                        <a href={`mailto:${siteContact.email}`} className="inline-flex items-center gap-2 hover:text-primary">
                            <Mail className="size-4" /> {siteContact.email}
                        </a>
                    </div>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg">
                            <LocaleLink href="/contacte">
                                {t('home.contact_cta', 'Scrie-ne')} <ArrowRight className="size-4" />
                            </LocaleLink>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <LocaleLink href="/personal">
                                <Users className="size-4" /> {t('home.staff_see_all', 'Vezi toată echipa')}
                            </LocaleLink>
                        </Button>
                    </div>
                </div>
            </section>
        </>
    );
}
