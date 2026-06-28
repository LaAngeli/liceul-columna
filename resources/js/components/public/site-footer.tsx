import { Mail, MapPin, Phone } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Container, FourStar } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';
import { footerNav, siteContact } from '@/lib/public-navigation';

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

const VALUES: [string, string][] = [
    ['credinta', 'Credință'], ['onoare', 'Onoare'], ['libertate', 'Libertate'], ['unire', 'Unire'],
    ['munca', 'Munca'], ['natiune', 'Națiune'], ['adevar', 'Adevăr'],
];

export function SiteFooter() {
    const t = useTranslations();

    const contactRows = [
        { icon: MapPin, value: siteContact.address, href: `https://maps.google.com/?q=${encodeURIComponent(siteContact.address)}`, external: true },
        { icon: Phone, value: siteContact.phone, href: 'tel:+37322742852', external: false },
        { icon: Mail, value: siteContact.email, href: `mailto:${siteContact.email}`, external: false },
    ] as const;

    const social = [
        { icon: FacebookIcon, label: 'Facebook', href: 'https://www.facebook.com/ColumnaLyceum' },
        { icon: InstagramIcon, label: 'Instagram', href: 'https://www.instagram.com/liceul.columna/' },
    ] as const;

    return (
        <footer className="on-navy relative mt-auto overflow-hidden bg-brand-navy text-[color:var(--brand-navy-foreground)]">
            {/* Watermark crest */}
            <img
                src="/images/logo/columna-white.png"
                alt=""
                aria-hidden="true"
                className="pointer-events-none absolute -right-16 -bottom-16 w-[28rem] max-w-[60%] opacity-[0.05] select-none"
            />

            <Container className="relative py-14">
                {/* Colofon */}
                <div className="flex flex-col items-start gap-5 border-b border-white/15 pb-10 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <LocaleLink href="/" aria-label="Liceul Columna" className="inline-flex items-center gap-3">
                            <img src="/images/logo/columna-white.png" alt="Liceul Columna" className="h-14 w-auto" />
                            <span className="numeral text-2xl text-white/40">est. 1998</span>
                        </LocaleLink>
                        <p className="mt-5 max-w-md text-[clamp(1.25rem,2.6vw,1.75rem)] leading-tight" style={{ fontFamily: 'var(--font-display)', fontWeight: 700 }}>
                            {t('home.hero_title_a', 'Succesul copilului')} <span className="text-brand-green">{t('home.hero_title_b', 'începe aici.')}</span>
                        </p>
                    </div>
                    <ul className="flex flex-col gap-2.5 text-sm">
                        {contactRows.map(({ icon: Icon, value, href, external }) => (
                            <li key={value}>
                                <a href={href} {...(external ? { target: '_blank', rel: 'noreferrer' } : {})} className="group inline-flex min-h-11 items-center gap-2.5 text-white/85 hover:text-white md:min-h-9">
                                    <Icon className="size-4 shrink-0 text-brand-green" />
                                    {value}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* Valori */}
                <ul className="flex flex-wrap items-center gap-x-2 gap-y-3 border-b border-white/15 py-7">
                    {VALUES.map(([key, fb], i) => (
                        <li key={key} className="flex items-center gap-2">
                            <span className="text-[0.8125rem] font-bold tracking-[0.08em] text-white/90 uppercase sm:tracking-[0.14em]" style={{ fontFamily: 'var(--font-display)' }}>
                                {t(`values.${key}`, fb)}
                            </span>
                            {i < VALUES.length - 1 && <FourStar className="size-2.5 text-brand-green" />}
                        </li>
                    ))}
                </ul>

                {/* Sitemap */}
                <nav className="grid grid-cols-2 gap-8 py-10 sm:grid-cols-3 lg:grid-cols-5">
                    {footerNav.map((column) => (
                        <div key={column.title}>
                            <h3 className="eyebrow text-white/55">{t(column.tKey, column.title)}</h3>
                            <ul className="mt-4 space-y-1">
                                {column.links.map((link) => (
                                    <li key={link.href}>
                                        <LocaleLink href={link.href} className="flex min-h-11 items-center gap-2 text-sm text-white/80 hover:text-white md:min-h-10">
                                            <FourStar className="size-2 shrink-0 text-brand-green/70" />
                                            {link.tKey ? t(link.tKey, link.title) : link.title}
                                        </LocaleLink>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>

                {/* Social */}
                <div className="flex items-center justify-center gap-3 border-t border-white/15 pt-8">
                    {social.map(({ icon: Icon, label, href }) => (
                        <a key={label} href={href} target="_blank" rel="noreferrer" aria-label={label} title={label} className="flex size-11 items-center justify-center rounded-full border border-white/25 text-white/85 transition-colors hover:border-brand-green hover:text-brand-green">
                            <Icon className="size-5" />
                        </a>
                    ))}
                </div>
            </Container>

            <div className="border-t border-white/15">
                <Container className="flex flex-col items-center justify-between gap-2 py-5 text-xs text-white/60 sm:flex-row">
                    <p>
                        © {new Date().getFullYear()} IPL „Liceul Columna”. {t('footer.rights', 'Toate drepturile rezervate.')}{' '}
                        <LocaleLink href="/confidentialitate" className="underline decoration-white/30 underline-offset-2 hover:text-white/90">
                            {t('footer.privacy', 'Confidențialitate')}
                        </LocaleLink>
                        {' · '}
                        <button
                            type="button"
                            onClick={() => window.dispatchEvent(new CustomEvent('cookie-settings:open'))}
                            className="underline decoration-white/30 underline-offset-2 hover:text-white/90"
                        >
                            {t('cookies.settings', 'Setări cookies')}
                        </button>
                    </p>
                    <p>
                        Created by{' '}
                        <a href="https://advista.marketing/" target="_blank" rel="noopener noreferrer" className="font-medium transition-colors hover:text-[rgb(228,81,55)]">
                            AdVista
                        </a>
                    </p>
                </Container>
            </div>
        </footer>
    );
}
