import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Images, Maximize2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Band, Reveal, SectionHeader } from '@/components/public/brand';
import { PageBanner } from '@/components/public/page-banner';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Photo {
    src: string;
    alt: string;
}
interface Album {
    key: string;
    label: string;
    count: number;
    images: Photo[];
}
interface Entry extends Photo {
    albumKey: string;
    albumLabel: string;
}
interface Crumb {
    title: string;
    href?: string;
}
interface Props {
    title: string;
    description?: string;
    breadcrumbs?: Crumb[];
    albums: Album[];
    totalPhotos: number;
}

type Tr = (k: string, f?: string) => string;

/** Lightbox în pagină: navigare prev/next + tastatură (Esc / ← / →), fără a părăsi pagina. */
function Lightbox({ entries, index, onClose, onNav, t }: { entries: Entry[]; index: number; onClose: () => void; onNav: (delta: number) => void; t: Tr }) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
onClose();
} else if (e.key === 'ArrowLeft') {
onNav(-1);
} else if (e.key === 'ArrowRight') {
onNav(1);
}
        };
        window.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = prev;
        };
    }, [onClose, onNav]);

    const entry = entries[index];
    const btn = 'inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition-colors hover:bg-white/20';

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-surface-navy/95 p-4 backdrop-blur-sm sm:p-8" role="dialog" aria-modal="true" onClick={onClose}>
            <button type="button" onClick={onClose} className={cn(btn, 'absolute top-4 right-4 z-10')} aria-label={t('gallery.close', 'Închide')}>
                <X className="size-5" />
            </button>
            {entries.length > 1 && (
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onNav(-1);
                    }}
                    className={cn(btn, 'absolute left-3 sm:left-6')}
                    aria-label={t('gallery.prev', 'Imaginea anterioară')}
                >
                    <ChevronLeft className="size-6" />
                </button>
            )}
            <figure className="flex max-h-full max-w-5xl flex-col items-center" onClick={(e) => e.stopPropagation()}>
                <img src={entry.src} alt={entry.alt} className="max-h-[80vh] w-auto rounded-[10px] object-contain shadow-2xl" />
                <figcaption className="mt-4 flex items-center gap-2 text-sm text-white/80">
                    <span className="font-semibold text-white">{entry.albumLabel}</span>
                    <span className="text-white/40">·</span>
                    <span className="numeral">
                        {index + 1} / {entries.length}
                    </span>
                </figcaption>
            </figure>
            {entries.length > 1 && (
                <button
                    type="button"
                    onClick={(e) => {
                        e.stopPropagation();
                        onNav(1);
                    }}
                    className={cn(btn, 'absolute right-3 sm:right-6')}
                    aria-label={t('gallery.next', 'Imaginea următoare')}
                >
                    <ChevronRight className="size-6" />
                </button>
            )}
        </div>
    );
}

export default function Galerie({ title, description, breadcrumbs = [], albums, totalPhotos }: Props) {
    const t = useTranslations();
    const albumLabel = (a: Album) => t(`gallery.album.${a.key}`, a.label);

    const [active, setActive] = useState('all');
    const [lightbox, setLightbox] = useState<number | null>(null);

    const entries = useMemo<Entry[]>(() => {
        const out: Entry[] = [];

        for (const a of albums) {
            const label = t(`gallery.album.${a.key}`, a.label);

            for (const img of a.images) {
                out.push({ ...img, albumKey: a.key, albumLabel: label });
            }
        }

        return out;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [albums]);

    const view = useMemo(() => (active === 'all' ? entries : entries.filter((e) => e.albumKey === active)), [entries, active]);

    const selectAlbum = (key: string) => {
        setActive(key);
        setLightbox(null);
    };

    const chips = [{ key: 'all', label: t('gallery.all', 'Toate'), count: totalPhotos }, ...albums.map((a) => ({ key: a.key, label: albumLabel(a), count: a.count }))];

    return (
        <>
            <Head title={title} />

            <PageBanner title={title} breadcrumbs={breadcrumbs} description={t('gallery.lead', description)} />

            {albums.length === 0 ? (
                <Band variant="light" pattern="mesh" className="!py-[clamp(2.5rem,5vw,4rem)]">
                    <div className="flex flex-col items-center gap-3 rounded-[14px] border border-dashed keyline bg-card px-6 py-16 text-center">
                        <Images className="size-9 text-brand-navy/30" />
                        <p className="max-w-sm text-brand-gray">{t('gallery.empty', 'Galeria va fi completată în curând.')}</p>
                    </div>
                </Band>
            ) : (
                <>
                    {/* Bandă NAVY — antet + filtrele de album; chip-urile inactive sunt semi-transparente
                        pe navy (bg-white/[0.06] + border-white/20), cel activ verde. */}
                    <Band variant="navy" pattern="mesh" className="!py-[clamp(2rem,4vw,3.5rem)]">
                        <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                            <SectionHeader variant="navy" index="01" label={`${view.length} ${t('gallery.photos', 'fotografii')}`} title={active === 'all' ? t('gallery.all', 'Toate') : chips.find((c) => c.key === active)?.label} />
                        </div>

                        {albums.length > 1 && (
                            <div className="flex flex-wrap gap-2">
                                {chips.map((c) => {
                                    const isActive = c.key === active;

                                    return (
                                        <button
                                            key={c.key}
                                            type="button"
                                            onClick={() => selectAlbum(c.key)}
                                            className={cn(
                                                'inline-flex min-h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold transition-colors',
                                                isActive
                                                    ? 'border-brand-green bg-brand-green text-[color:var(--brand-green-foreground)]'
                                                    : 'border-white/20 bg-white/[0.06] text-[color:var(--brand-navy-foreground)] hover:border-white/40 hover:bg-white/10',
                                            )}
                                        >
                                            {c.label}
                                            <span className={cn('numeral text-xs', isActive ? 'text-[color:var(--brand-green-foreground)]/80' : 'text-white/60')}>{c.count}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </Band>

                    {/* Bandă DESCHISĂ — grila foto (editorial pe fundal light, imagini își găsesc
                        greutatea naturală). */}
                    <Band variant="light" pattern="mesh" className="!py-[clamp(2.5rem,5vw,4rem)]">
                        <Reveal className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            {view.map((e, i) => (
                                <button
                                    key={`${e.albumKey}-${i}`}
                                    type="button"
                                    onClick={() => setLightbox(i)}
                                    className="photo-frame group relative block overflow-hidden rounded-[10px] border keyline"
                                    aria-label={e.albumLabel}
                                >
                                    <img src={e.src} alt={e.alt} loading="lazy" className="aspect-[3/2] w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                                    <span className="absolute inset-0 flex items-center justify-center bg-brand-navy/0 transition-colors duration-300 group-hover:bg-brand-navy/30">
                                        <Maximize2 className="size-6 text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                                    </span>
                                </button>
                            ))}
                        </Reveal>
                    </Band>
                </>
            )}

            {lightbox !== null && view[lightbox] && (
                <Lightbox
                    entries={view}
                    index={lightbox}
                    t={t}
                    onClose={() => setLightbox(null)}
                    onNav={(delta) => setLightbox((i) => (i === null ? null : (i + delta + view.length) % view.length))}
                />
            )}
        </>
    );
}
