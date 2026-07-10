<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

/**
 * Absențele se consemnează de profesorul lecției sau de dirigintele clasei (scoping în
 * `Teacher::canRecordAbsence`), iar autoritatea academică le administrează pe toate.
 *
 * Ștergerea SOFT = „retragerea" unei absențe consemnate greșit; profesorul o poate face doar pe
 * ale LUI. Ștergerea PERMANENTĂ și restaurarea din coș rămân la autoritatea academică — profesorul
 * nu scoate definitiv date de catalog (audit staff, finding CRITIC #2).
 */
class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, Absence $absence): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return $user->teacher !== null || $user->canAdministerCatalog();
    }

    public function update(User $user, Absence $absence): bool
    {
        if ($user->canAdministerCatalog()) {
            return true;
        }

        $teacher = $user->teacher;

        return $teacher !== null && $teacher->canRecordAbsence(
            (int) $absence->school_class_id,
            $absence->subject_id !== null ? (int) $absence->subject_id : null,
        );
    }

    /**
     * Retragerea (ștergerea soft) unei absențe. Administrația poate oricare. Profesorul poate doar
     * în scope-ul lui de consemnare ȘI doar dacă absența e a lui sau nu are autor — nu retrage
     * consemnarea explicită a unui COLEG de la aceeași clasă.
     *
     * Absențele importate din sistemul vechi nu au autor (`teacher_id` null); fără ramura
     * „fără autor", retragerea lor ar fi imposibilă pentru cine le gestionează zilnic.
     */
    public function delete(User $user, Absence $absence): bool
    {
        if ($user->canAdministerCatalog()) {
            return true;
        }

        $teacher = $user->teacher;

        if ($teacher === null || ! $this->update($user, $absence)) {
            return false;
        }

        return $absence->teacher_id === null || (int) $absence->teacher_id === (int) $teacher->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->canAdministerCatalog() || $user->teacher !== null;
    }

    public function restore(User $user, Absence $absence): bool
    {
        return $user->canAdministerCatalog();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDelete(User $user, Absence $absence): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }
}
