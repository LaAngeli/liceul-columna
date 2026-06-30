import { Head, router, useForm } from '@inertiajs/react';
import { MailX } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/cabinet/empty-state';
import { useTranslations } from '@/lib/i18n';
import { dashboard } from '@/routes';
// Importăm sub-namespace-ul COMPLET (send/reply/read) — `cabinet/index.ts` re-exportă doar funcția
// `messages` (GET inbox), nu și acțiunile de sub-rută.
import * as messageRoutes from '@/routes/cabinet/messages';

interface Recipient {
    id: number;
    name: string;
    role: 'profesor' | 'diriginte';
}

interface StudentOption {
    id: number;
    name: string;
    recipients: Recipient[];
}

interface ThreadMessage {
    id: number;
    body: string;
    mine: boolean;
    senderName: string;
    at: string | null;
}

interface Thread {
    id: number;
    subject: string;
    type: 'direct' | 'audience';
    student: string | null;
    with: string;
    lastAt: string | null;
    unread: number;
    messages: ThreadMessage[];
}

interface Props {
    threads: Thread[];
    compose: {
        students: StudentOption[];
        canAudience: boolean;
        audienceDomains: string[];
    };
}

export default function MessagesPage({ threads, compose }: Props) {
    const t = useTranslations();
    const [expanded, setExpanded] = useState<number | null>(null);
    const [replyBody, setReplyBody] = useState<Record<number, string>>({});
    // Set de id-uri de thread aflate în trimitere — folosit pentru a dezactiva butonul de reply
    // și a preveni dubla-trimitere când backend-ul nu răspunde instant.
    const [replying, setReplying] = useState<Set<number>>(new Set());

    const form = useForm<{
        type: 'direct' | 'audience';
        student_id: number | '';
        recipient_user_id: number | '';
        domain: string;
        subject: string;
        body: string;
    }>({
        type: 'direct',
        student_id: compose.students[0]?.id ?? '',
        recipient_user_id: '',
        domain: '',
        subject: '',
        body: '',
    });

    const currentStudent = compose.students.find((s) => s.id === Number(form.data.student_id));
    const recipients = currentStudent?.recipients ?? [];

    function submitCompose(e: React.FormEvent) {
        e.preventDefault();
        form.post(messageRoutes.send().url, {
            preserveScroll: true,
            onSuccess: () => form.reset('subject', 'body', 'recipient_user_id', 'domain'),
        });
    }

    function toggleThread(thread: Thread) {
        const next = expanded === thread.id ? null : thread.id;
        setExpanded(next);

        if (next !== null && thread.unread > 0) {
            router.post(messageRoutes.read(thread.id).url, {}, { preserveScroll: true, preserveState: true });
        }
    }

    function submitReply(e: React.FormEvent, threadId: number) {
        e.preventDefault();
        const body = (replyBody[threadId] ?? '').trim();

        if (body === '' || replying.has(threadId)) {
            return;
        }

        setReplying((prev) => new Set(prev).add(threadId));
        router.post(
            messageRoutes.reply(threadId).url,
            { body },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setReplyBody((prev) => ({ ...prev, [threadId]: '' })),
                onFinish: () =>
                    setReplying((prev) => {
                        const next = new Set(prev);
                        next.delete(threadId);

                        return next;
                    }),
            },
        );
    }

    return (
        <>
            <Head title={t('cabinet.messages_title')} />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{t('cabinet.messages_title')}</h1>

                {/* Compunere (comunicare rapidă filtrată) */}
                {/* Empty state explicit când utilizatorul N-ARE pe cine să contacteze direct
                    (audit § mesaje #8) — altfel formularul „dispărea" silențios și inboxul rămânea singur. */}
                {compose.students.length === 0 && (
                    <EmptyState
                        icon={MailX}
                        title={t('cabinet.messages_no_channel')}
                        description={t('cabinet.messages_no_channel_hint')}
                    />
                )}

                {compose.students.length > 0 && (
                    <form
                        onSubmit={submitCompose}
                        className="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
                    >
                        <p className="mb-3 text-sm font-medium">{t('cabinet.messages_new')}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {compose.students.length > 1 && (
                                <label className="grid gap-1.5 text-xs text-muted-foreground">
                                    {t('cabinet.messages_child')}
                                    <select
                                        value={form.data.student_id}
                                        onChange={(e) => form.setData('student_id', Number(e.target.value))}
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

                            <label className="grid gap-1.5 text-xs text-muted-foreground">
                                {t('cabinet.messages_to')}
                                <select
                                    value={form.data.type === 'audience' ? 'audience' : form.data.recipient_user_id}
                                    onChange={(e) => {
                                        if (e.target.value === 'audience') {
                                            form.setData((prev) => ({ ...prev, type: 'audience', recipient_user_id: '' }));
                                        } else {
                                            form.setData((prev) => ({
                                                ...prev,
                                                type: 'direct',
                                                recipient_user_id: Number(e.target.value),
                                            }));
                                        }
                                    }}
                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="">—</option>
                                    {recipients.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name} ({t(`cabinet.role_${r.role}`, r.role)})
                                        </option>
                                    ))}
                                    {compose.canAudience && (
                                        <option value="audience">{t('cabinet.messages_audience')}</option>
                                    )}
                                </select>
                            </label>

                            {form.data.type === 'audience' && (
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
                        </div>

                        <input
                            type="text"
                            value={form.data.subject}
                            onChange={(e) => form.setData('subject', e.target.value)}
                            placeholder={t('cabinet.messages_subject')}
                            className="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            maxLength={150}
                        />
                        {form.errors.subject && <p className="mt-1 text-xs text-destructive">{form.errors.subject}</p>}

                        <textarea
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                            placeholder={t('cabinet.messages_body')}
                            rows={3}
                            required
                            className="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            maxLength={2000}
                        />
                        {form.errors.body && <p className="mt-1 text-xs text-destructive">{form.errors.body}</p>}

                        {recipients.length === 0 && form.data.type === 'direct' && (
                            <p className="mt-2 text-xs text-muted-foreground">{t('cabinet.messages_no_recipients')}</p>
                        )}

                        <button
                            type="submit"
                            disabled={
                                form.processing ||
                                (form.data.type === 'direct' && form.data.recipient_user_id === '') ||
                                (form.data.type === 'audience' && form.data.domain === '')
                            }
                            className="mt-3 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                        >
                            {t('cabinet.messages_send')}
                        </button>
                    </form>
                )}

                {/* Inbox */}
                <section className="flex flex-col gap-3">
                    {threads.length === 0 && (
                        <EmptyState icon={MailX} title={t('cabinet.messages_empty')} />
                    )}

                    {threads.map((thread) => (
                        <article
                            key={thread.id}
                            className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                        >
                            <button
                                type="button"
                                onClick={() => toggleThread(thread)}
                                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/50"
                            >
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 font-medium">
                                        {thread.unread > 0 && <span className="size-2 shrink-0 rounded-full bg-primary" />}
                                        <span className="truncate">{thread.subject}</span>
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {t('cabinet.messages_with')} {thread.with}
                                        {thread.student ? ` · ${thread.student}` : ''}
                                    </p>
                                </div>
                                <span className="shrink-0 text-xs text-muted-foreground">{thread.lastAt}</span>
                            </button>

                            {expanded === thread.id && (
                                <div className="border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                                    <div className="flex flex-col gap-2">
                                        {thread.messages.map((m) => (
                                            <div
                                                key={m.id}
                                                className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                                                    m.mine
                                                        ? 'self-end bg-primary/10 text-foreground'
                                                        : 'self-start bg-muted text-foreground'
                                                }`}
                                            >
                                                <p className="mb-0.5 text-xs text-muted-foreground">
                                                    {m.senderName} · {m.at}
                                                </p>
                                                <p className="whitespace-pre-wrap">{m.body}</p>
                                            </div>
                                        ))}
                                    </div>

                                    <form onSubmit={(e) => submitReply(e, thread.id)} className="mt-3 flex gap-2">
                                        <input
                                            type="text"
                                            value={replyBody[thread.id] ?? ''}
                                            onChange={(e) =>
                                                setReplyBody((prev) => ({ ...prev, [thread.id]: e.target.value }))
                                            }
                                            placeholder={t('cabinet.messages_reply_ph')}
                                            className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            maxLength={2000}
                                        />
                                        <button
                                            type="submit"
                                            disabled={replying.has(thread.id) || (replyBody[thread.id] ?? '').trim() === ''}
                                            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                                        >
                                            {replying.has(thread.id)
                                                ? t('cabinet.motivation_sending')
                                                : t('cabinet.messages_reply')}
                                        </button>
                                    </form>
                                </div>
                            )}
                        </article>
                    ))}
                </section>
            </div>
        </>
    );
}

MessagesPage.layout = {
    breadcrumbs: [
        { title: 'action.cabinet', href: dashboard() },
        { title: 'cabinet.nav_messages', href: '#' },
    ],
};
