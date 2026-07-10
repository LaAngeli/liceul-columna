<?php

namespace App\Policies;

use App\Enums\CalendarEventScope;
use App\Models\CalendarEvent;
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

        return $event->visibility_scope === CalendarEventScope::SchoolClass
            && $event->school_class_id !== null
            && in_array($event->school_class_id, $user->homeroomSchoolClassIds(), true);
    }
}
