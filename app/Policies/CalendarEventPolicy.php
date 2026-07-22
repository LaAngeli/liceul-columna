<?php

namespace App\Policies;

use App\Enums\CalendarEventScope;
use App\Models\CalendarEvent;
use App\Models\Student;
use App\Models\User;

/**
 * Conducerea (`canPublishContent`) publică evenimente de orice audiență; dirigintele creează și
 * modifică DOAR evenimente de clasă, pentru clasele lui. Ștergerea permanentă și restaurarea
 * rămân la conducere.
 */
class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCalendarEvents();
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->canModify($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->canManageCalendarEvents();
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->canModify($user, $event);
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->canModify($user, $event);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCalendarEvents();
    }

    public function restore(User $user, CalendarEvent $event): bool
    {
        return $user->canPublishContent();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canPublishContent();
    }

    public function forceDelete(User $user, CalendarEvent $event): bool
    {
        return $user->canPublishContent();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canPublishContent();
    }

    private function canModify(User $user, CalendarEvent $event): bool
    {
        if ($user->canPublishContent()) {
            return true;
        }

        $homeroomClassIds = $user->homeroomSchoolClassIds();

        if ($homeroomClassIds === []) {
            return false;
        }

        // Dirigintele: evenimentele de clasă ale claselor lui...
        if ($event->visibility_scope === CalendarEventScope::SchoolClass) {
            return $event->school_class_id !== null
                && in_array($event->school_class_id, $homeroomClassIds, true);
        }

        // ...și evenimentele nominale unde TOȚI elevii vizați sunt din clasele lui (un eveniment
        // cu elevi din afara sferei rămâne al conducerii — nu-l atinge dirigintele parțial vizat).
        if ($event->visibility_scope === CalendarEventScope::Students) {
            $students = $event->students;

            return $students->isNotEmpty() && $students->every(
                fn (Student $student): bool => in_array($student->currentSchoolClass()?->id, $homeroomClassIds, true),
            );
        }

        return false;
    }
}
