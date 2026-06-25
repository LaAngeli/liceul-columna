import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Mail, Menu, Phone, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { mainNav, siteContact, utilityNav } from '@/lib/public-navigation';
import { dashboard, login } from '@/routes';

function useIsActive() {
    const { url } = usePage();
    return (href?: string) => {
        if (!href) {
            return false;
        }
        if (href === '/') {
            return url === '/';
        }
        return url === href || url.startsWith(`${href}/`);
    };
}

export function SiteHeader() {
    const { auth } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const isActive = useIsActive();

    return (
        <header className="sticky top-0 z-40 w-full border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            {/* Bara utilitară */}
            <div className="hidden border-b border-border/60 bg-muted/40 text-xs text-muted-foreground lg:block">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-1.5">
                    <div className="flex items-center gap-4">
                        <a href={`tel:${siteContact.phone.replace(/[^+\d]/g, '')}`} className="inline-flex items-center gap-1.5 hover:text-foreground">
                            <Phone className="size-3.5" /> {siteContact.phone}
                        </a>
                        <a href={`mailto:${siteContact.email}`} className="inline-flex items-center gap-1.5 hover:text-foreground">
                            <Mail className="size-3.5" /> {siteContact.email}
                        </a>
                    </div>
                    <nav className="flex items-center gap-3">
                        {utilityNav.map((item) => (
                            <Link key={item.href} href={item.href} className={cn('hover:text-foreground', isActive(item.href) && 'text-foreground font-medium')}>
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </div>
            </div>

            {/* Bara principală */}
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3">
                <Link href="/" className="flex items-center gap-2.5">
                    <img src="/images/logo/columna-navy.png" alt="Liceul Columna" className="h-11 w-auto dark:hidden" />
                    <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="hidden h-11 w-auto dark:block" />
                    <span className="font-serif text-lg leading-tight font-semibold tracking-tight">Liceul Columna</span>
                </Link>

                {/* Navigare desktop */}
                <nav className="hidden items-center gap-1 xl:flex">
                    {mainNav.map((item) =>
                        item.children ? (
                            <div key={item.title} className="group relative">
                                <button
                                    type="button"
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground',
                                        item.href && isActive(item.href) && 'text-primary',
                                    )}
                                >
                                    {item.title}
                                    <ChevronDown className="size-3.5 transition-transform group-hover:rotate-180" />
                                </button>
                                <div className="invisible absolute left-0 top-full z-50 min-w-56 translate-y-1 rounded-md border border-border bg-popover p-1 opacity-0 shadow-lg transition-all group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                                    {item.href && (
                                        <Link href={item.href} className="block rounded-sm px-3 py-2 text-sm font-medium hover:bg-accent">
                                            {item.title} — prezentare
                                        </Link>
                                    )}
                                    {item.children.map((child) => (
                                        <Link key={child.href} href={child.href} className={cn('block rounded-sm px-3 py-2 text-sm hover:bg-accent', isActive(child.href) && 'bg-accent font-medium')}>
                                            {child.title}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <Link
                                key={item.href}
                                href={item.href!}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground',
                                    isActive(item.href) && 'text-primary',
                                )}
                            >
                                {item.title}
                            </Link>
                        ),
                    )}
                </nav>

                <div className="flex items-center gap-2">
                    {!auth?.user ? (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <Link href={login()}>Autentificare</Link>
                        </Button>
                    ) : auth.canAccessAdmin ? (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <a href="/admin">Panou</a>
                        </Button>
                    ) : (
                        <Button asChild size="sm" className="hidden sm:inline-flex">
                            <Link href={dashboard()}>Cabinet</Link>
                        </Button>
                    )}
                    <button
                        type="button"
                        onClick={() => setMobileOpen((v) => !v)}
                        className="inline-flex size-9 items-center justify-center rounded-md border border-border xl:hidden"
                        aria-label="Meniu"
                    >
                        {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                </div>
            </div>

            {/* Navigare mobil */}
            {mobileOpen && (
                <div className="border-t border-border bg-background xl:hidden">
                    <nav className="mx-auto max-w-7xl space-y-1 px-6 py-4">
                        {mainNav.map((item) =>
                            item.children ? (
                                <details key={item.title} className="group">
                                    <summary className="flex cursor-pointer list-none items-center justify-between rounded-md px-3 py-2 text-sm font-medium hover:bg-accent">
                                        {item.title}
                                        <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div className="ml-3 border-l border-border pl-3">
                                        {item.href && (
                                            <Link href={item.href} className="block rounded-sm px-3 py-2 text-sm hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                                {item.title} — prezentare
                                            </Link>
                                        )}
                                        {item.children.map((child) => (
                                            <Link key={child.href} href={child.href} className="block rounded-sm px-3 py-2 text-sm hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                                {child.title}
                                            </Link>
                                        ))}
                                    </div>
                                </details>
                            ) : (
                                <Link key={item.href} href={item.href!} className="block rounded-md px-3 py-2 text-sm font-medium hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                    {item.title}
                                </Link>
                            ),
                        )}
                        <div className="mt-2 border-t border-border pt-2">
                            {utilityNav.map((item) => (
                                <Link key={item.href} href={item.href} className="block rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent" onClick={() => setMobileOpen(false)}>
                                    {item.title}
                                </Link>
                            ))}
                        </div>
                    </nav>
                </div>
            )}
        </header>
    );
}
