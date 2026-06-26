<?php

namespace App\Actions;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trimiterea mesajelor cu filtrare IERARHICĂ pe server (spec §4.2): comunicarea e liberă spre
 * nivelul firesc (familie ↔ profesorul/dirigintele copilului), dar familia NU scrie direct
 * conducerii — pentru asta există „Solicitarea de audiență", rutată automat spre prim-vicedirector
 * (în lipsa vicedirectorilor de domeniu din spec), escaladabilă spre director. Canalele nepermise
 * nu apar în interfață, dar regula reală e aici, verificată la fiecare scriere.
 */
class SendMessage
{
    /**
     * Mesaj direct. Aruncă 403 dacă regula ierarhică nu permite perechea expeditor→destinatar.
     */
    public function direct(
        User $sender,
        User $recipient,
        string $body,
        ?string $subject = null,
        ?Student $student = null,
        ?Message $replyTo = null,
    ): Message {
        abort_unless($this->canSendDirect($sender, $recipient, $student), 403, 'Canal de comunicare nepermis.');

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'student_id' => $student !== null ? $student->id : $replyTo?->student_id,
            'parent_id' => $replyTo?->id,
            'type' => MessageType::Direct,
            'subject' => $subject ?? ($replyTo !== null ? 'Re: '.(string) $replyTo->subject : null),
            'body' => $body,
        ]);
    }

    /**
     * Răspuns într-un fir EXISTENT. Nu se reaplică filtrarea ierarhică — canalul a fost deja
     * deschis legitim; doar participanții la fir pot răspunde (ex. prim-vicedirectorul răspunde
     * la o solicitare de audiență, deși nu „predă" elevului).
     */
    public function reply(User $sender, Message $original, string $body): Message
    {
        abort_unless(
            in_array($sender->id, [$original->sender_user_id, $original->recipient_user_id], true),
            403,
            'Nu faci parte din această conversație.',
        );

        $recipientId = $original->sender_user_id === $sender->id
            ? $original->recipient_user_id
            : $original->sender_user_id;

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipientId,
            'student_id' => $original->student_id,
            'parent_id' => $original->parent_id ?? $original->id,
            'type' => $original->type,
            'subject' => str_starts_with((string) $original->subject, 'Re: ')
                ? $original->subject
                : 'Re: '.(string) $original->subject,
            'body' => $body,
        ]);
    }

    /**
     * Solicitare de audiență a familiei → rutată către conducere (prim-vicedirector).
     */
    public function audience(User $sender, Student $student, string $subject, string $body): Message
    {
        abort_unless($this->isFamilyOf($sender, $student), 403, 'Doar familia poate solicita audiență.');

        $recipient = $this->audienceTarget();
        abort_unless($recipient !== null, 422, 'Nu există un membru al conducerii pentru rutarea audienței.');

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'student_id' => $student->id,
            'type' => MessageType::Audience,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /**
     * Regula centrală „cine poate scrie cui" (§4.2):
     * - familia → doar profesorul/dirigintele copilului (NU conducerea: aceea e via audiență);
     * - personalul → familia unui elev pe care îl predă/conduce, sau alt membru al personalului;
     * - familie → familie: interzis.
     */
    public function canSendDirect(User $sender, User $recipient, ?Student $student): bool
    {
        if ($sender->id === $recipient->id) {
            return false;
        }

        $senderIsStaff = $sender->hasAnyRole(UserRole::panelRoleValues());
        $recipientIsStaff = $recipient->hasAnyRole(UserRole::panelRoleValues());

        // Familie ca expeditor: doar către profesorul/dirigintele copilului.
        if (! $senderIsStaff) {
            return $student !== null
                && $this->isFamilyOf($sender, $student)
                && $recipientIsStaff
                && $this->teachesStudent($recipient, $student);
        }

        // Personal → familie: doar către familia unui elev pe care îl predă/conduce.
        if (! $recipientIsStaff) {
            return $student !== null
                && $this->isFamilyOf($recipient, $student)
                && $this->teachesStudent($sender, $student);
        }

        // Personal → personal: permis (comunicare internă / escaladare ierarhică).
        return true;
    }

    /**
     * Lista destinatarilor permiși pentru familia unui elev: profesorii care îi predau + dirigintele.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    public function allowedRecipientsForStudent(Student $student): array
    {
        $classIds = $student->enrollments()->pluck('school_class_id')
            ->map(static fn ($id): int => (int) $id)->all();

        if ($classIds === []) {
            return [];
        }

        $homeroomTeacherIds = Teacher::query()
            ->whereHas('homeroomClasses', fn (Builder $q) => $q->whereIn('id', $classIds))
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        // Profesorii care predau la clasele elevului + dirigintele clasei.
        $teachers = Teacher::query()
            ->whereNotNull('user_id')
            ->where(function (Builder $query) use ($classIds): void {
                $query->whereHas('teachingAssignments', fn (Builder $q) => $q->whereIn('school_class_id', $classIds))
                    ->orWhereHas('homeroomClasses', fn (Builder $q) => $q->whereIn('id', $classIds));
            })
            ->get();

        // Dedup pe user id; dirigintele primește eticheta „diriginte" chiar dacă și predă.
        $recipients = [];
        foreach ($teachers as $teacher) {
            if ($teacher->user_id === null) {
                continue;
            }
            $recipients[(int) $teacher->user_id] = [
                'id' => (int) $teacher->user_id,
                'name' => $teacher->full_name,
                'role' => in_array((int) $teacher->id, $homeroomTeacherIds, true) ? 'diriginte' : 'profesor',
            ];
        }

        return array_values($recipients);
    }

    private function isFamilyOf(User $user, Student $student): bool
    {
        return $user->students()->whereKey($student->id)->exists() || $student->user_id === $user->id;
    }

    private function teachesStudent(User $staff, Student $student): bool
    {
        $teacher = $staff->teacher;

        if ($teacher === null) {
            return false;
        }

        $classIds = $teacher->visibleSchoolClassIds();

        if ($classIds === []) {
            return false;
        }

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('school_class_id', $classIds)
            ->exists();
    }

    private function audienceTarget(): ?User
    {
        foreach ([UserRole::PrimVicedirector, UserRole::Director, UserRole::AdministratorOperational] as $role) {
            $user = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', $role->value))
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
