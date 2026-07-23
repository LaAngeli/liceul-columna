<?php

namespace App\Actions;

use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Publică un anunț al conducerii (spec §4): îl marchează publicat, reține câți destinatari are și
 * îl trimite ca notificare AUDIENȚEI alese ({@see AnnouncementAudience}) — sursa unică a rezolvării
 * destinatarilor, folosită și de rezumatul live din formular ({@see resolveRecipients}). Notificarea
 * poartă `announcement_id` în payload, ca să se poată număra confirmările de citire per anunț.
 *
 * Conturile SUSPENDATE sunt excluse din orice audiență: nu se pot autentifica, deci notificarea
 * ar rămâne necitită pe vecie și ar strica onestitatea pâlniei de citire.
 */
class BroadcastAnnouncement
{
    public function publish(Announcement $announcement): void
    {
        // Idempotent: un anunț deja publicat NU se re-difuzează (altfel familiile primesc de mai multe
        // ori aceeași notificare, iar recipients_count se suprascrie). Audit S-8.
        if ($announcement->published_at !== null) {
            return;
        }

        $recipients = $this->resolveRecipients($announcement);

        $announcement->update([
            'published_at' => now(),
            'recipients_count' => $recipients->count(),
        ]);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new CatalogNotification(
                NotificationType::Announcement,
                url: route('cabinet.notifications', [], false),
                customTitle: $announcement->title,
                customBody: $announcement->body,
                meta: ['announcement_id' => $announcement->id],
            ));
        }
    }

    /**
     * Destinatarii REALI ai anunțului, după audiența lui — deduplicați pe utilizator și fără
     * conturile suspendate. Publicarea și rezumatul din formular trec prin ACEEAȘI metodă:
     * numărul confirmat înainte de publicare e exact numărul difuzat.
     *
     * @return Collection<int, User>
     */
    public function resolveRecipients(Announcement $announcement): Collection
    {
        $recipients = match ($announcement->audience) {
            AnnouncementAudience::Families => $this->familyAccounts(),
            AnnouncementAudience::School => $this->allAccounts(),
            AnnouncementAudience::Classes => $this->classFamilies($announcement->schoolClasses()->pluck('school_classes.id')->all()),
            AnnouncementAudience::Students => $this->nominalFamilies(
                $announcement->students()->pluck('students.id')->all(),
                $announcement->audience_reach ?? AudienceReach::Both,
            ),
            AnnouncementAudience::SubjectTeachers => $this->subjectTeachers($announcement->subject_id),
            AnnouncementAudience::Users => $announcement->users()->get(),
        };

        return $recipients
            ->filter(fn (User $user): bool => $user->suspended_at === null)
            ->unique('id')
            ->values();
    }

    /**
     * Rezumat pentru FORMULAR (starea nu e încă salvată): aceeași logică de rezolvare, pe valori
     * brute. Întoarce numărul de conturi care ar primi anunțul.
     *
     * @param  array<int, int|string>  $classIds
     * @param  array<int, int|string>  $studentIds
     * @param  array<int, int|string>  $userIds
     */
    public function previewCount(
        mixed $audience,
        array $classIds = [],
        array $studentIds = [],
        mixed $reach = null,
        mixed $subjectId = null,
        array $userIds = [],
    ): ?int {
        $audienceCase = is_string($audience) ? AnnouncementAudience::tryFrom($audience) : null;

        if ($audienceCase === null) {
            return null;
        }

        $reachCase = is_string($reach) ? (AudienceReach::tryFrom($reach) ?? AudienceReach::Both) : AudienceReach::Both;

        $recipients = match ($audienceCase) {
            AnnouncementAudience::Families => $this->familyAccounts(),
            AnnouncementAudience::School => $this->allAccounts(),
            AnnouncementAudience::Classes => $this->classFamilies(self::ids($classIds)),
            AnnouncementAudience::Students => $this->nominalFamilies(self::ids($studentIds), $reachCase),
            AnnouncementAudience::SubjectTeachers => $this->subjectTeachers(is_numeric($subjectId) ? (int) $subjectId : null),
            AnnouncementAudience::Users => User::query()->whereKey(self::ids($userIds))->get(),
        };

        return $recipients
            ->filter(fn (User $user): bool => $user->suspended_at === null)
            ->unique('id')
            ->count();
    }

    /**
     * Toate conturile de familie (părinți + elevi) — defaultul istoric.
     *
     * @return Collection<int, User>
     */
    private function familyAccounts(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [UserRole::Parinte->value, UserRole::Elev->value]))
            ->get();
    }

    /**
     * Toată instituția: familiile + tot personalul = orice cont cu un rol atribuit.
     *
     * @return Collection<int, User>
     */
    private function allAccounts(): Collection
    {
        return User::query()->whereHas('roles')->get();
    }

    /**
     * Familiile elevilor ÎNMATRICULAȚI ACTIV în clasele alese (left_on null — cine a plecat din
     * clasă nu mai primește anunțurile ei).
     *
     * @param  array<int, int>  $classIds
     * @return Collection<int, User>
     */
    private function classFamilies(array $classIds): Collection
    {
        if ($classIds === []) {
            return collect();
        }

        return Student::query()
            ->with(['user', 'guardians'])
            ->whereHas('enrollments', fn (Builder $enrollment) => $enrollment
                ->whereIn('school_class_id', $classIds)
                ->whereNull('left_on'))
            ->get()
            ->flatMap(fn (Student $student): Collection => $student->notifiableUsers());
    }

    /**
     * Elevii aleși nominal, filtrați pe reach: contul elevului și/sau tutorii lui.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, User>
     */
    private function nominalFamilies(array $studentIds, AudienceReach $reach): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return Student::query()
            ->with(['user', 'guardians'])
            ->whereKey($studentIds)
            ->get()
            ->flatMap(function (Student $student) use ($reach): Collection {
                /** @var Collection<int, User> $recipients */
                $recipients = collect();

                if ($reach->includesStudent() && $student->user !== null) {
                    $recipients->push($student->user);
                }

                if ($reach->includesGuardians()) {
                    $recipients = $recipients->concat($student->guardians);
                }

                return $recipients;
            });
    }

    /**
     * Profesorii care predau disciplina (din alocările didactice) — comunicare de catedră.
     *
     * @return Collection<int, User>
     */
    private function subjectTeachers(?int $subjectId): Collection
    {
        if ($subjectId === null) {
            return collect();
        }

        return Teacher::query()
            ->with('user')
            ->whereHas('teachingAssignments', fn (Builder $assignment) => $assignment->where('subject_id', $subjectId))
            ->get()
            ->map(fn (Teacher $teacher): ?User => $teacher->user)
            ->filter()
            ->values();
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private static function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }
}
