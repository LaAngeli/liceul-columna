import { Link } from '@inertiajs/react';
import { BookOpen, ChevronRight, Link2, Paperclip } from 'lucide-react';
import { useMemo, useState } from 'react';
import { isUrl } from '@/components/cabinet/student-profile/helpers';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { pluralKey, useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Vederile temelor. `HomeworkCard` e PARTAJATĂ — o folosesc și modulul „Teme" (înăuntrul unei
 * discipline), și planificatorul „Ziua mea" din Orar, deci o temă arată la fel oriunde apare.
 */

export interface HomeworkItem {
    id: number;
    /** Data lecției (d.m.Y) — axa unică după eliminarea „termenului" (2026-07-31). */
    date: string;
    /** Cheia zilei (Y-m-d) — gruparea cronologică. */
    effectiveDate: string;
    /** Eticheta zilei, tradusă pe server („Vineri, 18 iulie"). */
    dayLabel: string;
    status: 'today' | 'upcoming' | 'past';
    subject: string;
    /** Profesorul care a dat tema (snapshot-ul author_name). */
    teacher: string | null;
    topic: string | null;
    required: string | null;
    optional: string | null;
    links: string[];
    /** Resurse tipărite/fizice (manuale, pagini) — chip-uri gri lângă linkuri. */
    resources: string[];
    /** Fișiere atașate de profesor — nume original + rută autentificată de descărcare. */
    files?: { name: string; url: string }[];
}

/** Data locală ca Y-m-d (NU toISOString — UTC-ul ar aluneca o zi noaptea). */
export function localIso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/**
 * Temele, ORGANIZATE PE DISCIPLINE — aceeași logică de citire ca la Note (cerința beneficiarului,
 * 2026-08-04): întâi lista disciplinelor, apoi, la alegerea uneia, toate temele ei.
 *
 * Ce înlocuiește: patru moduri de afișare (pe zile / azi / săptămâna aceasta / calendar) plus un
 * filtru de dată. Erau multe unelte pentru o întrebare simplă — „ce s-a dat la matematică?" — la
 * care niciuna nu răspundea direct: trebuia găsită fiecare zi în parte. Aici disciplina e ușa, iar
 * înăuntru stă tot istoricul ei.
 *
 * Ordinea în interiorul disciplinei e CRONOLOGIC DESCRESCĂTOARE: cea mai recentă temă e mereu
 * prima, fără derulare.
 */
