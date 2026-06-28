import { ArrowRight, Check, Download, FileText, ImageIcon, Mail, MapPin, Phone, Plus } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { BrandButton, Container, FourStar, Reveal } from '@/components/public/brand';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';
import { cn } from '@/lib/utils';

/**
 * Motorul de secțiuni pentru paginile publice. Conținutul vine din PHP
 * (App\Support\PublicPageContent); aici doar îl randăm în „Columna Civic Editorial".
 */
type Action = { label: string; href: string; variant?: 'primary' | 'outline' };

export type PageSection =
    | { type: 'lead'; text: string }
    | { type: 'prose'; paragraphs: string[] }
    | { type: 'heading'; text: string; level?: 2 | 3 }
    | { type: 'list'; items: string[]; variant?: 'check' | 'bullet' }
    | { type: 'cards'; columns?: 2 | 3; items: { title: string; text?: string; href?: string; image?: string | null }[] }
    | { type: 'figure'; ratio?: '16/9' | '4/3' | '1/1' | '3/4'; caption?: string; label?: string }
    | { type: 'gallery'; count?: number; images?: { src: string; alt?: string }[] }
    | { type: 'downloads'; items: { label: string; note?: string; href?: string }[] }
    | { type: 'contact' }
    | { type: 'signature'; name: string; role: string }
    | { type: 'cta'; title: string; text?: string; actions: Action[] }
    | { type: 'map'; src: string; title?: string }
    | { type: 'faq'; items: { question: string; answer: string }[] }
    | { type: 'table'; label?: string; headers: string[]; rows: string[][] };

const ratioClass: Record<NonNullable<Extract<PageSection, { type: 'figure' }>['ratio']>, string> = {
    '16/9': 'aspect-video',
    '4/3': 'aspect-[4/3]',
    '1/1': 'aspect-square',
    '3/4': 'aspect-[3/4]',
};

/** Placeholder de media (panou navy discret + crest) până la încărcarea imaginilor reale. */
function MediaPlaceholder({ className, label }: { className?: string; label: string }) {
    return (
        <div className={cn('flex w-full items-center justify-center rounded-[12px] border border-dashed keyline bg-brand-navy/[0.03] text-brand-navy/40', className)}>
            <span className="inline-flex items-center gap-2 text-sm">
                <ImageIcon className="size-5" />
                {label}
            </span>
        </div>
    );
}

