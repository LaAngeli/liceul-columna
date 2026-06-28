<?php

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comunicarea din cabinet (spec §4): inbox cu fire de conversație, trimitere filtrată ierarhic
 * (prin {@see SendMessage}) și solicitări de audiență rutate spre conducere.
 */
class MessagesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $threads = Message::query()
            ->whereNull('parent_id')
            ->where(function (Builder $query) use ($user): void {
                $query->where('recipient_user_id', $user->id)->orWhere('sender_user_id', $user->id);
            })
            ->with(['sender', 'recipient', 'student', 'replies' => fn ($query) => $query->with('sender')->oldest()])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Message $message): array => $this->presentThread($message, (int) $user->id))
            ->all();

        return Inertia::render('cabinet/messages', [
            'threads' => $threads,
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
            'subject' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $send = app(SendMessage::class);

        if ($data['type'] === 'audience') {
            $send->audience(
                $user,
                $student,
                $data['subject'] ?? 'Solicitare audiență',
                $data['body'],
                AudienceDomain::from((string) $data['domain']),
            );
        } else {
            abort_if($data['recipient_user_id'] === null, 422, 'Alege un destinatar.');
            $recipient = User::query()->findOrFail((int) $data['recipient_user_id']);
            $send->direct($user, $recipient, $data['body'], $data['subject'] ?? null, $student);
        }

        return back()->with('success', 'Mesajul a fost trimis.');
    }

    public function reply(Request $request, Message $message): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        app(SendMessage::class)->reply($request->user(), $message, $data['body']);

        return back()->with('success', 'Răspunsul a fost trimis.');
    }

    public function markRead(Request $request, Message $message): RedirectResponse
    {
        $rootId = $message->parent_id ?? $message->id;

        Message::query()
            ->where(function (Builder $query) use ($rootId): void {
                $query->whereKey($rootId)->orWhere('parent_id', $rootId);
            })
            ->where('recipient_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
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

        return [
            'id' => $root->id,
            'subject' => $root->subject ?? $root->type->label(),
            'type' => $root->type->value,
            'student' => $root->student?->full_name,
            'with' => $root->sender_user_id === $userId ? $root->recipient->name : $root->sender->name,
            'lastAt' => $last?->created_at?->format('d.m.Y H:i'),
            'unread' => $all->where('recipient_user_id', $userId)->whereNull('read_at')->count(),
            'messages' => $all->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => $message->sender_user_id === $userId,
                'senderName' => $message->sender->name,
                'at' => $message->created_at?->format('d.m.Y H:i'),
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

        return [
            'students' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->full_name,
                'recipients' => $send->allowedRecipientsForStudent($student),
            ])->all(),
            'canAudience' => $students->isNotEmpty(),
            'audienceDomains' => AudienceDomain::values(),
        ];
    }
}
