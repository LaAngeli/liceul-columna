import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    CalendarDays,
    ClipboardList,
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Image as ImageIcon,
    Link2,
    Paperclip,
    Sparkles,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { SectionHeading } from '@/components/cabinet/section-heading';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * Pagina de DETALIU a unei teme (cerința 2026-08-07): din fișa disciplinei, cardul temei se
 * deschide aici — pagină proprie, nu popup — cu tot conținutul și resursele atașate, interactive.
 *
 * Ierarhia (restructurată 2026-08-07, „premium, nu simplist"): HERO cu identitatea temei
 * (disciplină, stare, titlu, zi, profesor) → CERINȚA obligatorie ca piesă centrală, cu accent →
 * suplimentarul, vizibil secundar → RESURSELE ca rânduri bogate (domeniu la linkuri, tip la
 * fișiere), nu chip-uri anonime.
 *
 * SECURITATE (frontend = ultima verigă; deciziile grele sunt pe server):
 *   • Linkurile primesc `href` DOAR dacă încep cu http/https (`isUrl`) — orice altceva rămâne
 *     rând inert. Serverul a validat oricum ({@see \App\Support\WebLink}).
 *   • `target="_blank"` merge mereu cu `rel="noopener noreferrer"`.
 *   • Previzualizarea fișierelor se face DOAR când serverul a decis-o (`preview` din payload,
 *     calculat din CONȚINUTUL fișierului): imaginile în <img>, PDF-urile în <iframe> same-origin,
 *     prin ruta autentificată. Niciun `dangerouslySetInnerHTML`, nicăieri.
 *   • Tot textul temei se randează ca TEXT — React îl escapă implicit.
 */

interface HomeworkFile {
    name: string;
    url: string;
    /** Decizia SERVERULUI, din conținutul fișierului: null = doar descărcare. */
    preview: 'image' | 'pdf' | null;
    previewUrl: string;
}

interface HomeworkDetail {
    id: number;
    date: string;
    dayLabel: string;
    status: 'today' | 'upcoming' | 'past';
    subject: string;
    teacher: string | null;
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
    resources: string[];
    files: HomeworkFile[];
}

interface Props {
    homework: HomeworkDetail;
}

/** Domeniul unui link http/https — eticheta lizibilă a rândului; URL-ul complet stă dedesubt. */
function hostOf(link: string): string {
    try {
        return new URL(link).hostname.replace(/^www\./, '');
    } catch {
        return link;
    }
}

/** Pastila de stare — doar când tema mai cere ceva; trecutul n-are nevoie de etichetă. */
function StatusPill({ status }: { status: HomeworkDetail['status'] }) {
    const t = useTranslations();

    if (status === 'past') {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-primary-foreground">
            <span className="size-1.5 rounded-full bg-primary-foreground/80" aria-hidden />
            {t(status === 'today' ? 'cabinet.hw_status_today' : 'cabinet.hw_status_upcoming')}
        </span>
    );
}

/** Pătrățelul-iconiță al unui rând de resursă — același gabarit peste tot, tonul diferă. */
function ResourceGlyph({ icon: Icon, tone = 'primary' }: { icon: typeof Link2; tone?: 'primary' | 'muted' }) {
    return (
        <span
            className={cn(
                'grid size-10 shrink-0 place-items-center rounded-lg',
                tone === 'primary' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
            )}
        >
            <Icon className="size-4.5" aria-hidden />
        </span>
    );
}

/** Un fișier atașat: rând bogat (tip + nume) cu descărcare mereu și previzualizare aprobată. */
function FileRow({ file }: { file: HomeworkFile }) {
    const t = useTranslations();
    const [open, setOpen] = useState(false);

    const glyph = file.preview === 'image' ? ImageIcon : file.preview === 'pdf' ? FileText : Paperclip;
    const kind =
        file.preview === 'image'
            ? t('cabinet.hw_kind_image', 'Imagine')
            : file.preview === 'pdf'
              ? t('cabinet.hw_kind_pdf', 'Document PDF')
              : t('cabinet.hw_kind_file', 'Fișier');

    return (
        <li className="flex flex-col rounded-xl border bg-card transition-[border-color,box-shadow] hover:border-primary/40 hover:shadow-sm">
            <div className="flex flex-wrap items-center gap-3 p-3">
                <ResourceGlyph icon={glyph} tone={file.preview !== null ? 'primary' : 'muted'} />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{file.name}</p>
                    <p className="text-xs text-muted-foreground">{kind}</p>
                </div>

                <div className="flex shrink-0 items-center gap-1.5">
                    {file.preview !== null && (
                        <button
                            type="button"
                            onClick={() => setOpen((value) => !value)}
                            aria-expanded={open}
                            className={cn(
                                'inline-flex min-h-10 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors',
                                'hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                open && 'border-primary bg-primary/10 text-primary',
                            )}
                        >
                            {open ? <EyeOff className="size-3.5" aria-hidden /> : <Eye className="size-3.5" aria-hidden />}
                            <span className="max-sm:sr-only">{t(open ? 'cabinet.hw_preview_hide' : 'cabinet.hw_preview_show')}</span>
                        </button>
                    )}

                    <a
                        href={file.url}
                        className={cn(
                            'inline-flex min-h-10 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors',
                            'hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        )}
                    >
                        <Download className="size-3.5" aria-hidden />
                        <span className="max-sm:sr-only">{t('cabinet.hw_download')}</span>
                    </a>
                </div>
            </div>

            {/* Previzualizarea: conținut servit de ruta autentificată, pe aceeași origine.
                Imaginile într-un <img> (nu execută nimic); PDF-ul într-un <iframe> același-origin. */}
            {open && file.preview === 'image' && (
                <div className="border-t bg-muted/30 p-3">
                    <img src={file.previewUrl} alt={file.name} loading="lazy" className="mx-auto max-h-[70vh] max-w-full rounded-lg shadow-sm" />
                </div>
            )}
            {open && file.preview === 'pdf' && (
                <div className="border-t bg-muted/30 p-3">
                    <iframe src={file.previewUrl} title={file.name} className="h-[65vh] w-full rounded-lg border bg-white" />
                </div>
            )}
        </li>
    );
}

export default function HomeworkDetailPage({ homework }: Props) {
    const t = useTranslations();

    const links = homework.links.filter(Boolean);
    const resources = homework.resources.filter(Boolean);
    const hasResources = links.length > 0 || resources.length > 0 || homework.files.length > 0;

    return (
        <>
            <Head title={`${homework.subject} · ${homework.date}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
                <Link
                    href="/cabinet/teme"
                    className={cn(
                        'inline-flex min-h-11 w-fit items-center gap-1.5 rounded-lg border bg-card px-3 text-sm font-medium',
                        'transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    )}
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    {t('cabinet.hw_back')}
                </Link>

                {/* HERO — identitatea temei, cu greutate: pe asta ai dat clic, asta primești. */}
                <header className="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/[0.08] via-card to-card p-5 sm:p-6">
                    {/* Filigranul discret al modulului — decor, nu informație. */}
                    <BookOpen className="pointer-events-none absolute -right-6 -bottom-8 size-36 -rotate-12 text-primary/[0.06]" aria-hidden />

                    <div className="relative">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="rounded-md bg-primary/10 px-2.5 py-1 text-sm font-semibold text-primary">{homework.subject}</span>
                            <StatusPill status={homework.status} />
                        </div>

                        <h1 className="mt-3 text-xl leading-snug font-semibold text-balance sm:text-2xl">
                            {homework.topic ?? t('cabinet.hw_detail')}
                        </h1>

                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full border bg-background/70 px-3 py-1 text-xs text-muted-foreground">
                                <CalendarDays className="size-3.5" aria-hidden />
                                <span className="first-letter:uppercase">
                                    {homework.dayLabel} · {homework.date}
                                </span>
                            </span>
                            {homework.teacher !== null && (
                                <span className="inline-flex items-center gap-1.5 rounded-full border bg-background/70 px-3 py-1 text-xs text-muted-foreground">
                                    <UserRound className="size-3.5" aria-hidden />
                                    {homework.teacher}
                                </span>
                            )}
                        </div>
                    </div>
                </header>

                {/* CERINȚA — piesa centrală a paginii, cu accentul de brand pe muchie. */}
                {homework.required !== null && homework.required.trim() !== '' && (
                    <section className="rounded-xl border border-l-4 border-l-primary bg-card p-4 sm:p-5">
                        <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-primary uppercase">
                            <ClipboardList className="size-4" aria-hidden />
                            {t('cabinet.required', 'Obligatoriu:')}
                        </h2>
                        <p className="mt-2 text-[15px] leading-relaxed whitespace-pre-line">{homework.required}</p>
                    </section>
                )}

                {/* Suplimentarul — vizibil, dar în mod clar opțional (chenar punctat, fundal stins). */}
                {homework.optional !== null && homework.optional.trim() !== '' && (
                    <section className="rounded-xl border border-dashed bg-muted/30 p-4 sm:p-5">
                        <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            <Sparkles className="size-4" aria-hidden />
                            {t('cabinet.optional', 'Suplimentar:')}
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed whitespace-pre-line">{homework.optional}</p>
                    </section>
                )}

                {/* RESURSELE — un singur capitol, cu rânduri bogate pe feluri. */}
                {hasResources && (
                    <section>
                        <SectionHeading title={t('cabinet.hw_resources', 'Resurse')} />
                        <div className="flex flex-col gap-4">
                            {links.length > 0 && (
                                <ul className="flex flex-col gap-2">
                                    {links.map((link, index) =>
                                        isUrl(link) ? (
                                            <li key={index}>
                                                <a
                                                    href={link}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className={cn(
                                                        'group flex items-center gap-3 rounded-xl border bg-card p-3',
                                                        'transition-[border-color,box-shadow] hover:border-primary/40 hover:shadow-sm',
                                                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                    )}
                                                >
                                                    <ResourceGlyph icon={Link2} />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate text-sm font-medium">{hostOf(link)}</span>
                                                        <span className="block truncate text-xs text-muted-foreground">{link}</span>
                                                    </span>
                                                    <ExternalLink
                                                        className="size-4 shrink-0 text-muted-foreground/60 transition-colors group-hover:text-primary"
                                                        aria-hidden
                                                    />
                                                </a>
                                            </li>
                                        ) : (
                                            // Intrare fără schemă http/https (legacy sau text liber):
                                            // rând INERT, deliberat — niciodată `href`.
                                            <li key={index} className="flex items-center gap-3 rounded-xl border bg-card p-3">
                                                <ResourceGlyph icon={Link2} tone="muted" />
                                                <span className="min-w-0 flex-1 truncate text-sm text-muted-foreground">{link}</span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}

                            {resources.length > 0 && (
                                <ul className="flex flex-col gap-2">
                                    {resources.map((resource, index) => (
                                        <li key={index} className="flex items-center gap-3 rounded-xl border bg-card p-3">
                                            <ResourceGlyph icon={BookOpen} tone="muted" />
                                            <span className="min-w-0 flex-1 text-sm">{resource}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {homework.files.length > 0 && (
                                <ul className="flex flex-col gap-2">
                                    {homework.files.map((file) => (
                                        <FileRow key={file.url} file={file} />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

HomeworkDetailPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_homework', href: '/cabinet/teme' },
        { title: 'cabinet.hw_detail', href: '#' },
    ],
};
