<?php

namespace App\Policies;

use App\Models\HomeworkAssignment;
use App\Models\User;

/**
 * Temele se editează/retrag de AUTOR (sau de administrație). Ștergerea permanentă și restaurarea
 * din coș rămân la autoritatea academică — nici măcar autorul nu scoate definitiv date de catalog
 * (audit staff, finding #1 din raport-teme-staff.md).
 */
class HomeworkAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, HomeworkAssignment $homework): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return $user->teacher !== null || $user->isAdministrator();
    }

    public function update(User $user, HomeworkAssignment $homework): bool
    {
        return $this->isAuthorOrAdministration($user, $homework);
    }

    public function delete(User $user, HomeworkAssignment $homework): bool
    {
        return $this->isAuthorOrAdministration($user, $homework);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator() || $user->teacher !== null;
    }

    public function restore(User $user, HomeworkAssignment $homework): bool
    {
        return $user->canAdministerCatalog();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDelete(User $user, HomeworkAssignment $homework): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }

    private function isAuthorOrAdministration(User $user, HomeworkAssignment $homework): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $teacher = $user->teacher;

        return $teacher !== null && (int) $homework->teacher_id === (int) $teacher->id;
    }
}
