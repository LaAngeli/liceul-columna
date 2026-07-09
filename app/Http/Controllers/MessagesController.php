<?php

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageState;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Poșta internă a cabinetului (spec §4): foldere ca la un client de e-mail — Primite / Trimise /
 * Preferate / Șterse — peste firele de conversație. Trimiterea rămâne filtrată ierarhic (prin
 * {@see SendMessage}); preferatul și coșul sunt stare PER-UTILIZATOR ({@see MessageState}), deci
 * ce șterge un participant nu afectează cutia celuilalt. Totul e comunicare INTERNĂ (fără e-mail extern).
 */
class MessagesController extends Controller
{
    /** Pastilele-filtru ale poștei (folderele clasice, retrogradate la filtre peste roster). */
    private const FOLDERS = ['all', 'unread', 'starred', 'trash'];

    public function index(Request $request): Response
    {
        $user = $request->user('web');
        $uid = (int) $user->id;

        $folder = (string) $request->query('folder', 'all');
        if (! in_array($folder, self::FOLDERS, true)) {
            $folder = 'all';
        }

        $threads = $this->folderQuery($uid, $folder)
            ->with([
                'sender', 'recipient', 'student', 'attachments',
                'states' => fn ($q) => $q->where('user_id', $uid),
                'replies' => fn ($q) => $q->with(['sender', 'attachments'])->oldest(),
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Message $message): array => $this->presentThread($message, $uid))
            ->all();

        return Inertia::render('cabinet/messages', [
            'folder' => $folder,
            'threads' => $threads,
            'counts' => $this->folderCounts($uid),
            'compose' => $this->composeContext($user),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:direct,audience'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'domain' => ['required_if:type,audience', 'nullable', new Enum(AudienceDomain::class)],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            ...$this->attachmentRules(),
        ]);

        $user = $request->user('web');
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $send = app(SendMessage::class);

        if ($data['type'] === 'audience') {
            $message = $send->audience(
                $user,
                $student,
                $data['subject'] ?? 'Solicitare audiență',
                $data['body'],
                AudienceDomain::from((string) $data['domain']),
            );
        } else {
            abort_if($data['recipient_user_id'] === null, 422, 'Alege un destinatar.');
            $recipient = User::query()->findOrFail((int) $data['recipient_user_id']);
            $message = $send->direct($user, $recipient, $data['body'], $data['subject'] ?? null, $student);
        }

        app(StoreMessageAttachments::class)->handle($message, $request->file('files', []));

        return back()->with('success', 'Mesajul a fost trimis.');
    }

