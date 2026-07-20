<?php

namespace App\Policies;

use App\Filament\Resources\Lessons\LessonResource;
use App\Models\Lesson;
use App\Models\User;

/**
 * Orarul STRUCTURAT (sloturile pe lecții, sursa calculului de risc al amânării): aceeași împărțire
 * ca la orarele publicate — citit de conducere și de personalul pedagogic, scris de administratorul
 * operațional. Perimetrul profesorului/dirigintelui (doar clasele proprii) se aplică la nivel de
 * INTEROGARE, în {@see LessonResource::getEloquentQuery()}: policy-ul
 * răspunde „ce ai voie să faci", scope-ul răspunde „peste ce rânduri".
 *
 * Modelul are SoftDeletes; fără policy, `restore`/`forceDelete` cădeau pe „permis" pentru orice rol.
 */
class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewSchedules();
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $user->canViewSchedules();
    }

    public function create(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->canManageSchedules();
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->canManageSchedules();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function restore(User $user, Lesson $lesson): bool
    {
        return $user->canManageSchedules();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return $user->canManageSchedules();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canManageSchedules();
    }
}