export function HomeworkBySubject({ homework }: { homework: HomeworkItem[] }) {
    const t = useTranslations();
    const [openSubject, setOpenSubject] = useState<string | null>(null);

    // Lista vine deja sortată descrescător de server → prima temă a fiecărei discipline e cea mai
    // recentă, iar grupurile se formează dintr-o singură parcurgere.
    const subjects = useMemo(() => {
        const map = new Map<string, { name: string; items: HomeworkItem[]; teachers: string[]; pending: number }>();

        for (const h of homework) {
            const entry = map.get(h.subject) ?? { name: h.subject, items: [], teachers: [], pending: 0 };

            entry.items.push(h);

            if (h.teacher !== null && !entry.teachers.includes(h.teacher)) {
                entry.teachers.push(h.teacher);
            }

            // „De făcut" = azi sau în viitor — singurul semnal acționabil de pe card.
            if (h.status !== 'past') {
                entry.pending++;
            }

            map.set(h.subject, entry);
        }

        return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
    }, [homework]);

    const detail = subjects.find((subject) => subject.name === openSubject) ?? null;

    return (
        <>
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {subjects.map((subject) => (
                    <li key={subject.name}>
                        <button
                            type="button"
                            onClick={() => setOpenSubject(subject.name)}
                            aria-label={`${t('cabinet.hw_open_subject')}: ${subject.name}`}
                            className={cn(
                                'flex h-full w-full cursor-pointer flex-col gap-2.5 rounded-xl border bg-card p-3.5 text-left shadow-sm transition-shadow',
                                'hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{subject.name}</p>
                                    {subject.teachers.length > 0 && (
                                        <p className="truncate text-xs text-muted-foreground">{subject.teachers.join(', ')}</p>
                                    )}
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className="text-2xl leading-none font-bold tabular-nums">{subject.items.length}</p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        {t(pluralKey('cabinet.hw_items', subject.items.length))}
                                    </p>
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {t('cabinet.hw_last')} {subject.items[0].date}
                            </p>

                            {subject.pending > 0 && (
                                <p className="text-xs font-medium text-primary">
                                    {subject.pending} {t('cabinet.hw_pending')}
                                </p>
                            )}

                            <p className="mt-auto flex items-center gap-0.5 pt-1 text-[11px] font-medium text-primary">
                                {t('cabinet.hw_open_subject')}
                                <ChevronRight className="size-3.5" aria-hidden />
                            </p>
                        </button>
                    </li>
                ))}
            </ul>

            <Dialog open={detail !== null} onOpenChange={(open) => !open && setOpenSubject(null)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    {detail !== null && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{detail.name}</DialogTitle>
                                <DialogDescription>
                                    {detail.items.length} {t(pluralKey('cabinet.hw_items', detail.items.length))}
                                </DialogDescription>
                            </DialogHeader>

                            {/* Cea mai recentă prima — lista păstrează ordinea serverului. Fiecare
                                rând NAVIGHEAZĂ la pagina de detaliu a temei (cerința 2026-08-07):
                                fișa e ușa, pagina e locul unde tema se citește și se folosește. */}
                            <ul className="divide-y overflow-hidden rounded-lg border">
                                {detail.items.map((item) => (
                                    <li key={item.id}>
                                        <Link
                                            href={`/cabinet/teme/${item.id}`}
                                            aria-label={`${t('cabinet.hw_open_item')}: ${item.subject}, ${item.date}`}
                                            className={cn(
                                                'flex min-h-11 w-full items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                                'hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none',
                                                item.status === 'past' && 'opacity-80',
                                            )}
                                        >
                                            <div className="w-16 shrink-0">
                                                <span className="text-sm font-semibold tabular-nums">{item.date.slice(0, 5)}</span>
                                                {item.status !== 'past' && (
                                                    <span className="mt-0.5 block text-[10px] font-medium text-primary">
                                                        {t(item.status === 'today' ? 'cabinet.hw_status_today' : 'cabinet.hw_status_upcoming')}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="min-w-0 flex-1 truncate text-sm">
                                                {item.topic ?? item.required ?? item.optional ?? '—'}
                                            </p>
                                            {/* Semnale de resurse — familia vede din listă ce temă poartă materiale. */}
                                            <span className="flex shrink-0 items-center gap-1.5 text-muted-foreground">
                                                {item.links.filter(Boolean).length > 0 && <Link2 className="size-3.5" aria-hidden />}
                                                {(item.files ?? []).length > 0 && <Paperclip className="size-3.5" aria-hidden />}
                                                {item.resources.filter(Boolean).length > 0 && <BookOpen className="size-3.5" aria-hidden />}
                                                <ChevronRight className="size-4" aria-hidden />
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

export function HomeworkCard({ h, muted = false }: { h: HomeworkItem; muted?: boolean }) {
    const t = useTranslations();

    return (
        <article className={`rounded-xl border bg-card p-4 shadow-sm ${muted ? 'opacity-80' : ''}`}>
            <div className="mb-1 flex flex-wrap items-center gap-2">
                <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                {/* Cine a dat tema — context, nu decor: la teme neclare, familia știe pe cine întreabă. */}
                {h.teacher && <span className="text-xs text-muted-foreground">{h.teacher}</span>}
                <span className="text-xs text-muted-foreground">
                    {t('cabinet.homework_assigned_on')} {h.date}
                </span>
            </div>
            {h.topic && <p className="font-medium">{h.topic}</p>}
            {h.required && (
                <p className="mt-1 text-sm">
                    <span className="text-muted-foreground">{t('cabinet.required')} </span>
                    {h.required}
                </p>
            )}
            {h.optional && (
                <p className="mt-1 text-sm">
                    <span className="text-muted-foreground">{t('cabinet.optional')} </span>
                    {h.optional}
                </p>
            )}
            {(h.links.filter(Boolean).length > 0 || h.resources.filter(Boolean).length > 0 || (h.files ?? []).length > 0) && (
                <div className="mt-2 flex flex-wrap gap-2">
                    {/* Linkuri deschizabile (URL) — restul intrărilor rămase în `links` (legacy)
                        se afișează tot ca chip gri, la fel ca resursele tipărite. */}
                    {h.links.filter(Boolean).map((link, i) =>
                        isUrl(link) ? (
                            <a
                                key={`l${i}`}
                                href={link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex min-h-11 items-center rounded-md bg-muted px-3 text-xs text-primary underline-offset-2 hover:underline md:min-h-0 md:px-2 md:py-0.5"
                            >
                                {t('cabinet.link')} {i + 1}
                            </a>
                        ) : (
                            <span key={`l${i}`} className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                {link}
                            </span>
                        ),
                    )}
                    {/* Resurse tipărite/fizice — chip gri, lângă linkuri (aceeași linie). */}
                    {h.resources.filter(Boolean).map((res, i) => (
                        <span key={`r${i}`} className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {res}
                        </span>
                    ))}
                    {/* Fișiere atașate de profesor — chip cu agrafă + numele ORIGINAL; descărcarea
                        trece prin ruta autentificată (disc privat, fără URL public). */}
                    {(h.files ?? []).map((f, i) => (
                        <a
                            key={`f${i}`}
                            href={f.url}
                            className="inline-flex min-h-11 items-center gap-1 rounded-md bg-muted px-3 text-xs text-primary underline-offset-2 hover:underline md:min-h-0 md:px-2 md:py-0.5"
                        >
                            <Paperclip aria-hidden className="h-3 w-3 shrink-0" />
                            {f.name}
                        </a>
                    ))}
                </div>
            )}
        </article>
    );
}