    public function reply(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            ...$this->attachmentRules(),
        ]);

        $reply = app(SendMessage::class)->reply($request->user('web'), $message, $data['body']);
        app(StoreMessageAttachments::class)->handle($reply, $request->file('files', []));

        return back()->with('success', 'Răspunsul a fost trimis.');
    }

    /**
     * Reguli de validare pentru atașamente (partajate de send/reply). Limitele vin din
     * `config/messaging.php` — o singură sursă, oglindită și în interfață.
     *
     * @return array<string, array<int, string>>
     */
    private function attachmentRules(): array
    {
        $extensions = implode(',', (array) config('messaging.attachments.extensions', []));

        return [
            'files' => ['nullable', 'array', 'max:'.(int) config('messaging.attachments.max_files', 5)],
            'files.*' => ['file', 'max:'.(int) config('messaging.attachments.max_file_kb', 8192), 'mimes:'.$extensions],
        ];
    }

    /**
     * Descarcă un atașament — DOAR pentru cei doi participanți ai firului din care face parte
     * mesajul (PII de minori). Servit inline (imaginile se randează în interfață); `nosniff`
     * previne interpretarea greșită a tipului.
     */
    public function downloadAttachment(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $message = $attachment->message;
        $uid = (int) $request->user('web')->id;

        abort_unless(
            in_array($uid, [(int) $message->sender_user_id, (int) $message->recipient_user_id], true),
            403,
        );

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markRead(Request $request, Message $message): RedirectResponse
    {
        $rootId = $message->parent_id ?? $message->id;

        Message::query()
            ->where(function (Builder $query) use ($rootId): void {
                $query->whereKey($rootId)->orWhere('parent_id', $rootId);
            })
            ->where('recipient_user_id', $request->user('web')->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /** Comută marcajul „preferat" pe firul din care face parte mesajul. */
    public function toggleStar(Request $request, Message $message): RedirectResponse
    {
        $state = $this->stateForThread($request, $message);
        $state->starred_at = $state->starred_at === null ? now() : null;
        $state->save();

        return back();
    }

    /** Mută firul în coșul PROPRIU (nu afectează cutia celuilalt participant). */
    public function trash(Request $request, Message $message): RedirectResponse
    {
        $state = $this->stateForThread($request, $message);
        $state->trashed_at = now();
        $state->save();

        return back()->with('success', 'Conversația a fost mutată în coș.');
    }

    /** Restaurează firul din coș înapoi în folderul lui firesc (Primite / Trimise). */
    public function restore(Request $request, Message $message): RedirectResponse
    {
        $state = $this->stateForThread($request, $message);
        $state->trashed_at = null;
        $state->save();

        return back()->with('success', 'Conversația a fost restaurată.');
    }

    /**
     * Starea (preferat/coș) a firului pentru utilizatorul curent — creată la nevoie. Autorizează
     * mai întâi: doar cei doi participanți la conversație pot acționa asupra ei.
     */
    private function stateForThread(Request $request, Message $message): MessageState
    {
        $uid = (int) $request->user('web')->id;
        $rootId = $message->parent_id ?? $message->id;
        $root = Message::query()->findOrFail($rootId);

        abort_unless(
            in_array($uid, [(int) $root->sender_user_id, (int) $root->recipient_user_id], true),
            403,
            'Nu faci parte din această conversație.',
        );

        return MessageState::query()->firstOrNew(['message_id' => $rootId, 'user_id' => $uid]);
    }

    /**
     * Query-ul de bază pentru un folder (fire-rădăcină, din perspectiva userului). FĂRĂ ordine/limită/
     * eager-load — acelea se adaugă separat (listare vs. numărare).
     *
     * @return Builder<Message>
     */
    private function folderQuery(int $uid, string $folder): Builder
    {
        $query = Message::query()
            ->whereNull('parent_id')
            ->where(fn (Builder $q) => $q->where('recipient_user_id', $uid)->orWhere('sender_user_id', $uid));

        return match ($folder) {
            // Toate = orice conversație în care particip, care nu e în coșul meu (fără distincția
            // Primite/Trimise — pentru o familie o conversație cu un profesor e UNA, indiferent cine
            // a scris primul; direcția rămâne doar ca prefix „Tu:" pe fragment).
            'all' => $query->whereDoesntHave('states', fn (Builder $q) => $q->where('user_id', $uid)->whereNotNull('trashed_at')),
            // Necitite = firele necitite, care nu-s în coș.
            'unread' => $this->withUnread(
                $query->whereDoesntHave('states', fn (Builder $q) => $q->where('user_id', $uid)->whereNotNull('trashed_at')),
                $uid,
            ),
            // Preferate = firele cu stea de la mine, care nu-s în coș (overlay, nu mutare).
            'starred' => $query
                ->whereDoesntHave('states', fn (Builder $q) => $q->where('user_id', $uid)->whereNotNull('trashed_at'))
                ->whereHas('states', fn (Builder $q) => $q->where('user_id', $uid)->whereNotNull('starred_at')),
            // Arhivă (coș) = firele mutate de mine în coș.
            'trash' => $query->whereHas('states', fn (Builder $q) => $q->where('user_id', $uid)->whereNotNull('trashed_at')),
            default => $query,
        };
    }

    /**
     * Totaluri + necitite pe fiecare folder (pentru navigația poștei).
     *
     * @return array<string, array{total: int, unread: int}>
     */
    private function folderCounts(int $uid): array
    {
        $counts = [];
        foreach (self::FOLDERS as $folder) {
            $counts[$folder] = [
                'total' => $this->folderQuery($uid, $folder)->count(),
                'unread' => $this->withUnread($this->folderQuery($uid, $folder), $uid)->count(),
            ];
        }

        return $counts;
    }

    /**
     * Restrânge la firele cu cel puțin un mesaj NECITIT primit de utilizator (rădăcină sau răspuns).
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    private function withUnread(Builder $query, int $uid): Builder
    {
        return $query->where(function (Builder $q) use ($uid): void {
            $q->where(fn (Builder $r) => $r->where('recipient_user_id', $uid)->whereNull('read_at'))
                ->orWhereHas('replies', fn (Builder $r) => $r->where('recipient_user_id', $uid)->whereNull('read_at'));
        });
    }

    /**
     * Un fir = mesajul-rădăcină + răspunsurile, prezentat din perspectiva userului curent.
     *
     * @return array<string, mixed>
     */
    private function presentThread(Message $root, int $userId): array
    {
        /** @var Collection<int, Message> $all */
        $all = collect([$root])->merge($root->replies)->sortBy('created_at')->values();
        $last = $all->last();
        $mineRoot = (int) $root->sender_user_id === $userId;

        $attachmentCount = $all->sum(fn (Message $m): int => $m->attachments->count());

        return [
            'id' => $root->id,
            'subject' => $root->subject ?? $root->type->label(),
            'type' => $root->type->value,
            'student' => $root->student?->full_name,
            'studentId' => $root->student_id,
            // Id-ul CELUILALT participant — cheia de mapare fir ↔ persoană din roster.
            'withId' => $mineRoot ? (int) $root->recipient_user_id : (int) $root->sender_user_id,
            'with' => $mineRoot ? $root->recipient->name : $root->sender->name,
            'direction' => $mineRoot ? 'sent' : 'received',
            'starred' => $root->states->first()?->starred_at !== null,
            'trashed' => $root->states->first()?->trashed_at !== null,
            'snippet' => $last !== null ? Str::limit((string) preg_replace('/\s+/', ' ', $last->body), 110) : '',
            'lastAt' => $last?->created_at?->format('d.m.Y H:i'),
            'unread' => $all->where('recipient_user_id', $userId)->whereNull('read_at')->count(),
            'attachmentCount' => $attachmentCount,
            'messages' => $all->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => (int) $message->sender_user_id === $userId,
                'senderName' => $message->sender->name,
                'at' => $message->created_at?->format('d.m.Y H:i'),
                'attachments' => $message->attachments->map(fn (MessageAttachment $a): array => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'size' => $a->humanSize(),
                    'isImage' => $a->isImage(),
                    'url' => route('cabinet.messages.attachment', $a),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Contextul de compunere: copiii (cu destinatarii permiși) + dacă se poate solicita audiență.
     *
     * @return array<string, mixed>
     */
    private function composeContext(User $user): array
    {
        $send = app(SendMessage::class);

        $students = $user->students()->get();
        $self = Student::query()->where('user_id', $user->id)->first();
        if ($self !== null) {
            $students->push($self);
        }
        $students = $students->unique('id')->values();

        // Solicitarea de audiență către conducere e prerogativa PĂRINTELUI (tutorelui legal), NU a elevului.
        // Elevul comunică doar direct cu profesorii/dirigintele lui; escaladarea către conducere se face
        // de familie. `hasRole` — verificăm rolul de Elev prin `guardian` (via spatie/permission).
        $isStudent = $user->hasRole(UserRole::Elev->value);

        return [
            'students' => $students->map(function (Student $student) use ($send): array {
                $class = $student->enrollments()->latest('id')->first()?->schoolClass;

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'classLabel' => $class !== null ? trim($class->name.' '.($class->section ?? '')) : null,
                    'recipients' => $send->allowedRecipientsForStudent($student),
                ];
            })->all(),
            'canAudience' => $students->isNotEmpty() && ! $isStudent,
            'audienceDomains' => AudienceDomain::values(),
            // Limitele de atașamente — o singură sursă (config), oglindită în interfață pentru
            // validare de curtoazie + afișarea regulii („max 5 · 8 MB").
            'attachments' => [
                'maxFiles' => (int) config('messaging.attachments.max_files', 5),
                'maxFileMb' => (int) round(((int) config('messaging.attachments.max_file_kb', 8192)) / 1024),
                'extensions' => array_values((array) config('messaging.attachments.extensions', [])),
            ],
        ];
    }
}
