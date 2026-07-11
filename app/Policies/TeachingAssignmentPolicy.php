<?php

namespace App\Policies;

use App\Models\TeachingAssignment;
use App\Models\User;

/**
 * Alocările (profesor ↔ clasă ↔ disciplină) sunt fundamentul scoping-ului întregului catalog
 * (cine poate nota/consemna la ce clasă) — se scriu DOAR de configuratorii școlii (§3.3), ca și
 * nomenclatoarele. Consultarea: personalul academic (AT exclus, ca peste tot la datele academice).
 */
class TeachingAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    public function update(User $user, TeachingAssignment $assignment): bool
    {
        return $user->canConfigureSchool();
    }

    public function delete(User $user, TeachingAssignment $assignment): bool
    {
        return $user->canConfigureSchool();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    public function restore(User $user, TeachingAssignment $assignment): bool
    {
        return $user->canConfigureSchool();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    /**
     * Alocarea nu are istoric academic dependent prin FK (notele poartă autorul direct pe
     * `grades.teacher_id`) — ștergerea permanentă a unei alocări greșite rămâne la configuratori.
     */
    public function forceDelete(User $user, TeachingAssignment $assignment): bool
    {
        return $user->canConfigureSchool();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }
}
