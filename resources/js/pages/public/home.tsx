import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, CalendarDays, GraduationCap, Images, Newspaper, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { siteContact } from '@/lib/public-navigation';

const quickLinks = [
    { title: 'Admitere', description: 'Pașii de înscriere și contactul', href: '/admitere', icon: GraduationCap },
    { title: 'Orarul lecțiilor', description: 'Orarul pe clase', href: '/orarul-lectiilor', icon: CalendarDays },
    { title: 'Biblioteca online', description: 'Cărți, curricula, ghiduri', href: '/biblioteca-online', icon: BookOpen },
    { title: 'Personal', description: 'Echipa didactică', href: '/personal', icon: Users },
    { title: 'Actualități', description: 'Evenimente și anunțuri', href: '/actualitati-si-evenimente', icon: Newspaper },
    { title: 'Galerie', description: 'Momente din viața liceului', href: '/galerie', icon: Images },
];

const schools = [
    { title: 'Școala primară', href: '/scoala-primara' },
    { title: 'Școala gimnazială', href: '/scoala-gimnaziala' },
    { title: 'Școala liceală', href: '/scoala-liceala' },
];

export default function Home() {
    return (
        <>
            <Head title="Acasă" />

            {/* Hero */}
            <section className="border-b border-border bg-gradient-to-b from-muted/60 to-background">
                <div className="mx-auto max-w-7xl px-6 py-20 text-center">
                    <span className="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                        IPL „Liceul Columna” · Chișinău
                    </span>
                    <h1 className="mx-auto mt-6 max-w-3xl font-serif text-4xl font-bold tracking-tight sm:text-5xl">
                        {siteContact.tagline}
                    </h1>
                    <p className="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                        Educație de calitate de la clasele primare până la liceu, într-un mediu sigur și inspirat.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button asChild size="lg">
                            <Link href="/admitere">
                                Admitere <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href="/de-ce-columna">De ce Columna?</Link>
                        </Button>
                    </div>
                </div>
            </section>

            {/* Cele trei trepte */}
            <section className="mx-auto max-w-7xl px-6 py-14">
                <div className="grid gap-4 sm:grid-cols-3">
                    {schools.map((school) => (
                        <Link
                            key={school.href}
                            href={school.href}
                            className="group rounded-lg border border-border bg-card p-6 transition-colors hover:border-primary"
                        >
                            <GraduationCap className="size-7 text-primary" />
                            <h2 className="mt-4 text-lg font-semibold">{school.title}</h2>
                            <span className="mt-2 inline-flex items-center gap-1 text-sm text-muted-foreground group-hover:text-foreground">
                                Detalii <ArrowRight className="size-3.5" />
                            </span>
                        </Link>
                    ))}
                </div>
            </section>

            {/* Acces rapid */}
            <section className="border-t border-border bg-muted/30">
                <div className="mx-auto max-w-7xl px-6 py-14">
                    <h2 className="font-serif text-2xl font-bold tracking-tight">Acces rapid</h2>
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {quickLinks.map((link) => {
                            const Icon = link.icon;
                            return (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    className="group flex items-start gap-4 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </span>
                                    <div>
                                        <h3 className="font-semibold">{link.title}</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">{link.description}</p>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Actualități (placeholder) */}
            <section className="mx-auto max-w-7xl px-6 py-14">
                <div className="flex items-center justify-between">
                    <h2 className="font-serif text-2xl font-bold tracking-tight">Ultimele actualități</h2>
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/actualitati-si-evenimente">
                            Toate <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>
                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {[1, 2, 3].map((i) => (
                        <div key={i} className="rounded-lg border border-dashed border-border bg-card p-5">
                            <div className="aspect-video w-full rounded-md bg-muted" />
                            <p className="mt-4 text-sm font-medium text-muted-foreground">Articol în curs de migrare</p>
                            <p className="mt-1 text-xs text-muted-foreground">columna.org.md → columna.md</p>
                        </div>
                    ))}
                </div>
            </section>
        </>
    );
}
