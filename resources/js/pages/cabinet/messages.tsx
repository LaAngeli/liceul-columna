import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    Building2,
    ChevronLeft,
    Download,
    FileText,
    Inbox,
    MessageSquarePlus,
    Paperclip,
    RotateCcw,
    Search,
    Send,
    Star,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { messages as messagesRoute } from '@/routes/cabinet';
import * as messageRoutes from '@/routes/cabinet/messages';

const SUBJECT_MAX = 120;
const BODY_MAX = 2000;

type Folder = 'all' | 'unread' | 'starred' | 'trash';
type RecipientRole = 'profesor' | 'diriginte';

interface Recipient {
    id: number;
    name: string;
    role: RecipientRole;
}

interface StudentOption {
    id: number;
    name: string;
    classLabel: string | null;
    recipients: Recipient[];
}

interface Attachment {
    id: number;
    name: string;
    size: string;
    isImage: boolean;
    url: string;
}

interface ThreadMessage {
    id: number;
    body: string;
    mine: boolean;
    senderName: string;
    at: string | null;
    attachments: Attachment[];
}

interface Thread {
    id: number;
    subject: string;
    type: 'direct' | 'audience';
    student: string | null;
    studentId: number | null;
    withId: number;
    with: string;
    direction: 'sent' | 'received';
    starred: boolean;
    trashed: boolean;
    snippet: string;
    lastAt: string | null;
    unread: number;
    attachmentCount: number;
    messages: ThreadMessage[];
}

interface FolderCount {
    total: number;
    unread: number;
}

interface Props {
    folder: Folder;
    threads: Thread[];
    counts: Record<Folder, FolderCount>;
    compose: {
        students: StudentOption[];
        canAudience: boolean;
        audienceDomains: string[];
        attachments: { maxFiles: number; maxFileMb: number; extensions: string[] };
    };
}

const FILTERS: { key: Folder; labelKey: string; emptyKey: string }[] = [
    { key: 'all', labelKey: 'mailbox_filter_all', emptyKey: 'mailbox_empty_all' },
    { key: 'unread', labelKey: 'mailbox_filter_unread', emptyKey: 'mailbox_empty_unread' },
    { key: 'starred', labelKey: 'mailbox_starred', emptyKey: 'mailbox_empty_starred' },
    { key: 'trash', labelKey: 'mailbox_trash', emptyKey: 'mailbox_empty_trash' },
];

/** Contor de caractere gradat (neutru → amber → destructive) cu aria-live. */
function CharCount({ id, current, max }: { id: string; current: number; max: number }) {
    const ratio = current / max;
    const cls = current >= max ? 'text-destructive' : ratio >= 0.8 ? 'text-amber-700 dark:text-amber-300' : 'text-muted-foreground';

    return (
        <p id={id} aria-live="polite" className={cn('mt-1 text-right text-[11px] tabular-nums', cls)}>
            {current}/{max}
        </p>
    );
}

/** Avatar cu inițiale + inel colorat pe rol (navy=diriginte, verde=profesor). */
function Avatar({ name, variant, size = 'md' }: { name: string; variant: 'diriginte' | 'profesor' | 'audience' | 'plain'; size?: 'md' | 'lg' }) {
    const getInitials = useInitials();
    const dim = size === 'lg' ? 'size-11 text-base' : 'size-10 text-sm';

    if (variant === 'audience') {
        return (
            <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground', dim)}>
                <Building2 className="size-5" aria-hidden="true" />
            </span>
        );
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary',
                dim,
                variant === 'diriginte' && 'ring-2 ring-primary',
                variant === 'profesor' && 'ring-2 ring-brand-green',
            )}
        >
            {getInitials(name)}
        </span>
    );
}

/** Eticheta de rol, contrast AA (navy plin pt. diriginte, verde plin pt. profesor). */
function RoleBadge({ role }: { role: RecipientRole }) {
    const t = useTranslations();

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                role === 'diriginte'
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-brand-green text-brand-green-foreground',
            )}
        >
            {t(`cabinet.role_${role}`)}
        </span>
    );
}

