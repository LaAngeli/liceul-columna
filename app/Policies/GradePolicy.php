<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

/**
 * Notele NU se șterg niciodată (§1) — se anulează cu motiv, rămânând în istoric. Valoarea unei
 * note se schimbă DOAR prin cerere de corecție aprobată de administrație (§3.1): de aceea
 * `update` e rezervat autorității academice, iar profesorul introduce note noi, cere corecții
 * și anulează.
 */
class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, Grade $grade): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return $user->teacher !== null || $user->canAdministerCatalog();
    }

    /** Editarea directă a notei = cale excepțională a autorității academice. */
    public function update(User $user, Grade $grade): bool
    {
        return $user->canAdministerCatalog();
    }

    public function delete(User $user, Grade $grade): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Grade $grade): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Grade $grade): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
