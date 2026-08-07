import { Link } from '@inertiajs/react';
import { ChevronRight, Paperclip } from 'lucide-react';
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

                            {/* Cea mai recentă prima — CARDURILE COMPLETE ale temelor (corecția
                                2026-08-07: popupul rămâne cum era), fiecare clicabil ÎNTREG →
                                pagina de detaliu; resursele de pe card rămân active deasupra. */}
                            <div className="flex flex-col gap-3">
                                {detail.items.map((item) => (
                                    <HomeworkCard key={item.id} h={item} muted={item.status === 'past'} href={`/cabinet/teme/${item.id}`} />
                                ))}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

export function HomeworkCard({ h, muted = false, href }: { h: HomeworkItem; muted?: boolean; href?: string }) {
    const t = useTranslations();

    return (
        <article
            className={cn(
                'rounded-xl border bg-card p-4 shadow-sm',
                muted && 'opacity-80',
                // Cu țintă de navigare, cardul ÎNTREG e clicabil (link „întins" mai jos) și o
                // spune: chenar + umbră la hover, săgeată în colț. Resursele de pe card rămân
                // deasupra linkului (z-10), deci se deschid în continuare direct.
                href !== undefined && 'group relative transition-[border-color,box-shadow] hover:border-primary hover:shadow-md',
            )}
        >
            <div className="mb-1 flex flex-wrap items-center gap-2">
                <span className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{h.subject}</span>
                {/* Cine a dat tema — context, nu decor: la teme neclare, familia știe pe cine întreabă. */}
                {h.teacher && <span className="text-xs text-muted-foreground">{h.teacher}</span>}
                <span className="text-xs text-muted-foreground">
                    {t('cabinet.homework_assigned_on')} {h.date}
                </span>
                {href !== undefined && (
                    <ChevronRight
                        className="ml-auto size-4 shrink-0 text-muted-foreground/60 transition-colors group-hover:text-primary"
                        aria-hidden
                    />
                )}
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
                <div className="relative z-10 mt-2 flex flex-wrap gap-2">
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

            {/* Linkul „întins": acoperă tot cardul (ancorele nu se pot imbrica, deci resursele de
                mai sus stau DEASUPRA lui, pe z-10) — clic oriunde altundeva deschide pagina temei. */}
            {href !== undefined && (
                <Link
                    href={href}
                    aria-label={`${t('cabinet.hw_open_item')}: ${h.subject}, ${h.date}`}
                    className="absolute inset-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
            )}
        </article>
    );
}