function SectionBlock({ section }: { section: PageSection }) {
    const t = useTranslations();

    switch (section.type) {
        case 'lead':
            return <p className="max-w-[60ch] text-[clamp(1.0625rem,1.6vw,1.25rem)] leading-relaxed font-medium text-brand-navy/90">{section.text}</p>;

        case 'prose':
            return (
                <div className="max-w-[66ch] space-y-4 text-[1.0625rem] leading-[1.7] text-brand-dark/90">
                    {section.paragraphs.map((p, i) => (
                        <p key={i}>{p}</p>
                    ))}
                </div>
            );

        case 'heading': {
            const Tag = section.level === 3 ? 'h3' : 'h2';
            return (
                <div className="flex flex-col gap-3">
                    <div className="flex items-center gap-3">
                        <FourStar className="size-3 shrink-0 text-brand-green" />
                        <span className="h-px w-10 keyline border-t" aria-hidden="true" />
                    </div>
                    <Tag className={cn('display text-brand-navy', section.level === 3 ? 'text-[1.375rem]' : 'text-[clamp(1.5rem,3vw,2.375rem)]')}>{section.text}</Tag>
                </div>
            );
        }

        case 'list':
            return (
                <ul className="max-w-[66ch] space-y-3">
                    {section.items.map((item, i) => (
                        <li key={i} className="flex gap-3">
                            {section.variant === 'bullet' ? (
                                <FourStar className="mt-1.5 size-3 shrink-0 text-brand-green" />
                            ) : (
                                <Check className="mt-0.5 size-5 shrink-0 text-brand-green" />
                            )}
                            <span className="leading-relaxed text-brand-dark/90">{item}</span>
                        </li>
                    ))}
                </ul>
            );

        case 'cards':
            return (
                <div className={cn('grid gap-5', section.columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3')}>
                    {section.items.map((card) => {
                        const inner = card.image ? (
                            <div className="flex items-start gap-4">
                                <img src={card.image} alt={card.title} loading="lazy" className="size-16 shrink-0 rounded-full border keyline object-cover" />
                                <div className="min-w-0">
                                    <h3 className="heading-dynamic text-lg text-brand-navy">{card.title}</h3>
                                    {card.text && <p className="mt-1 text-sm leading-relaxed text-brand-gray">{card.text}</p>}
                                </div>
                            </div>
                        ) : (
                            <>
                                <h3 className="display text-[1.25rem] text-brand-navy">{card.title}</h3>
                                {card.text && <p className="mt-2 text-sm leading-relaxed text-brand-gray">{card.text}</p>}
                                {card.href && (
                                    <span className="mt-3 inline-flex items-center gap-1.5 font-semibold text-brand-navy underline decoration-brand-green decoration-2 underline-offset-4">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-3.5" />
                                    </span>
                                )}
                            </>
                        );
                        const base = 'flex h-full flex-col rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card p-6';
                        return card.href ? (
                            <LocaleLink key={card.title} href={card.href} className={cn(base, 'group transition-all hover:-translate-y-0.5 hover:border-l-brand-green')}>
                                {inner}
                            </LocaleLink>
                        ) : (
                            <div key={card.title} className={base}>
                                {inner}
                            </div>
                        );
                    })}
                </div>
            );

        case 'figure':
            return (
                <figure className="max-w-4xl">
                    <MediaPlaceholder className={ratioClass[section.ratio ?? '16/9']} label={section.label ?? t('section.image_soon', 'Imagine în curând')} />
                    {section.caption && <figcaption className="mt-2 text-sm text-brand-gray">{section.caption}</figcaption>}
                </figure>
            );

        case 'gallery':
            if (section.images && section.images.length > 0) {
                return (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {section.images.map((image, i) => (
                            <a key={i} href={image.src} target="_blank" rel="noreferrer" className="photo-frame group block overflow-hidden rounded-[10px] border keyline">
                                <img src={image.src} alt={image.alt ?? ''} loading="lazy" className="aspect-square w-full object-cover" />
                            </a>
                        ))}
                    </div>
                );
            }
            return (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {Array.from({ length: section.count ?? 6 }).map((_, i) => (
                        <MediaPlaceholder key={i} className="aspect-square" label={t('section.photo', 'Foto')} />
                    ))}
                </div>
            );

        case 'downloads':
            return (
                <ul className="max-w-3xl divide-y divide-[color:var(--keyline)] overflow-hidden rounded-[12px] border keyline">
                    {section.items.map((item, i) => (
                        <li key={i} className="flex items-center justify-between gap-4 bg-card p-4">
                            <div className="flex min-w-0 items-center gap-3">
                                <FileText className="size-5 shrink-0 text-brand-navy" />
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-brand-navy">{item.label}</p>
                                    {item.note && <p className="truncate text-xs text-brand-gray">{item.note}</p>}
                                </div>
                            </div>
                            {item.href ? (
                                <a href={item.href} download className="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-[12px] border border-brand-navy px-4 font-semibold text-brand-navy transition-colors hover:bg-brand-navy hover:text-[color:var(--brand-navy-foreground)]">
                                    <Download className="size-4" /> {t('section.download', 'Descarcă')}
                                </a>
                            ) : (
                                <span className="shrink-0 rounded-full bg-brand-navy/8 px-2.5 py-1 text-xs text-brand-gray">{t('section.soon', 'în curând')}</span>
                            )}
                        </li>
                    ))}
                </ul>
            );

        case 'contact': {
            const rows = [
                { icon: MapPin, label: t('contact.address', 'Adresă'), value: siteContact.address, href: `https://maps.google.com/?q=${encodeURIComponent(siteContact.address)}`, external: true },
                { icon: Phone, label: t('contact.phone', 'Telefon'), value: siteContact.phone, href: 'tel:+37322742852', external: false },
                { icon: Mail, label: t('contact.email', 'E-mail'), value: siteContact.email, href: `mailto:${siteContact.email}`, external: false },
            ];
            return (
                <div className="grid max-w-4xl gap-4 sm:grid-cols-3">
                    {rows.map(({ icon: Icon, label, value, href, external }) => (
                        <a key={label} href={href} {...(external ? { target: '_blank', rel: 'noreferrer' } : {})} className="group flex items-start gap-3 rounded-[12px] border keyline border-l-[5px] border-l-brand-navy bg-card p-5 transition-all hover:-translate-y-0.5 hover:border-l-brand-green">
                            <Icon className="mt-0.5 size-5 shrink-0 text-brand-green" />
                            <span>
                                <span className="block text-sm font-semibold text-brand-navy">{label}</span>
                                <span className="mt-1 block text-sm text-brand-gray">{value}</span>
                            </span>
                        </a>
                    ))}
                </div>
            );
        }

        case 'signature':
            return (
                <div className="max-w-3xl border-t-2 keyline pt-4">
                    <p className="display text-xl text-brand-navy">{section.name}</p>
                    <p className="eyebrow mt-1 text-brand-gray">{section.role}</p>
                </div>
            );

        case 'cta':
            return (
                <div className="on-navy relative overflow-hidden rounded-[16px] bg-brand-navy px-6 py-9 text-[color:var(--brand-navy-foreground)] sm:px-10">
                    <div className="dotgrid pointer-events-none absolute inset-0 opacity-[0.12]" aria-hidden="true" />
                    <div className="relative">
                        <h2 className="display text-[clamp(1.375rem,3vw,2rem)] text-[color:var(--brand-navy-foreground)]">{section.title}</h2>
                        {section.text && <p className="mt-2 max-w-2xl text-white/80">{section.text}</p>}
                        <div className="mt-6 flex flex-wrap gap-3">
                            {section.actions.map((action) => (
                                <BrandButton key={action.href} href={action.href} variant={action.variant === 'outline' ? 'ghost-navy' : 'primary'} icon={ArrowRight}>
                                    {action.label}
                                </BrandButton>
                            ))}
                        </div>
                    </div>
                </div>
            );

        case 'map':
            return (
                <div className="max-w-4xl overflow-hidden rounded-[12px] border keyline">
                    <iframe src={section.src} title={section.title ?? 'Hartă'} className="aspect-video w-full" loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen />
                </div>
            );

        case 'faq':
            return (
                <div className="max-w-3xl overflow-hidden rounded-[12px] border keyline">
                    {section.items.map((item, i) => (
                        <details key={i} className="group border-t keyline bg-card first:border-t-0">
                            <summary className="flex min-h-12 cursor-pointer list-none items-center justify-between gap-4 p-4 font-semibold text-brand-navy">
                                {item.question}
                                <Plus className="size-4 shrink-0 text-brand-green transition-transform group-open:rotate-45" />
                            </summary>
                            <p className="px-4 pb-4 leading-relaxed text-brand-dark/90">{item.answer}</p>
                        </details>
                    ))}
                </div>
            );

        case 'table':
            return (
                <figure className="max-w-full">
                    {section.label && <figcaption className="mb-3 display text-[1.25rem] text-brand-navy">{section.label}</figcaption>}
                    <div className="overflow-x-auto rounded-[12px] border keyline">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-brand-navy text-[color:var(--brand-navy-foreground)]">
                                    {section.headers.map((header, i) => (
                                        <th key={i} className="px-3 py-2.5 text-left font-semibold">{header}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {section.rows.map((row, ri) => (
                                    <tr key={ri} className="even:bg-brand-navy/[0.03]">
                                        {row.map((cell, ci) => (
                                            <td key={ci} className="border-t keyline px-3 py-2 align-top break-words text-brand-dark/90">{cell}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </figure>
            );

        default:
            return null;
    }
}

export function PageSections({ sections }: { sections: PageSection[] }) {
    return (
        <Container className="py-[clamp(2.5rem,6vw,5rem)]">
            <div className="space-y-10 sm:space-y-14">
                {sections.map((section, i) => (
                    <Reveal key={i}>
                        <SectionBlock section={section} />
                    </Reveal>
                ))}
            </div>
        </Container>
    );
}
