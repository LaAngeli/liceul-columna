import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArchiveX,
    Building2,
    ChevronLeft,
    Download,
    FileText,
    Inbox,
    Mail,
    MailOpen,
    Paperclip,
    PencilLine,
    RotateCcw,
    Search,
    Send,
    Star,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { messages as messagesRoute } from '@/routes/cabinet';
import * as messageRoutes from '@/routes/cabinet/messages';

const SUBJECT_MAX = 120;
const BODY_MAX = 2000;

/**
 * Poșta cabinetului — client de e-mail intern (tipar Gmail), cu ACEEAȘI structură și logică de
 * foldere ca poșta personalului: Primite / Cu stea / Trimise / Arhivă / Coș, listă de conversații,
 * firul deschis în același ecran, compunerea în dialog. Regulile familiei rămân neschimbate:
 * destinatarii = profesorii/dirigintele copilului, plus solicitarea de audiență (doar părintele).
 */
type Folder = 'inbox' | 'starred' | 'sent' | 'archive' | 'trash';
type RecipientRole = 'profesor' | 'diriginte';

const FOLDERS: { key: Folder; icon: typeof Inbox; labelKey: string; emptyKey: string }[] = [
    { key: 'inbox', icon: Inbox, labelKey: 'mailbox_inbox', emptyKey: 'mailbox_empty_inbox' },
    { key: 'starred', icon: Star, labelKey: 'mailbox_starred', emptyKey: 'mailbox_empty_starred' },
    { key: 'sent', icon: Send, labelKey: 'mailbox_sent', emptyKey: 'mailbox_empty_sent' },
    { key: 'archive', icon: Archive, labelKey: 'mailbox_archive', emptyKey: 'mailbox_empty_archive' },
    { key: 'trash', icon: Trash2, labelKey: 'mailbox_trash', emptyKey: 'mailbox_empty_trash' },
];

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
    archived: boolean;
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
    q: string;
    threads: Thread[];
    counts: Record<Folder, FolderCount>;
    compose: {
        students: StudentOption[];
        canAudience: boolean;
        audienceDomains: string[];
        attachments: { maxFiles: number; maxFileMb: number; extensions: string[] };
    };
}

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

