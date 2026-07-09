<?php

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Actions\StoreMessageAttachments;
use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Student;
use App\Models\User;
use App\Support\MessageMailbox;
use App\Support\ThreadPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Poșta internă a cabinetului (spec §4): filtre ca la un client de e-mail — Toate / Necitite /
 * Preferate / Coș — peste firele de conversație. Trimiterea rămâne filtrată ierarhic (prin
 * {@see SendMessage}); preferatul și coșul sunt stare PER-UTILIZATOR, deci ce șterge un participant
 * nu afectează cutia celuilalt. Totul e comunicare INTERNĂ (fără e-mail extern).
 *
 * Logica de foldere/stare NU trăiește aici, ci în {@see MessageMailbox} — aceeași sursă pe care o
 * folosește și poșta personalului (Filament), ca cele două cutii să nu diveargă.
 */
class MessagesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user('web');
        $uid = (int) $user->id;
        $mailbox = MessageMailbox::for($user);

        $folder = (string) $request->query('folder', 'all');
        if (! in_array($folder, MessageMailbox::CABINET_FOLDERS, true)) {
            $folder = 'all';
        }

        $presenter = app(ThreadPresenter::class);

        $threads = $mailbox->folder($folder)
            ->with([
                'sender', 'recipient', 'student', 'attachments',
                'states' => fn ($q) => $q->where('user_id', $uid),
                'replies' => fn ($q) => $q->with(['sender', 'attachments'])->oldest(),
            ])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Message $message): array => $presenter->present($message, $uid))
            ->all();

        return Inertia::render('cabinet/messages', [
            'folder' => $folder,
            'threads' => $threads,
            'counts' => $mailbox->counts(MessageMailbox::CABINET_FOLDERS),
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
        MessageMailbox::for($request->user('web'))->markThreadRead($message);

        return back();
    }

    /** Comută marcajul „preferat" pe firul din care face parte mesajul. */
    public function toggleStar(Request $request, Message $message): RedirectResponse
    {
        $state = MessageMailbox::for($request->user('web'))->stateForThread($message);
        $state->starred_at = $state->starred_at === null ? now() : null;
        $state->save();

        return back();
    }

    /** Mută firul în coșul PROPRIU (nu afectează cutia celuilalt participant). */
    public function trash(Request $request, Message $message): RedirectResponse
    {
        $state = MessageMailbox::for($request->user('web'))->stateForThread($message);
        $state->trashed_at = now();
        $state->save();

        return back()->with('success', 'Conversația a fost mutată în coș.');
    }

    /** Restaurează firul din coș înapoi în folderul lui firesc. */
    public function restore(Request $request, Message $message): RedirectResponse
    {
        $state = MessageMailbox::for($request->user('web'))->stateForThread($message);
        $state->trashed_at = null;
        $state->save();

        return back()->with('success', 'Conversația a fost restaurată.');
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
