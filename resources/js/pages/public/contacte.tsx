import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Clock, GraduationCap, Mail, MapPin, Phone, Send } from 'lucide-react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
import { LocaleLink } from '@/components/locale-link';
import { Band, BrandButton, FourStar, Reveal } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';
import { cn } from '@/lib/utils';

const MAP =
    'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2719.635001013197!2d28.786920515806074!3d47.02776913577571!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40c97de6edb81515%3A0x4c1eb7bb1962c7ea!2sColumna!5e0!3m2!1sro!2smd!4v1642691379746';

function FacebookIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className={className}>
            <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z" />
        </svg>
    );
}
function InstagramIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className={className}>
            <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 1.62c-3.15 0-3.52.01-4.76.07-.95.04-1.47.2-1.81.34-.46.18-.78.39-1.12.73-.34.34-.55.66-.73 1.12-.14.34-.3.86-.34 1.81-.06 1.24-.07 1.61-.07 4.76s.01 3.52.07 4.76c.04.95.2 1.47.34 1.81.18.46.39.78.73 1.12.34.34.66.55 1.12.73.34.14.86.3 1.81.34 1.24.06 1.61.07 4.76.07s3.52-.01 4.76-.07c.95-.04 1.47-.2 1.81-.34.46-.18.78-.39 1.12-.73.34-.34.55-.66.73-1.12.14-.34.3-.86.34-1.81.06-1.24.07-1.61.07-4.76s-.01-3.52-.07-4.76c-.04-.95-.2-1.47-.34-1.81a3.02 3.02 0 0 0-.73-1.12 3.02 3.02 0 0 0-1.12-.73c-.34-.14-.86-.3-1.81-.34-1.24-.06-1.61-.07-4.76-.07zm0 2.76a5.46 5.46 0 1 1 0 10.92 5.46 5.46 0 0 1 0-10.92zm0 9a3.54 3.54 0 1 0 0-7.08 3.54 3.54 0 0 0 0 7.08zm6.95-9.22a1.28 1.28 0 1 1-2.56 0 1.28 1.28 0 0 1 2.56 0z" />
        </svg>
    );
}

const INPUT =
    'h-12 w-full rounded-[12px] border keyline bg-card px-4 text-base text-brand-navy shadow-sm outline-none transition-colors placeholder:text-brand-gray/60 focus:border-brand-green focus:ring-2 focus:ring-brand-green/30';

function Field({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-brand-navy">
                {label} {hint && <span className="font-normal text-brand-gray">{hint}</span>}
            </span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-600">{error}</span>}
        </label>
    );
}

interface Row {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: string;
    href: string | null;
    external?: boolean;
}