export default function MessagesPage({ folder, q, threads, counts, compose }: Props) {
    const t = useTranslations();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [search, setSearch] = useState(q);
    const [lightbox, setLightbox] = useState<string | null>(null);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [composeState, setComposeState] = useState<{ open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean }>({
        open: false,
        studentId: compose.students[0]?.id ?? '',
        recipientId: '',
        audience: false,
    });

    const selected = threads.find((th) => th.id === selectedId) ?? null;

    // Căutarea rulează pe SERVER (subiect + corp + corespondent), cu debounce — aceeași
    // semantică precum în poșta personalului.
    useEffect(() => {
        if (search === q) {
            return;
        }

        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }

        searchTimer.current = setTimeout(() => {
            router.get(
                messagesRoute({ query: { folder, q: search } }).url,
                {},
                { only: ['q', 'threads', 'counts'], preserveState: true, preserveScroll: true, replace: true },
            );
        }, 400);

        return () => {
            if (searchTimer.current) {
                clearTimeout(searchTimer.current);
            }
        };
    }, [search, q, folder]);

    function openThread(thread: Thread) {
        setSelectedId(thread.id);

        if (thread.unread > 0) {
            router.post(messageRoutes.read(thread.id).url, {}, { preserveScroll: true, preserveState: true, only: ['threads', 'counts'] });
        }
    }

    /** Acțiune pe fir (stea/arhivă/coș/restaurare/necitit) cu reîncărcare parțială. */
    function act(url: string, closeDetail = false) {
        router.post(url, {}, { preserveScroll: true, preserveState: true, only: ['threads', 'counts'] });

        if (closeDetail) {
            setSelectedId(null);
        }
    }

    function openCompose(audience: boolean) {
        setComposeState({
            open: true,
            studentId: compose.students[0]?.id ?? '',
            recipientId: '',
            audience,
        });
    }

    const folderMeta = FOLDERS.find((f) => f.key === folder) ?? FOLDERS[0];

    return (
        <>
            <Head title={t('cabinet.messages_title')} />
            <div className="mx-auto flex h-[calc(100dvh-8rem)] w-full max-w-6xl flex-col p-4">
                <div className="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row">
                    {/* ============ ȘINA: Scrie + foldere ============ */}
                    <aside className="shrink-0 lg:w-52">
                        <button
                            type="button"
                            onClick={() => openCompose(false)}
                            disabled={compose.students.length === 0}
                            className="mb-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-60 lg:w-auto lg:min-w-36"
                        >
                            <PencilLine className="size-4" aria-hidden="true" />
                            {t('cabinet.mailbox_write')}
                        </button>

                        <nav className="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible" aria-label={t('cabinet.mailbox_conversations')}>
                            {FOLDERS.map((f) => {
                                const active = f.key === folder;
                                const count = counts[f.key] ?? { total: 0, unread: 0 };
                                const badge = f.key === 'inbox' ? count.unread : f.key === 'sent' ? 0 : count.total;

                                return (
                                    <Link
                                        key={f.key}
                                        href={messagesRoute({ query: { folder: f.key, q: search } }).url}
                                        only={['folder', 'q', 'threads', 'counts']}
                                        preserveScroll
                                        preserveState
                                        onClick={() => setSelectedId(null)}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'inline-flex min-h-10 shrink-0 items-center gap-2.5 rounded-full px-3.5 text-sm transition-colors lg:rounded-l-none lg:rounded-r-full',
                                            active ? 'bg-primary/10 font-bold text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        <f.icon className="size-4 shrink-0" aria-hidden="true" />
                                        <span className="flex-1">{t(`cabinet.${f.labelKey}`)}</span>
                                        {badge > 0 && (
                                            <span
                                                className={cn(
                                                    'rounded-full px-1.5 text-[11px] font-bold tabular-nums',
                                                    f.key === 'inbox' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground',
                                                )}
                                            >
                                                {badge}
                                            </span>
                                        )}
                                    </Link>
                                );
                            })}
                        </nav>
                    </aside>

                    {/* ============ ZONA PRINCIPALĂ: listă SAU conversație ============ */}
                    <section className="flex min-h-0 flex-1 flex-col">
                        {selected === null ? (
                            <>
                                {/* Căutare */}
                                <div className="relative mb-3">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                    <input
                                        type="search"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder={t('cabinet.mailbox_search')}
                                        aria-label={t('cabinet.mailbox_search')}
                                        className="w-full rounded-full border border-input bg-background py-2.5 pr-4 pl-9 text-sm"
                                    />
                                </div>

                                {/* Lista de conversații */}
                                <div className="min-h-0 flex-1 overflow-y-auto rounded-xl border bg-card">
                                    {threads.length === 0 ? (
                                        <div className="flex flex-col items-center gap-2 py-14 text-center text-sm text-muted-foreground">
                                            <folderMeta.icon className="size-7 text-muted-foreground/50" aria-hidden="true" />
                                            {t(`cabinet.${folderMeta.emptyKey}`)}
                                        </div>
                                    ) : (
                                        <ul role="list" className="divide-y">
                                            {threads.map((thread) => (
                                                <MailRow
                                                    key={thread.id}
                                                    thread={thread}
                                                    folder={folder}
                                                    onOpen={() => openThread(thread)}
                                                    onStar={() => act(messageRoutes.star(thread.id).url)}
                                                    onArchive={() => act(messageRoutes.archive(thread.id).url)}
                                                    onTrash={() => act(messageRoutes.trash(thread.id).url)}
                                                    onRestore={() => act(messageRoutes.restore(thread.id).url)}
                                                    onUnread={() => act(messageRoutes.unread(thread.id).url)}
                                                />
                                            ))}
                                        </ul>
                                    )}
                                </div>
                                {threads.length >= 50 && <p className="mt-2 text-center text-[11px] text-muted-foreground">{t('cabinet.mailbox_cap')}</p>}
                            </>
                        ) : (
                            <div className="min-h-0 flex-1 rounded-xl border bg-card">
                                <ThreadDetail
                                    key={selected.id}
                                    thread={selected}
                                    attachmentsCfg={compose.attachments}
                                    onBack={() => setSelectedId(null)}
                                    onStar={() => act(messageRoutes.star(selected.id).url)}
                                    onArchive={() => act(messageRoutes.archive(selected.id).url, true)}
                                    onTrash={() => act(messageRoutes.trash(selected.id).url, true)}
                                    onRestore={() => act(messageRoutes.restore(selected.id).url, true)}
                                    onUnread={() => act(messageRoutes.unread(selected.id).url, true)}
                                    onImage={(url) => setLightbox(url)}
                                />
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

            <ComposeDialog
                state={composeState}
                setState={setComposeState}
                compose={compose}
                onSent={() => setComposeState((prev) => ({ ...prev, open: false }))}
            />
        </>
    );
}

/** Un rând de conversație, în stilul unui client de e-mail (necitit bold, acțiuni la hover). */
function MailRow({
    thread,
    folder,
    onOpen,
    onStar,
    onArchive,
    onTrash,
    onRestore,
    onUnread,
}: {
    thread: Thread;
    folder: Folder;
    onOpen: () => void;
    onStar: () => void;
    onArchive: () => void;
    onTrash: () => void;
    onRestore: () => void;
    onUnread: () => void;
}) {
    const t = useTranslations();
    const unread = thread.unread > 0;

    return (
        <li className="group relative flex items-center">
            <button
                type="button"
                onClick={onStar}
                aria-label={t(thread.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')}
                className="shrink-0 py-3 pr-1 pl-3 text-muted-foreground hover:text-amber-500"
            >
                <Star className={cn('size-4', thread.starred && 'fill-amber-400 text-amber-500')} aria-hidden="true" />
            </button>

            {/* Acțiunile de mai jos sunt absolute (în afara fluxului), deci la hover/focus le
                rezervăm explicit lățimea aici — altfel subiectul/fragmentul nu se trunchiază și
                curge pe sub iconițe. Ora (`meta`) e prea îngustă ca s-o ascundem doar pe ea. */}
            <button
                type="button"
                onClick={onOpen}
                className={cn(
                    'flex min-h-12 min-w-0 flex-1 items-center gap-3 py-3 pr-3 pl-1 text-left',
                    folder === 'trash'
                        ? 'group-focus-within:pr-12 lg:group-hover:pr-12' /* un singur buton (Restaurează) */
                        : 'group-focus-within:pr-[7.75rem] lg:group-hover:pr-[7.75rem]' /* 3 × size-9 + gap-uri + right-2 */,
                )}
            >
                <span className={cn('w-32 shrink-0 truncate text-sm sm:w-40', unread ? 'font-bold' : 'text-foreground/85')}>
                    {thread.with}
                </span>
                <span className="min-w-0 flex-1 truncate text-sm">
                    <span className={cn(unread && 'font-bold')}>{thread.subject}</span>
                    {thread.type === 'audience' && (
                        <span className="mx-1.5 inline-flex shrink-0 items-center rounded-full bg-primary/10 px-1.5 py-0.5 align-middle text-[10px] font-semibold text-primary">
                            {t('cabinet.mailbox_audiences')}
                        </span>
                    )}
                    <span className="hidden text-muted-foreground sm:inline"> — {thread.snippet}</span>
                </span>
                <span className="ml-auto flex shrink-0 items-center gap-2 group-focus-within:hidden lg:group-hover:hidden">
                    {thread.attachmentCount > 0 && <Paperclip className="size-3.5 text-muted-foreground" aria-hidden="true" />}
                    <time className="text-xs tabular-nums text-muted-foreground">{thread.lastAt}</time>
                </span>
            </button>

            {/* Acțiuni la hover (desktop) / mereu accesibile prin focus (tastatură) */}
            <span className="pointer-events-none absolute right-2 hidden items-center gap-0.5 group-focus-within:pointer-events-auto group-focus-within:flex lg:group-hover:pointer-events-auto lg:group-hover:flex">
                {folder === 'trash' ? (
                    <RowIcon label={t('cabinet.mailbox_restore')} onClick={onRestore}>
                        <RotateCcw className="size-4" aria-hidden="true" />
                    </RowIcon>
                ) : (
                    <>
                        <RowIcon
                            label={t(thread.archived ? 'cabinet.mailbox_unarchive' : 'cabinet.mailbox_archive_action')}
                            onClick={onArchive}
                        >
                            {thread.archived ? <ArchiveX className="size-4" aria-hidden="true" /> : <Archive className="size-4" aria-hidden="true" />}
                        </RowIcon>
                        {unread ? (
                            <RowIcon label={t('cabinet.messages_title')} onClick={onOpen}>
                                <MailOpen className="size-4" aria-hidden="true" />
                            </RowIcon>
                        ) : (
                            <RowIcon label={t('cabinet.mailbox_mark_unread')} onClick={onUnread}>
                                <Mail className="size-4" aria-hidden="true" />
                            </RowIcon>
                        )}
                        <RowIcon label={t('cabinet.mailbox_trash_action')} onClick={onTrash} danger>
                            <Trash2 className="size-4" aria-hidden="true" />
                        </RowIcon>
                    </>
                )}
            </span>
        </li>
    );
}

function RowIcon({ label, onClick, danger, children }: { label: string; onClick: () => void; danger?: boolean; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title={label}
            className={cn(
                'flex size-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted',
                danger ? 'hover:text-destructive' : 'hover:text-foreground',
            )}
        >
            {children}
        </button>
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

/** Conversația deschisă: antet cu acțiuni, firul de mesaje, răspuns inline dedesubt. */
function ThreadDetail({
    thread,
    attachmentsCfg,
    onBack,
    onStar,
    onArchive,
    onTrash,
    onRestore,
    onUnread,
    onImage,
}: {
    thread: Thread;
    attachmentsCfg: { maxFiles: number; maxFileMb: number; extensions: string[] };
    onBack: () => void;
    onStar: () => void;
    onArchive: () => void;
    onTrash: () => void;
    onRestore: () => void;
    onUnread: () => void;
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
            <div className="flex items-center gap-2 border-b p-3">
                <button type="button" onClick={onBack} aria-label={t('cabinet.mailbox_back')} className="rounded-full p-2 text-muted-foreground hover:bg-muted">
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
                <button type="button" onClick={onStar} aria-label={t(thread.starred ? 'cabinet.mailbox_unstar' : 'cabinet.mailbox_star')} className="rounded-full p-2 text-muted-foreground hover:bg-muted">
                    <Star className={cn('size-4', thread.starred && 'fill-amber-400 text-amber-500')} aria-hidden="true" />
                </button>
                {thread.trashed ? (
                    <button type="button" onClick={onRestore} aria-label={t('cabinet.mailbox_restore')} className="rounded-full p-2 text-muted-foreground hover:bg-muted hover:text-primary">
                        <RotateCcw className="size-4" aria-hidden="true" />
                    </button>
                ) : (
                    <>
                        <button
                            type="button"
                            onClick={onArchive}
                            aria-label={t(thread.archived ? 'cabinet.mailbox_unarchive' : 'cabinet.mailbox_archive_action')}
                            className="rounded-full p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            {thread.archived ? <ArchiveX className="size-4" aria-hidden="true" /> : <Archive className="size-4" aria-hidden="true" />}
                        </button>
                        <button type="button" onClick={onUnread} aria-label={t('cabinet.mailbox_mark_unread')} className="rounded-full p-2 text-muted-foreground hover:bg-muted hover:text-foreground">
                            <Mail className="size-4" aria-hidden="true" />
                        </button>
                        <button type="button" onClick={onTrash} aria-label={t('cabinet.mailbox_trash_action')} className="rounded-full p-2 text-muted-foreground hover:bg-muted hover:text-destructive">
                            <Trash2 className="size-4" aria-hidden="true" />
                        </button>
                    </>
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

            {/* Firul */}
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

            {/* Răspuns INLINE — firul rămâne vizibil; ascuns când firul e la coș */}
            {!thread.trashed && (
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
                                disabled={sending || body.trim() === ''}
                                className="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                            >
                                <Send className="size-4" aria-hidden="true" />
                                {sending ? t('cabinet.motivation_sending') : t('cabinet.mailbox_send')}
                            </button>
                        </div>
                    </div>
                </form>
            )}
        </div>
    );
}

/** Dialogul „Mesaj nou" — regulile familiei: copil → profesorii/dirigintele lui; audiență (părinte). */
function ComposeDialog({
    state,
    setState,
    compose,
    onSent,
}: {
    state: { open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean };
    setState: React.Dispatch<React.SetStateAction<{ open: boolean; studentId: number | ''; recipientId: number | ''; audience: boolean }>>;
    compose: Props['compose'];
    onSent: () => void;
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

    const student = compose.students.find((s) => s.id === Number(form.data.student_id || state.studentId));

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
                onSent();
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

                    {/* Comutare între mesaj direct și audiență (doar părintele) */}
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
                        {t('cabinet.mailbox_send')}
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
