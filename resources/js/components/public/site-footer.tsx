import { Link } from '@inertiajs/react';
import { MapPin, Mail, Phone } from 'lucide-react';
import { footerNav, siteContact } from '@/lib/public-navigation';

export function SiteFooter() {
    return (
        <footer className="mt-16 border-t border-border bg-muted/30">
            <div className="mx-auto grid max-w-7xl gap-8 px-6 py-12 md:grid-cols-2 lg:grid-cols-5">
                <div className="lg:col-span-1">
                    <div className="flex items-center gap-2.5">
                        <img src="/images/logo/columna-navy.png" alt="Liceul Columna" className="h-12 w-auto dark:hidden" />
                        <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="hidden h-12 w-auto dark:block" />
                        <span className="font-serif font-semibold">Liceul Columna</span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">{siteContact.tagline}</p>
                    <ul className="mt-4 space-y-2 text-sm text-muted-foreground">
                        <li className="flex items-start gap-2">
                            <MapPin className="mt-0.5 size-4 shrink-0" /> {siteContact.address}
                        </li>
                        <li className="flex items-center gap-2">
                            <Phone className="size-4 shrink-0" /> {siteContact.phone}
                        </li>
                        <li className="flex items-center gap-2">
                            <Mail className="size-4 shrink-0" /> {siteContact.email}
                        </li>
                    </ul>
                </div>

                {footerNav.map((column) => (
                    <div key={column.title}>
                        <h3 className="text-sm font-semibold">{column.title}</h3>
                        <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                            {column.links.map((link) => (
                                <li key={link.href}>
                                    <Link href={link.href} className="hover:text-foreground">
                                        {link.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>

            <div className="border-t border-border">
                <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-6 py-4 text-xs text-muted-foreground sm:flex-row">
                    <p>© {new Date().getFullYear()} IPL „Liceul Columna”. Toate drepturile rezervate.</p>
                    <p>Chișinău, Republica Moldova</p>
                </div>
            </div>
        </footer>
    );
}
