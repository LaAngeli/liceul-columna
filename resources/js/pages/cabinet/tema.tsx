import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Download, ExternalLink, Eye, EyeOff, FileText, Image as ImageIcon, Link2, Paperclip, UserRound } from 'lucide-react';
import { useState } from 'react';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * Pagina de DETALIU a unei teme (cerința 2026-08-07): din fișa disciplinei, cardul temei se
 * deschide aici — pagină proprie, nu popup — cu tot conținutul și resursele atașate, interactive.
 *
 * SECURITATE (frontend = ultima verigă; deciziile grele sunt pe server):
 *   • Linkurile primesc `href` DOAR dacă încep cu http/https (`isUrl`) — orice altceva rămâne chip
 *     inert. Serverul a validat oricum ({@see \App\Support\WebLink}), dar garda dublă nu costă.
 *   • `target="_blank"` merge mereu cu `rel="noopener noreferrer"` — pagina deschisă nu primește
 *     nici handle spre fereastra noastră, nici referrer-ul.
 *   • Previzualizarea fișierelor se face DOAR când serverul a decis-o (`preview` din payload,
 *     calculat din CONȚINUTUL fișierului): imaginile în <img>, PDF-urile în <iframe sandbox
 *     same-origin> către ruta autentificată. Niciun `dangerouslySetInnerHTML`, nicăieri.
 *   • Tot textul temei (topic/cerințe) se randează ca TEXT — React îl escapă implicit.
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

/** Pastila de stare a temei — aceeași semantică precum lista (azi / de făcut / trecută). */
function StatusPill({ status }: { status: HomeworkDetail['status'] }) {
    const t = useTranslations();

    if (status === 'past') {
        return null;
    }

    return (
        <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
            {t(status === 'today' ? 'cabinet.hw_status_today' : 'cabinet.hw_status_upcoming')}
        </span>
    );
}

/** Un bloc de conținut al temei (Obligatoriu / Suplimentar) — text simplu, cu rândurile păstrate. */
function TaskBlock({ label, text }: { label: string; text: string | null }) {
    if (text === null || text.trim() === '') {
        return null;
    }

    return (
        <section className="rounded-xl border bg-card p-4">
            <h2 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</h2>
            <p className="mt-1.5 text-sm leading-relaxed whitespace-pre-line">{text}</p>
        </section>
    );
}

/** Un fișier atașat: descărcare mereu; previzualizare doar când serverul a aprobat-o. */
function FileRow({ file }: { file: HomeworkFile }) {
    const t = useTranslations();
    const [open, setOpen] = useState(false);

    const Icon = file.preview === 'image' ? ImageIcon : file.preview === 'pdf' ? FileText : Paperclip;

    return (
        <li className="flex flex-col">
            <div className="flex flex-wrap items-center gap-2 px-3 py-2.5">
                <Icon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <span className="min-w-0 flex-1 truncate text-sm font-medium">{file.name}</span>

                {file.preview !== null && (
                    <button
                        type="button"
                        onClick={() => setOpen((value) => !value)}
                        aria-expanded={open}
                        className={cn(
                            'inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors',
                            'hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            open && 'border-primary bg-primary/10 text-primary',
                        )}
                    >
                        {open ? <EyeOff className="size-3.5" aria-hidden /> : <Eye className="size-3.5" aria-hidden />}
                        {t(open ? 'cabinet.hw_preview_hide' : 'cabinet.hw_preview_show')}
                    </button>
                )}

                <a
                    href={file.url}
                    className={cn(
                        'inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors',
                        'hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    )}
                >
                    <Download className="size-3.5" aria-hidden />
                    {t('cabinet.hw_download')}
                </a>
            </div>

            {/* Previzualizarea: conținut servit de ruta autentificată, pe aceeași origine.
                Imaginile într-un <img> (nu execută nimic); PDF-ul într-un <iframe> același-origin. */}
            {open && file.preview === 'image' && (
                <div className="border-t bg-muted/30 p-3">
                    <img src={file.previewUrl} alt={file.name} loading="lazy" className="mx-auto max-h-[70vh] max-w-full rounded-lg" />
                </div>
            )}
            {open && file.preview === 'pdf' && (
                <div className="border-t bg-muted/30 p-3">
                    <iframe src={file.previewUrl} title={file.name} className="h-[70vh] w-full rounded-lg border bg-white" />
                </div>
            )}
        </li>
    );
}

export default function HomeworkDetailPage({ homework }: Props) {
    const t = useTranslations();

    const links = homework.links.filter(Boolean);
    const resources = homework.resources.filter(Boolean);

    return (
        <>
            <Head title={`${homework.subject} · ${homework.date}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <Link
                    href="/cabinet/teme"
                    className="inline-flex min-h-11 w-fit items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    {t('cabinet.hw_back')}
                </Link>

                {/* Antetul temei: disciplina, profesorul, ziua — identitatea completă. */}
                <header className="rounded-xl border bg-card p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{homework.subject}</span>
                        <StatusPill status={homework.status} />
                    </div>
                    {homework.topic !== null && <h1 className="mt-2 text-lg leading-snug font-semibold text-balance">{homework.topic}</h1>}
                    <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span className="first-letter:uppercase">
                            {homework.dayLabel} · {homework.date}
                        </span>
                        {homework.teacher !== null && (
                            <span className="inline-flex items-center gap-1">
                                <UserRound className="size-3" aria-hidden />
                                {homework.teacher}
                            </span>
                        )}
                    </p>
                </header>

                <TaskBlock label={t('cabinet.required', 'Obligatoriu:')} text={homework.required} />
                <TaskBlock label={t('cabinet.optional', 'Suplimentar:')} text={homework.optional} />

                {/* Linkuri — se deschid în tab nou; ce nu e http/https rămâne text inert. */}
                {links.length > 0 && (
                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <Link2 className="size-3.5" aria-hidden />
                            {t('cabinet.hw_links')}
                        </h2>
                        <ul className="mt-2 flex flex-col gap-1.5">
                            {links.map((link, index) =>
                                isUrl(link) ? (
                                    <li key={index}>
                                        <a
                                            href={link}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex min-h-11 max-w-full items-center gap-2 rounded-lg border px-3 text-sm text-primary transition-colors hover:bg-muted"
                                        >
                                            <ExternalLink className="size-4 shrink-0" aria-hidden />
                                            <span className="truncate underline-offset-2 hover:underline">{link}</span>
                                        </a>
                                    </li>
                                ) : (
                                    <li key={index} className="rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground">
                                        {link}
                                    </li>
                                ),
                            )}
                        </ul>
                    </section>
                )}

                {/* Resurse tipărite/fizice — repere de citit, nu de deschis. */}
                {resources.length > 0 && (
                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <BookOpen className="size-3.5" aria-hidden />
                            {t('cabinet.hw_printed')}
                        </h2>
                        <ul className="mt-2 flex flex-wrap gap-2">
                            {resources.map((resource, index) => (
                                <li key={index} className="rounded-md bg-muted px-2.5 py-1 text-sm text-muted-foreground">
                                    {resource}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {/* Fișiere atașate de profesor — descărcare + previzualizare sigură. */}
                {homework.files.length > 0 && (
                    <section className="rounded-xl border bg-card p-4">
                        <h2 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <Paperclip className="size-3.5" aria-hidden />
                            {t('cabinet.hw_files')}
                        </h2>
                        <ul className="mt-2 divide-y overflow-hidden rounded-lg border">
                            {homework.files.map((file) => (
                                <FileRow key={file.url} file={file} />
                            ))}
                        </ul>
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
