import { Mail, MapPin, Phone } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { useTranslations } from '@/lib/i18n';
import { footerNav, siteContact } from '@/lib/public-navigation';

export function SiteFooter() {
    const t = useTranslations();

    return (
        <footer className="mt-16 border-t border-border bg-muted/30">
            <div className="mx-auto grid max-w-7xl gap-8 px-6 py-12 md:grid-cols-2">
                <div>
                    <div className="flex items-center gap-2.5">
                        <img src="/images/logo/columna-navy.png" alt="Liceul Columna" className="h-12 w-auto dark:hidden" />
                        <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="hidden h-12 w-auto dark:block" />
                        <span className="font-serif font-semibold">Liceul Columna</span>
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">{t('home.tagline', siteContact.tagline)}</p>
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

                <div className="grid grid-cols-2 gap-8">
                    {footerNav.map((column) => (
                        <div key={column.title}>
                            <h3 className="text-sm font-semibold">{t(column.tKey, column.title)}</h3>
                            <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                {column.links.map((link) => (
                                    <li key={link.href}>
                                        <LocaleLink href={link.href} className="hover:text-foreground">
                                            {link.tKey ? t(link.tKey, link.title) : link.title}
                                        </LocaleLink>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>

            <div className="border-t border-border">
                <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-6 py-4 text-xs text-muted-foreground sm:flex-row">
                    <p>© {new Date().getFullYear()} IPL „Liceul Columna”. {t('footer.rights', 'Toate drepturile rezervate.')}</p>
                    <p>
                        Created by{' '}
                        <a
                            href="https://advista.marketing/"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium transition-colors hover:text-[rgb(228,81,55)]"
                        >
                            AdVista
                        </a>
                    </p>
                </div>
            </div>
        </footer>
    );
}
