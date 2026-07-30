<?php

namespace App\Policies;

use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
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

    /**
     * Corecția DIRECTĂ (decizia beneficiarului, 2026-07-31, o răstoarnă pe cea din 2026-07-15):
     * AUTORUL și DIRIGINTELE clasei vizate corectează fără aprobare — fluxul cerere → judecată a
     * fost eliminat; schimbarea de conținut se consemnează automat în registrul de corecții.
     * Administrația păstrează editarea directă. Dreptul dirigintelui vine din DESEMNARE, pe
     * contextul activ (multi-rol în context Profesor = puteri de dirigenție stinse, F3).
     */
    public function update(User $user, HomeworkAssignment $homework): bool
    {
        return $this->isAuthorOrAdministration($user, $homework)
            || $this->isHomeroomTeacherOfClass($user, $homework);
    }

    /**
     * Tema vizează exact clasa de dirigenție a userului (treaptă + literă)? Temele pe TOATĂ
     * treapta (litera goală) NU intră: ar afecta și clasele altor diriginți — rămân pe
     * autor/administrație.
     */
    private function isHomeroomTeacherOfClass(User $user, HomeworkAssignment $homework): bool
    {
        if ($homework->section === null) {
            return false;
        }

        $classIds = $user->contextHomeroomClassIds();

        if ($classIds === []) {
            return false;
        }

        return SchoolClass::query()
            ->whereKey($classIds)
            ->where('grade_level', (int) $homework->grade_level)
            ->where('section', $homework->section)
            ->exists();
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
