import { ArrowRight, Check, Download, FileText, ImageIcon, Mail, MapPin, Phone } from 'lucide-react';
import { LocaleLink } from '@/components/locale-link';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/lib/i18n';
import { siteContact } from '@/lib/public-navigation';
import { cn } from '@/lib/utils';

/**
 * Schema de secțiuni pentru paginile publice. Sursa de conținut e PHP
 * (App\Support\PublicPageContent); aici doar le randăm în brandul nou.
 * Placeholderele de imagine/galerie țin locul mediei reale până la încărcare.
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
    | { type: 'table'; label?: string; headers: string[]; rows: string[][] };

const ratioClass: Record<NonNullable<Extract<PageSection, { type: 'figure' }>['ratio']>, string> = {
    '16/9': 'aspect-video',
    '4/3': 'aspect-[4/3]',
    '1/1': 'aspect-square',
    '3/4': 'aspect-[3/4]',
};

/** Placeholder de media — „ceva de umplutură" până la încărcarea imaginilor reale. */
function MediaPlaceholder({ className, label }: { className?: string; label: string }) {
    return (
        <div
            className={cn(
                'flex w-full items-center justify-center rounded-lg border border-dashed border-border bg-muted/50 text-muted-foreground',
                className,
            )}
        >
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
            return <p className="max-w-3xl text-lg leading-relaxed text-muted-foreground">{section.text}</p>;

        case 'prose':
            return (
                <div className="max-w-3xl space-y-4 leading-relaxed text-foreground/90">
                    {section.paragraphs.map((p, i) => (
                        <p key={i}>{p}</p>
                    ))}
                </div>
            );

        case 'heading': {
            const Tag = section.level === 3 ? 'h3' : 'h2';
            return <Tag className="font-serif text-2xl font-bold tracking-tight sm:text-3xl">{section.text}</Tag>;
        }

        case 'list':
            return (
                <ul className="max-w-3xl space-y-3">
                    {section.items.map((item, i) => (
                        <li key={i} className="flex gap-3">
                            {section.variant === 'bullet' ? (
                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-brand-green" />
                            ) : (
                                <Check className="mt-0.5 size-5 shrink-0 text-brand-green" />
                            )}
                            <span className="leading-relaxed text-foreground/90">{item}</span>
                        </li>
                    ))}
                </ul>
            );

        case 'cards':
            return (
                <div className={cn('grid gap-4', section.columns === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3')}>
                    {section.items.map((card) => {
                        const inner = card.image ? (
                            <div className="flex items-start gap-4">
                                <img src={card.image} alt={card.title} loading="lazy" className="size-16 shrink-0 rounded-full object-cover" />
                                <div className="min-w-0">
                                    <h3 className="font-serif text-lg font-semibold">{card.title}</h3>
                                    {card.text && <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{card.text}</p>}
                                </div>
                            </div>
                        ) : (
                            <>
                                <h3 className="font-serif text-lg font-semibold">{card.title}</h3>
                                {card.text && <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{card.text}</p>}
                                {card.href && (
                                    <span className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary">
                                        {t('action.details', 'Detalii')} <ArrowRight className="size-3.5" />
                                    </span>
                                )}
                            </>
                        );
                        return card.href ? (
                            <LocaleLink key={card.title} href={card.href} className="group rounded-lg border border-border bg-card p-6 transition-colors hover:border-primary">
                                {inner}
                            </LocaleLink>
                        ) : (
                            <div key={card.title} className="rounded-lg border border-border bg-card p-6">
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
                    {section.caption && <figcaption className="mt-2 text-sm text-muted-foreground">{section.caption}</figcaption>}
                </figure>
            );

        case 'gallery':
            if (section.images && section.images.length > 0) {
                return (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {section.images.map((image, i) => (
                            <a key={i} href={image.src} target="_blank" rel="noreferrer" className="group block overflow-hidden rounded-lg border border-border">
                                <img
                                    src={image.src}
                                    alt={image.alt ?? ''}
                                    loading="lazy"
                                    className="aspect-square w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
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
                <ul className="max-w-3xl divide-y divide-border overflow-hidden rounded-lg border border-border">
                    {section.items.map((item, i) => (
                        <li key={i} className="flex items-center justify-between gap-4 bg-card p-4">
                            <div className="flex min-w-0 items-center gap-3">
                                <FileText className="size-5 shrink-0 text-primary" />
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{item.label}</p>
                                    {item.note && <p className="truncate text-xs text-muted-foreground">{item.note}</p>}
                                </div>
                            </div>
                            {item.href ? (
                                <Button asChild size="sm" variant="outline">
                                    <a href={item.href} download>
                                        <Download className="size-4" /> {t('section.download', 'Descarcă')}
                                    </a>
                                </Button>
                            ) : (
                                <span className="shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">{t('section.soon', 'în curând')}</span>
                            )}
                        </li>
                    ))}
                </ul>
            );

        case 'contact':
            return (
                <div className="grid max-w-4xl gap-4 sm:grid-cols-3">
                    <a
                        href={`https://maps.google.com/?q=${encodeURIComponent(siteContact.address)}`}
                        target="_blank"
                        rel="noreferrer"
                        className="group flex items-start gap-3 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary"
                    >
                        <MapPin className="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>
                            <span className="block text-sm font-semibold">{t('contact.address', 'Adresă')}</span>
                            <span className="mt-1 block text-sm text-muted-foreground">{siteContact.address}</span>
                        </span>
                    </a>
                    <a
                        href={`tel:${siteContact.phone.replace(/[^+\d]/g, '')}`}
                        className="group flex items-start gap-3 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary"
                    >
                        <Phone className="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>
                            <span className="block text-sm font-semibold">{t('contact.phone', 'Telefon')}</span>
                            <span className="mt-1 block text-sm text-muted-foreground">{siteContact.phone}</span>
                        </span>
                    </a>
                    <a
                        href={`mailto:${siteContact.email}`}
                        className="group flex items-start gap-3 rounded-lg border border-border bg-card p-5 transition-colors hover:border-primary"
                    >
                        <Mail className="mt-0.5 size-5 shrink-0 text-primary" />
                        <span>
                            <span className="block text-sm font-semibold">{t('contact.email', 'E-mail')}</span>
                            <span className="mt-1 block text-sm text-muted-foreground">{siteContact.email}</span>
                        </span>
                    </a>
                </div>
            );

        case 'signature':
            return (
                <div className="max-w-3xl border-t border-border pt-4">
                    <p className="font-serif text-lg font-semibold">{section.name}</p>
                    <p className="text-sm text-muted-foreground">{section.role}</p>
                </div>
            );

        case 'cta':
            return (
                <div className="rounded-2xl bg-primary px-6 py-10 text-primary-foreground sm:px-10">
                    <h2 className="font-serif text-2xl font-bold tracking-tight">{section.title}</h2>
                    {section.text && <p className="mt-2 max-w-2xl text-primary-foreground/80">{section.text}</p>}
                    <div className="mt-6 flex flex-wrap gap-3">
                        {section.actions.map((action) => (
                            <Button key={action.href} asChild size="lg" variant={action.variant === 'outline' ? 'outline' : 'secondary'}>
                                <LocaleLink href={action.href}>
                                    {action.label} <ArrowRight className="size-4" />
                                </LocaleLink>
                            </Button>
                        ))}
                    </div>
                </div>
            );

        case 'map':
            return (
                <div className="max-w-4xl overflow-hidden rounded-lg border border-border">
                    <iframe
                        src={section.src}
                        title={section.title ?? 'Hartă'}
                        className="aspect-video w-full"
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                        allowFullScreen
                    />
                </div>
            );

        case 'table':
            return (
                <figure className="max-w-full">
                    {section.label && <figcaption className="mb-2 font-serif text-lg font-semibold">{section.label}</figcaption>}
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-muted/60">
                                    {section.headers.map((header, i) => (
                                        <th key={i} className="border-b border-border px-3 py-2 text-left font-semibold">
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {section.rows.map((row, ri) => (
                                    <tr key={ri} className="even:bg-muted/20">
                                        {row.map((cell, ci) => (
                                            <td key={ci} className="border-t border-border px-3 py-2 align-top">
                                                {cell}
                                            </td>
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
        <section className="mx-auto max-w-7xl px-6 py-12">
            <div className="space-y-12">
                {sections.map((section, i) => (
                    <SectionBlock key={i} section={section} />
                ))}
            </div>
        </section>
    );
}