export default function MessagesPage({ folder, threads, counts, compose }: Props) {
    const t = useTranslations();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [childFilter, setChildFilter] = useState<number | 'all'>('all');
    const [lightbox, setLightbox] = useState<string | null>(null);

    // Compose modal (picker de oameni): pre-completabil dintr-un card-contact.
    const [compose_, setCompose] = useState<{ open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean }>({
        open: false,
        studentId: compose.students[0]?.id ?? '',
        recipientId: '',
        audience: false,
    });

    const multiChild = compose.students.length > 1;
    const rosterMode = folder === 'all' || folder === 'unread';
    const selected = threads.find((th) => th.id === selectedId) ?? null;

    // Roster: pentru fiecare copil, dirigintele întâi, apoi profesorii; firul se leagă prin (studentId, withId).
    const roster = useMemo(() => {
        const q = search.trim().toLowerCase();

        return compose.students
            .filter((s) => childFilter === 'all' || s.id === childFilter)
            .map((student) => {
                const people = [...student.recipients].sort((a, b) => (a.role === 'diriginte' ? -1 : b.role === 'diriginte' ? 1 : 0));
                const entries = people
                    .map((person) => ({
                        person,
                        thread: threads.find((th) => th.type === 'direct' && th.studentId === student.id && th.withId === person.id) ?? null,
                    }))
                    // În „Necitite" arătăm doar contactele cu fir necitit; în „Toate" arătăm și contactele fără istoric.
                    .filter((e) => (folder === 'unread' ? e.thread !== null && e.thread.unread > 0 : true))
                    .filter((e) => {
                        if (q === '') {
                            return true;
                        }

                        return (
                            e.person.name.toLowerCase().includes(q) ||
                            student.name.toLowerCase().includes(q) ||
                            (e.thread?.subject ?? '').toLowerCase().includes(q)
                        );
                    });

                return { student, entries };
            })
            .filter((g) => g.entries.length > 0);
    }, [compose.students, threads, childFilter, folder, search]);

    const audienceThreads = useMemo(
        () =>
            threads.filter(
                (th) =>
                    th.type === 'audience' &&
                    (search.trim() === '' || th.subject.toLowerCase().includes(search.trim().toLowerCase()) || th.with.toLowerCase().includes(search.trim().toLowerCase())),
            ),
        [threads, search],
    );

    // Lista plată (Preferate / Arhivă), cu căutare.
    const flatList = useMemo(() => {
        const q = search.trim().toLowerCase();

        return threads.filter((th) => q === '' || th.subject.toLowerCase().includes(q) || th.with.toLowerCase().includes(q) || (th.student ?? '').toLowerCase().includes(q));
    }, [threads, search]);

    function openThread(thread: Thread) {
        setSelectedId(thread.id);

        if (thread.unread > 0) {
            router.post(messageRoutes.read(thread.id).url, {}, { preserveScroll: true, preserveState: true });
        }
    }

    function act(url: string) {
        router.post(url, {}, { preserveScroll: true, preserveState: true, only: ['threads', 'counts'] });
    }

    function openCompose(studentId: number | '', recipientId: number | '', audience: boolean) {
        setCompose({ open: true, studentId: studentId === '' ? compose.students[0]?.id ?? '' : studentId, recipientId, audience });
    }

    const activeFilterMeta = FILTERS.find((f) => f.key === folder) ?? FILTERS[0];
    const isEmpty = rosterMode ? roster.length === 0 && audienceThreads.length === 0 : flatList.length === 0;

    return (
        <>
            <Head title={t('cabinet.messages_title')} />
            <div className="mx-auto flex h-[calc(100dvh-8rem)] w-full max-w-6xl flex-col gap-3 p-4">
                {/* Master–detail: rail (stânga) + panou conversație (dreapta). Pe mobil un singur panou. */}
                <div className="flex min-h-0 flex-1 gap-4">
                    {/* ============ RAIL ============ */}
                    <aside className={cn('flex min-h-0 w-full flex-col lg:w-[340px] lg:shrink-0', selected && 'hidden lg:flex')}>
                        <div className="mb-3 flex items-center justify-between">
                            <h1 className="text-lg font-semibold">{t('cabinet.mailbox_conversations')}</h1>
                            <button
                                type="button"
                                onClick={() => openCompose('', '', false)}
                                disabled={compose.students.length === 0}
                                className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
                            >
                                <MessageSquarePlus className="size-4" aria-hidden="true" />
                                {t('cabinet.messages_new')}
                            </button>
                        </div>

                        {/* Căutare */}
                        <div className="relative mb-2">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('cabinet.mailbox_search')}
                                aria-label={t('cabinet.mailbox_search')}
                                className="w-full rounded-lg border border-input bg-background py-2 pr-3 pl-8 text-sm"
                            />
                        </div>

                        {/* Filtru pe copil (părinte cu mai mulți copii) */}
                        {multiChild && (
                            <div className="mb-2 flex gap-1.5 overflow-x-auto pb-1">
                                <ChildChip active={childFilter === 'all'} onClick={() => setChildFilter('all')} label={t('cabinet.mailbox_all_children')} />
                                {compose.students.map((s) => (
                                    <ChildChip key={s.id} active={childFilter === s.id} onClick={() => setChildFilter(s.id)} label={s.name.split(' ')[0]} />
                                ))}
                            </div>
                        )}

                        {/* Pastile-filtru (folderele retrogradate) */}
                        <nav className="mb-3 flex gap-1.5 overflow-x-auto pb-1" aria-label={t('cabinet.mailbox_conversations')}>
                            {FILTERS.map((f) => {
                                const active = f.key === folder;
                                const badge = f.key === 'unread' ? counts.unread?.total ?? 0 : f.key === 'starred' ? counts.starred?.total ?? 0 : 0;

                                return (
                                    <Link
                                        key={f.key}
                                        href={messagesRoute({ query: { folder: f.key } }).url}
                                        only={['folder', 'threads', 'counts']}
                                        preserveScroll
                                        preserveState
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                            active ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted',
                                        )}
                                    >
                                        {t(`cabinet.${f.labelKey}`)}
                                        {badge > 0 && <span className="rounded-full bg-primary px-1.5 text-[10px] font-bold tabular-nums text-primary-foreground">{badge}</span>}
                                    </Link>
                                );
                            })}
                        </nav>

                        {/* Conținutul rail-ului */}
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                            {isEmpty && (
                                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground">
                                    <Inbox className="size-6 text-muted-foreground/60" aria-hidden="true" />
                                    {t(`cabinet.${activeFilterMeta.emptyKey}`)}
                                </div>
                            )}

                            {rosterMode ? (
                                <>
                                    {roster.map((group) => (
                                        <section key={group.student.id}>
                                            <p className="mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                {group.student.name}
                                                {group.student.classLabel ? ` · ${group.student.classLabel}` : ''}
                                            </p>
                                            <div className="space-y-1.5">
                                                {group.entries.map(({ person, thread }) => (
                                                    <ContactCard
                                                        key={person.id}
                                                        person={person}
                                                        thread={thread}
                                                        selected={selected?.id === thread?.id}
                                                        onOpen={() => (thread ? openThread(thread) : openCompose(group.student.id, person.id, false))}
                                                        onStar={thread ? () => act(messageRoutes.star(thread.id).url) : undefined}
                                                    />
                                                ))}
                                            </div>
                                        </section>
                                    ))}

                                    {/* Canalul distinct Conducere / Audiențe */}
                                    {(audienceThreads.length > 0 || compose.canAudience) && folder === 'all' && (
                                        <section>
                                            <p className="mb-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                {t('cabinet.mailbox_leadership')}
                                            </p>
                                            <div className="space-y-1.5">
                                                {audienceThreads.map((th) => (
                                                    <AudienceCard key={th.id} thread={th} selected={selected?.id === th.id} onOpen={() => openThread(th)} onStar={() => act(messageRoutes.star(th.id).url)} />
                                                ))}
                                                {compose.canAudience && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openCompose('', '', true)}
                                                        className="flex w-full items-center gap-3 rounded-xl border border-dashed p-3 text-left text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                                                    >
                                                        <Avatar name="A" variant="audience" />
                                                        <span>{t('cabinet.mailbox_audience_channel')}</span>
                                                    </button>
                                                )}
                                            </div>
                                        </section>
                                    )}
                                </>
                            ) : (
                                <div className="space-y-1.5">
                                    {flatList.map((th) => (
                                        <ContactCard
                                            key={th.id}
                                            person={{ id: th.withId, name: th.with, role: 'profesor' }}
                                            thread={th}
                                            selected={selected?.id === th.id}
                                            onOpen={() => openThread(th)}
                                            onStar={() => act(messageRoutes.star(th.id).url)}
                                            showChild
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </aside>

                    {/* ============ DETAIL ============ */}
                    <section className={cn('min-h-0 flex-1 flex-col rounded-xl border bg-card', selected ? 'flex' : 'hidden lg:flex')}>
                        {selected ? (
                            <ThreadDetail
                                key={selected.id}
                                thread={selected}
                                folder={folder}
                                attachmentsCfg={compose.attachments}
                                onBack={() => setSelectedId(null)}
                                onStar={() => act(messageRoutes.star(selected.id).url)}
                                onArchive={() => {
                                    act(messageRoutes.trash(selected.id).url);
                                    setSelectedId(null);
                                }}
                                onRestore={() => {
                                    act(messageRoutes.restore(selected.id).url);
                                    setSelectedId(null);
                                }}
                                onImage={(url) => setLightbox(url)}
                            />
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center gap-2 p-8 text-center">
                                <MessageSquarePlus className="size-8 text-muted-foreground/50" aria-hidden="true" />
                                <p className="font-medium">{t('cabinet.mailbox_select_conversation')}</p>
                                <p className="max-w-xs text-sm text-muted-foreground">{t('cabinet.mailbox_select_hint')}</p>
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {/* Lightbox pentru imagini */}
            <Dialog open={lightbox !== null} onOpenChange={(open) => !open && setLightbox(null)}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader className="sr-only">
                        <DialogTitle>{t('cabinet.mailbox_download')}</DialogTitle>
                        <DialogDescription>{t('cabinet.mailbox_download')}</DialogDescription>
                    </DialogHeader>
                    {lightbox && <img src={lightbox} alt="" className="mx-auto max-h-[75vh] w-auto rounded-lg" />}
                </DialogContent>
            </Dialog>

            {/* Compune (picker de oameni) */}
            <ComposeDialog
                state={compose_}
                setState={setCompose}
                compose={compose}
                onSent={(threadId) => {
                    setCompose((prev) => ({ ...prev, open: false }));

                    if (threadId) {
setSelectedId(threadId);
}
                }}
            />
        </>
    );
}

function ChildChip({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                active ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted',
            )}
        >
            {label}
        </button>
    );
}

/** Card-contact în roster: persoana + firul (sau „Scrie primul mesaj"). */
function ContactCard({
    person,
    thread,
    selected,
    onOpen,
    onStar,
    showChild,
}: {
    person: Recipient;
    thread: Thread | null;
    selected: boolean;
    onOpen: () => void;
    onStar?: () => void;
    showChild?: boolean;
}) {
    const t = useTranslations();
    const unread = thread ? thread.unread > 0 : false;

    return (
        <div
            className={cn(
                'group relative flex items-center gap-3 rounded-xl border bg-card p-3 transition-colors',
                selected ? 'border-primary bg-primary/[0.06]' : 'hover:bg-muted/60',
                unread && !selected && 'border-l-[3px] border-l-primary',
            )}
        >
            <Avatar name={person.name} variant={thread === null ? 'plain' : person.role} />
            <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left focus-visible:outline-none">
                <div className="flex items-baseline justify-between gap-2">
                    <span className={cn('truncate', unread ? 'font-semibold' : 'font-medium')}>{person.name}</span>
                    {thread?.lastAt && <span className="shrink-0 text-[11px] tabular-nums text-muted-foreground">{thread.lastAt}</span>}
                </div>
                <div className="flex items-center gap-1.5">
                    <RoleBadge role={person.role} />
                    {showChild && thread?.student && <span className="truncate text-[11px] text-muted-foreground">· {thread.student}</span>}
                </div>
                <p className="mt-0.5 truncate text-xs text-muted-foreground">
                    {thread ? (
                        <>
                            {thread.direction === 'sent' && `${t('cabinet.mailbox_you')}: `}
                            {thread.snippet || thread.subject}
                        </>
                    ) : (
                        <span className="italic">{t('cabinet.mailbox_write_first')}</span>
                    )}
                </p>
            </button>
            <div className="flex shrink-0 flex-col items-end gap-1.5">
                {unread && thread && (
                    <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-bold tabular-nums text-primary-foreground">
                        {thread.unread}
                    </span>
                )}
                <div className="flex items-center gap-0.5">
                    {thread && thread.attachmentCount > 0 && <Paperclip className="size-3.5 text-muted-foreground" aria-hidden="true" />}
                    {onStar && (
                        <button
                            type="button"
                            onClick={onStar}
                            aria-label={t(thread?.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')}
                            title={t(thread?.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')}
                            className="rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <Star className={cn('size-4', thread?.starred && 'fill-amber-400 text-amber-500')} aria-hidden="true" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/** Card pentru un fir de audiență (canal Conducere). */
function AudienceCard({ thread, selected, onOpen, onStar }: { thread: Thread; selected: boolean; onOpen: () => void; onStar: () => void }) {
    const t = useTranslations();
    const unread = thread.unread > 0;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-xl border bg-card p-3 transition-colors',
                selected ? 'border-primary bg-primary/[0.06]' : 'hover:bg-muted/60',
                unread && !selected && 'border-l-[3px] border-l-primary',
            )}
        >
            <Avatar name={thread.with} variant="audience" />
            <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left">
                <div className="flex items-baseline justify-between gap-2">
                    <span className={cn('truncate', unread ? 'font-semibold' : 'font-medium')}>{thread.subject}</span>
                    {thread.lastAt && <span className="shrink-0 text-[11px] tabular-nums text-muted-foreground">{thread.lastAt}</span>}
                </div>
                <p className="truncate text-xs text-muted-foreground">
                    {t('cabinet.mailbox_audiences')}
                    {thread.student ? ` · ${thread.student}` : ''}
                </p>
            </button>
            <button
                type="button"
                onClick={onStar}
                aria-label={t(thread.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')}
                className="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
            >
                <Star className={cn('size-4', thread.starred && 'fill-amber-400 text-amber-500')} aria-hidden="true" />
            </button>
        </div>
    );
}

/** Blocul de atașamente dintr-un mesaj din fir. */
function AttachmentBlock({ items, onImage }: { items: Attachment[]; onImage: (url: string) => void }) {
    const t = useTranslations();

    if (items.length === 0) {
        return null;
    }

    return (
        <div className="mt-2 flex flex-wrap gap-2">
            {items.map((a) =>
                a.isImage ? (
                    <button key={a.id} type="button" onClick={() => onImage(a.url)} className="overflow-hidden rounded-lg ring-1 ring-border">
                        <img src={a.url} alt={a.name} className="h-24 w-auto max-w-[220px] object-cover" />
                    </button>
                ) : (
                    <a
                        key={a.id}
                        href={a.url}
                        download={a.name}
                        className="flex items-center gap-2 rounded-lg border bg-muted/40 p-2.5 text-sm transition-colors hover:border-primary"
                    >
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <FileText className="size-4" aria-hidden="true" />
                        </span>
                        <span className="min-w-0">
                            <span className="block max-w-[160px] truncate font-medium">{a.name}</span>
                            <span className="text-[11px] text-muted-foreground">{a.size}</span>
                        </span>
                        <Download className="size-4 shrink-0 text-muted-foreground" aria-label={t('cabinet.mailbox_download')} />
                    </a>
                ),
            )}
        </div>
    );
}

/** Selector de fișiere pentru compunere/răspuns: buton clips + chip-uri amovibile + metru de mărime. */
function FilePicker({
    files,
    setFiles,
    cfg,
}: {
    files: File[];
    setFiles: (f: File[]) => void;
    cfg: { maxFiles: number; maxFileMb: number; extensions: string[] };
}) {
    const t = useTranslations();
    const inputRef = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);

    function onPick(list: FileList | null) {
        if (!list) {
return;
}

        setError(null);
        const picked = Array.from(list);
        const next = [...files];

        for (const f of picked) {
            const ext = f.name.split('.').pop()?.toLowerCase() ?? '';

            if (!cfg.extensions.includes(ext)) {
                continue;
            }

            if (f.size > cfg.maxFileMb * 1024 * 1024) {
                setError(t('cabinet.mailbox_attach_too_big').replace(':mb', String(cfg.maxFileMb)));
                continue;
            }

            if (next.length >= cfg.maxFiles) {
                setError(t('cabinet.mailbox_attach_too_many').replace(':n', String(cfg.maxFiles)));
                break;
            }

            next.push(f);
        }

        setFiles(next);

        if (inputRef.current) {
inputRef.current.value = '';
}
    }

    return (
        <div>
            <input
                ref={inputRef}
                type="file"
                multiple
                accept={cfg.extensions.map((e) => `.${e}`).join(',')}
                onChange={(e) => onPick(e.target.files)}
                className="hidden"
            />
            <div className="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="inline-flex items-center gap-1.5 rounded-md border border-input px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                >
                    <Paperclip className="size-3.5" aria-hidden="true" />
                    {t('cabinet.mailbox_attach')}
                </button>
                {files.map((f, i) => (
                    <span key={`${f.name}-${i}`} className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs">
                        <FileText className="size-3.5 shrink-0 text-primary" aria-hidden="true" />
                        <span className="max-w-[120px] truncate">{f.name}</span>
                        <button
                            type="button"
                            onClick={() => setFiles(files.filter((_, j) => j !== i))}
                            aria-label={t('cabinet.mailbox_remove')}
                            className="text-muted-foreground hover:text-destructive"
                        >
                            <X className="size-3" aria-hidden="true" />
                        </button>
                    </span>
                ))}
            </div>
            {(files.length > 0 || error) && (
                <p className={cn('mt-1 text-[11px]', error ? 'text-destructive' : 'text-muted-foreground')} aria-live="polite">
                    {error ??
                        `${t('cabinet.mailbox_attach_types')} · max ${cfg.maxFileMb} MB · ${t('cabinet.mailbox_attach_upto')} ${cfg.maxFiles} ${t('cabinet.mailbox_attach_files_word')}`}
                </p>
            )}
        </div>
    );
}

/** Panoul de conversație (dreapta / full-screen mobil). */
function ThreadDetail({
    thread,
    folder,
    attachmentsCfg,
    onBack,
    onStar,
    onArchive,
    onRestore,
    onImage,
}: {
    thread: Thread;
    folder: Folder;
    attachmentsCfg: { maxFiles: number; maxFileMb: number; extensions: string[] };
    onBack: () => void;
    onStar: () => void;
    onArchive: () => void;
    onRestore: () => void;
    onImage: (url: string) => void;
}) {
    const t = useTranslations();
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [sending, setSending] = useState(false);

    function submitReply(e: React.FormEvent) {
        e.preventDefault();

        if (body.trim() === '' || sending) {
return;
}

        setSending(true);
        router.post(
            messageRoutes.reply(thread.id).url,
            { body, files },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setBody('');
                    setFiles([]);
                },
                onFinish: () => setSending(false),
            },
        );
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            {/* Antet */}
            <div className="flex items-center gap-3 border-b p-3">
                <button type="button" onClick={onBack} aria-label={t('cabinet.mailbox_back')} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted lg:hidden">
                    <ChevronLeft className="size-5" aria-hidden="true" />
                </button>
                <Avatar name={thread.with} variant={thread.type === 'audience' ? 'audience' : 'profesor'} />
                <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold">{thread.subject}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {thread.with}
                        {thread.student ? ` · ${t('cabinet.mailbox_about')} ${thread.student}` : ''}
                    </p>
                </div>
                <button type="button" onClick={onStar} aria-label={t(thread.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted">
                    <Star className={cn('size-4', thread.starred && 'fill-amber-400 text-amber-500')} aria-hidden="true" />
                </button>
                {folder === 'trash' ? (
                    <button type="button" onClick={onRestore} aria-label={t('cabinet.mailbox_restore')} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-primary">
                        <RotateCcw className="size-4" aria-hidden="true" />
                    </button>
                ) : (
                    <button type="button" onClick={onArchive} aria-label={t('cabinet.mailbox_trash_action')} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-destructive">
                        <Archive className="size-4" aria-hidden="true" />
                    </button>
                )}
            </div>

            {/* Banda distinctă pentru audiențe */}
            {thread.type === 'audience' && (
                <div className="flex items-center gap-2 bg-primary px-3 py-2 text-sm text-primary-foreground">
                    <Building2 className="size-4" aria-hidden="true" />
                    <span>
                        {t('cabinet.mailbox_audiences')} · {t('cabinet.mailbox_audience_routing')}
                    </span>
                </div>
            )}

            {/* Firul (stil scrisoare) */}
            <ol className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                {thread.messages.map((m) => (
                    <li key={m.id} className={cn('rounded-lg border p-3', m.mine ? 'border-primary/20 bg-primary/5' : 'bg-card')}>
                        <div className="mb-1 flex items-baseline justify-between gap-2 text-xs text-muted-foreground">
                            <span className="font-medium text-foreground/80">{m.mine ? t('cabinet.mailbox_you') : m.senderName}</span>
                            <span className="tabular-nums">{m.at}</span>
                        </div>
                        <p className="whitespace-pre-wrap text-sm">{m.body}</p>
                        <AttachmentBlock items={m.attachments} onImage={onImage} />
                    </li>
                ))}
            </ol>

            {/* Compozitor de răspuns (ascuns în arhivă) */}
            {folder !== 'trash' && (
                <form onSubmit={submitReply} className="border-t p-3">
                    <textarea
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        placeholder={t('cabinet.messages_reply_ph')}
                        rows={2}
                        maxLength={BODY_MAX}
                        aria-describedby="reply-count"
                        className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <FilePicker files={files} setFiles={setFiles} cfg={attachmentsCfg} />
                        <div className="flex items-center gap-2">
                            <CharCount id="reply-count" current={body.length} max={BODY_MAX} />
                            <button
                                type="submit"
                                disabled={sending || (body.trim() === '' && files.length === 0)}
                                className="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                            >
                                <Send className="size-4" aria-hidden="true" />
                                {sending ? t('cabinet.motivation_sending') : t('cabinet.messages_reply')}
                            </button>
                        </div>
                    </div>
                </form>
            )}
        </div>
    );
}

/** Dialogul „Mesaj nou" — picker de oameni + atașamente. */
function ComposeDialog({
    state,
    setState,
    compose,
    onSent,
}: {
    state: { open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean };
    setState: React.Dispatch<React.SetStateAction<{ open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean }>>;
    compose: Props['compose'];
    onSent: (threadId: number | null) => void;
}) {
    const t = useTranslations();
    const [files, setFiles] = useState<File[]>([]);
    const form = useForm<{ type: 'direct' | 'audience'; student_id: number | ''; recipient_user_id: number | ''; domain: string; subject: string; body: string }>({
        type: 'direct',
        student_id: '',
        recipient_user_id: '',
        domain: '',
        subject: '',
        body: '',
    });

    const student = compose.students.find((s) => s.id === Number(state.studentId));

    // Sincronizează formularul cu selecția din card la deschidere.
    function onOpenChange(open: boolean) {
        if (open) {
            form.setData({
                type: state.audience ? 'audience' : 'direct',
                student_id: state.studentId,
                recipient_user_id: state.recipientId,
                domain: '',
                subject: '',
                body: '',
            });
            setFiles([]);
        }

        setState((prev) => ({ ...prev, open }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.transform((data) => ({ ...data, files }));
        form.post(messageRoutes.send().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setFiles([]);
                onSent(null);
            },
        });
    }

    return (
        <Dialog open={state.open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('cabinet.messages_new')}
                        {student ? ` · ${t('cabinet.mailbox_about')} ${student.name.split(' ')[0]}` : ''}
                    </DialogTitle>
                    <DialogDescription className="sr-only">{t('cabinet.messages_new')}</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-3">
                    {compose.students.length > 1 && (
                        <label className="grid gap-1.5 text-xs text-muted-foreground">
                            {t('cabinet.messages_child')}
                            <select
                                value={form.data.student_id}
                                onChange={(e) => form.setData((p) => ({ ...p, student_id: Number(e.target.value), recipient_user_id: '' }))}
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                            >
                                {compose.students.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {!form.data.type || form.data.type === 'direct' ? (
                        <label className="grid gap-1.5 text-xs text-muted-foreground">
                            {t('cabinet.mailbox_pick_recipient')}
                            <select
                                value={form.data.recipient_user_id}
                                onChange={(e) => form.setData('recipient_user_id', Number(e.target.value))}
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                            >
                                <option value="">—</option>
                                {(student?.recipients ?? []).map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} ({t(`cabinet.role_${r.role}`, r.role)})
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : (
                        <label className="grid gap-1.5 text-xs text-muted-foreground">
                            {t('cabinet.messages_domain')}
                            <select
                                value={form.data.domain}
                                onChange={(e) => form.setData('domain', e.target.value)}
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                            >
                                <option value="">—</option>
                                {compose.audienceDomains.map((d) => (
                                    <option key={d} value={d}>
                                        {t(`cabinet.audience_${d}`, d)}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {/* Comutare între mesaj direct și audiență */}
                    {compose.canAudience && (
                        <button
                            type="button"
                            onClick={() => form.setData((p) => ({ ...p, type: p.type === 'audience' ? 'direct' : 'audience', recipient_user_id: '', domain: '' }))}
                            className={cn(
                                'inline-flex items-center gap-2 self-start rounded-md border px-3 py-1.5 text-xs font-medium transition-colors',
                                form.data.type === 'audience' ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted',
                            )}
                        >
                            <Building2 className="size-3.5" aria-hidden="true" />
                            {t('cabinet.mailbox_audience_channel')}
                        </button>
                    )}
                    {form.data.type === 'audience' && <p className="-mt-1 text-[11px] text-muted-foreground">{t('cabinet.mailbox_audience_routing')}</p>}

                    <div>
                        <input
                            type="text"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                            placeholder={t('cabinet.messages_subject')}
                            required
                            maxLength={SUBJECT_MAX}
                            aria-describedby="c-subject-count"
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <CharCount id="c-subject-count" current={form.data.subject.length} max={SUBJECT_MAX} />
                        {form.errors.subject && <p className="mt-1 text-xs text-destructive">{form.errors.subject}</p>}
                    </div>

                    <div>
                        <textarea
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            placeholder={t('cabinet.messages_body')}
                            rows={4}
                            required
                            maxLength={BODY_MAX}
                            aria-describedby="c-body-count"
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <CharCount id="c-body-count" current={form.data.body.length} max={BODY_MAX} />
                        {form.errors.body && <p className="mt-1 text-xs text-destructive">{form.errors.body}</p>}
                    </div>

                    <FilePicker files={files} setFiles={setFiles} cfg={compose.attachments} />

                    <button
                        type="submit"
                        disabled={
                            form.processing ||
                            form.data.subject.trim() === '' ||
                            form.data.body.trim() === '' ||
                            (form.data.type === 'direct' && form.data.recipient_user_id === '') ||
                            (form.data.type === 'audience' && form.data.domain === '')
                        }
                        className="inline-flex items-center justify-center gap-1.5 self-end rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        <Send className="size-4" aria-hidden="true" />
                        {t('cabinet.messages_send')}
                    </button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

MessagesPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_messages', href: '#' },
    ],
};