export default function Contacte() {
    const t = useTranslations();
    const heading = t('contact.heading', 'Contacte');
    const postUrl = usePage().url.split('?')[0];

    const form = useForm({ name: '', email: '', phone: '', subject: '', message: '', consent: false, website: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(postUrl, { preserveScroll: true });
    };

    const rows: Row[] = [
        { icon: MapPin, label: t('contact.address', 'Adresă'), value: siteContact.address, href: `https://maps.google.com/?q=${encodeURIComponent(siteContact.address)}`, external: true },
        { icon: Phone, label: t('contact.phone', 'Telefon'), value: siteContact.phone, href: 'tel:+37322742852' },
        { icon: Mail, label: t('contact.email', 'E-mail'), value: siteContact.email, href: `mailto:${siteContact.email}` },
        { icon: Clock, label: t('contact.hours', 'Program'), value: t('contact.hours_value', 'Luni – Vineri'), href: null },
    ];

    const social = [
        { Icon: FacebookIcon, label: 'Facebook', href: 'https://www.facebook.com/ColumnaLyceum' },
        { Icon: InstagramIcon, label: 'Instagram', href: 'https://www.instagram.com/liceul.columna/' },
    ];

    return (
        <>
            <Head title={heading} />

            <PageBanner title={heading} breadcrumbs={[{ title: heading }]} description={t('contact.lead')} />

            <Band variant="light">
                <div className="grid gap-6 lg:grid-cols-2 lg:items-stretch">
                    {/* Panou de contact */}
                    <Reveal className="flex flex-col rounded-[16px] border keyline bg-card p-6 sm:p-8">
                        <span className="eyebrow inline-flex items-center gap-2 text-brand-navy">
                            <FourStar className="size-3 text-brand-green" /> {t('contact.details', 'Date de contact')}
                        </span>
                        <p className="display mt-3 text-[clamp(1.25rem,4.5vw,1.5rem)] text-brand-navy">{siteContact.name}</p>
                        <p className="mt-1 text-sm text-brand-gray">{siteContact.tagline}</p>

                        <ul className="mt-6 space-y-3">
                            {rows.map((row) => {
                                const inner = (
                                    <>
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-brand-navy/8 text-brand-green">
                                            <row.icon className="size-5" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-xs font-semibold tracking-wide text-brand-gray uppercase">{row.label}</span>
                                            <span className="block font-semibold break-words text-brand-navy">{row.value}</span>
                                        </span>
                                    </>
                                );
                                const cls = 'flex items-center gap-3 rounded-[10px] border keyline border-l-[5px] border-l-brand-navy bg-card p-3';

                                return (
                                    <li key={row.label}>
                                        {row.href ? (
                                            <a href={row.href} {...(row.external ? { target: '_blank', rel: 'noreferrer' } : {})} className={`${cls} group transition-all hover:-translate-y-0.5 hover:border-l-brand-green`}>
                                                {inner}
                                            </a>
                                        ) : (
                                            <div className={cls}>{inner}</div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>

                        <div className="mt-6 border-t keyline pt-5 lg:mt-auto">
                            <span className="eyebrow text-brand-gray">{t('contact.follow', 'Urmărește-ne')}</span>
                            <div className="mt-3 flex gap-3">
                                {social.map(({ Icon, label, href }) => (
                                    <a
                                        key={label}
                                        href={href}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={label}
                                        title={label}
                                        className="flex size-11 items-center justify-center rounded-full border keyline text-brand-navy transition-colors hover:border-brand-green hover:bg-brand-green/10"
                                    >
                                        <Icon className="size-5" />
                                    </a>
                                ))}
                            </div>
                        </div>
                    </Reveal>

                    {/* Formular de contact */}
                    <Reveal className="rounded-[16px] border keyline bg-card p-6 sm:p-8">
                        <h2 className="display text-[1.5rem] text-brand-navy">{t('contact.form_title', 'Trimite-ne un mesaj')}</h2>
                        <p className="mt-1.5 text-sm leading-relaxed text-brand-gray">{t('contact.form_intro')}</p>

                        <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('contact.f_name', 'Nume')} error={form.errors.name}>
                                    <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder={t('contact.f_name_ph', 'Numele tău')} autoComplete="name" className={INPUT} required />
                                </Field>
                                <Field label={t('contact.f_email', 'E-mail')} error={form.errors.email}>
                                    <input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder={t('contact.f_email_ph', 'adresa@exemplu.md')} autoComplete="email" className={INPUT} required />
                                </Field>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('contact.f_phone', 'Telefon')} hint={t('contact.f_phone_opt', '(opțional)')} error={form.errors.phone}>
                                    <input type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} autoComplete="tel" className={INPUT} />
                                </Field>
                                <Field label={t('contact.f_subject', 'Subiect')} error={form.errors.subject}>
                                    <input type="text" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} placeholder={t('contact.f_subject_ph', 'Despre ce ne scrii?')} className={INPUT} required />
                                </Field>
                            </div>
                            <Field label={t('contact.f_message', 'Mesaj')} error={form.errors.message}>
                                <textarea value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} placeholder={t('contact.f_message_ph', 'Scrie-ne mesajul tău…')} rows={5} className={cn(INPUT, 'h-auto resize-y py-3')} required />
                            </Field>

                            {/* Honeypot anti-spam (ascuns) */}
                            <div className="hidden" aria-hidden="true">
                                <label>
                                    Website
                                    <input type="text" tabIndex={-1} autoComplete="off" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
                                </label>
                            </div>

                            <label className="flex items-start gap-2.5">
                                <input
                                    type="checkbox"
                                    checked={form.data.consent}
                                    onChange={(e) => form.setData('consent', e.target.checked)}
                                    className="mt-0.5 size-5 shrink-0 rounded border-brand-navy/30 text-brand-green focus:ring-brand-green/40"
                                />
                                <span className="text-sm text-brand-gray">
                                    {t('contact.consent_pre', 'Sunt de acord cu prelucrarea datelor mele conform')}{' '}
                                    <LocaleLink href="/confidentialitate" className="font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-2">
                                        {t('contact.consent_link', 'Politicii de confidențialitate')}
                                    </LocaleLink>
                                    .
                                </span>
                            </label>
                            {form.errors.consent && <span className="block text-sm text-red-600">{form.errors.consent}</span>}

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-[12px] bg-brand-green px-6 font-semibold text-[color:var(--brand-green-foreground)] shadow-sm transition-all hover:brightness-[1.04] active:scale-[0.99] disabled:opacity-60 sm:w-auto"
                            >
                                {form.processing ? t('contact.sending', 'Se trimite…') : t('contact.send', 'Trimite mesajul')}
                                <Send className="size-4" />
                            </button>
                        </form>
                    </Reveal>
                </div>
            </Band>

            {/* Hartă pe toată lățimea */}
            <Band variant="light" className="!pt-0">
                <Reveal className="overflow-hidden rounded-[16px] border keyline">
                    <iframe src={MAP} title={t('contact.map_title', 'Liceul „Columna" pe hartă')} className="h-[360px] w-full sm:h-[440px]" loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen />
                </Reveal>
            </Band>

            {/* CTA — vizită / înscriere */}
            <Band variant="navy" pattern="dotgrid">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="display text-[clamp(1.5rem,3vw,2.25rem)] text-[color:var(--brand-navy-foreground)]">{t('contact.visit_title', 'Vino să ne cunoști')}</h2>
                    <p className="mt-3 leading-relaxed text-white/85">{t('contact.visit_text')}</p>
                    <div className="mt-7 flex flex-wrap justify-center gap-3">
                        <BrandButton href="/admitere" variant="ghost-navy" icon={ArrowRight}>
                            {t('contact.visit_cta', 'Vezi pașii de admitere')}
                        </BrandButton>
                        <BrandButton href="/inregistrarea-student" variant="primary" icon={GraduationCap}>
                            {t('menu.enroll', 'Înscrie copilul')}
                        </BrandButton>
                    </div>
                </div>
            </Band>
        </>
    );
}
