<?php

namespace App\Policies;

use App\Models\ExamCommission;
use App\Models\User;

/**
 * Comisiile de examen = COMPONENȚA nominală desemnată prin ordin (§2.5). Ca și sesiunea, e act de
 * configurare: se stabilește înainte de examen și nu atinge note.
 *
 * Fără policy, Filament autoriza implicit orice rol pe editare/ștergere (vezi {@see CorigentaSessionPolicy}).
 */
class ExamCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }

    public function view(User $user, ExamCommission $commission): bool
    {
        return $user->canManageCorigenta();
    }

    public function create(User $user): bool
    {
        return $user->canManageCorigenta();
    }

    public function update(User $user, ExamCommission $commission): bool
    {
        return $user->canManageCorigenta();
    }

    public function delete(User $user, ExamCommission $commission): bool
    {
        return $user->canManageCorigenta();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }
}
