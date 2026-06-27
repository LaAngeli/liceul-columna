import { Mail, MapPin, Phone } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { useTranslations } from '@/lib/i18n';
import { footerNav, siteContact } from '@/lib/public-navigation';

// lucide-react (v0.475) nu mai exportă iconițe de brand → SVG inline (path-uri simple-icons).
function FacebookIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className={className}>
            <path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z" />
        </svg>
    );
}

function InstagramIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className={className}>
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z" />
        </svg>
    );
}

export function SiteFooter() {
    const t = useTranslations();

    const contactRows = [
        {
            icon: MapPin,
            label: t('contact.address', 'Adresă'),
            value: siteContact.address,
            href: `https://maps.google.com/?q=${encodeURIComponent(siteContact.address)}`,
            external: true,
        },
        {
            icon: Phone,
            label: t('contact.phone', 'Telefon'),
            value: siteContact.phone,
            href: `tel:${siteContact.phone.replace(/[^+\d]/g, '')}`,
            external: false,
        },
        {
            icon: Mail,
            label: t('contact.email', 'E-mail'),
            value: siteContact.email,
            href: `mailto:${siteContact.email}`,
            external: false,
        },
    ] as const;

    const socialLinks = [
        { icon: FacebookIcon, label: 'Facebook', href: 'https://www.facebook.com/ColumnaLyceum' },
        { icon: InstagramIcon, label: 'Instagram', href: 'https://www.instagram.com/liceul.columna/' },
    ] as const;

    return (
        <footer className="mt-16 border-t border-border bg-muted/30">
            <div className="mx-auto grid max-w-7xl gap-8 px-6 py-12 md:grid-cols-2">
                <div className="flex flex-col items-center text-center">
                    <LocaleLink href="/" className="inline-flex flex-col items-center gap-2.5">
                        <img src="/images/logo/columna-navy.png" alt="Liceul Columna" className="h-14 w-auto dark:hidden" />
                        <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="hidden h-14 w-auto dark:block" />
                        <span className="font-serif text-lg font-semibold">Liceul Columna</span>
                    </LocaleLink>

                    <p className="mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">{t('home.tagline', siteContact.tagline)}</p>

                    <ul className="mx-auto mt-6 flex w-fit max-w-full flex-col gap-3 text-left">
                        {contactRows.map(({ icon: Icon, label, value, href, external }) => (
                            <li key={label}>
                                <a
                                    href={href}
                                    target={external ? '_blank' : undefined}
                                    rel={external ? 'noreferrer' : undefined}
                                    className="group flex min-h-11 items-start gap-3"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-card text-primary transition-colors group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground">
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-xs text-muted-foreground">{label}</span>
                                        <span className="block text-sm font-medium text-foreground transition-colors group-hover:text-primary">{value}</span>
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-7">
                        <p className="text-xs font-medium text-muted-foreground">{t('footer.follow', 'Urmărește-ne')}</p>
                        <div className="mt-3 flex items-center justify-center gap-3">
                            {socialLinks.map(({ icon: Icon, label, href }) => (
                                <a
                                    key={label}
                                    href={href}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={label}
                                    title={label}
                                    className="flex size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground transition-colors hover:border-primary hover:bg-primary hover:text-primary-foreground"
                                >
                                    <Icon className="size-5" />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-8 sm:grid-cols-2">
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
