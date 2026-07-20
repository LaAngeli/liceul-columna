<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

/**
 * Zilele libere guvernează termene LEGALE (motivarea absențelor, §2.1) — scrierea lor e atribuția
 * administratorului operațional (canManageSchedules = AO + super-admin break-glass, §3.2 „publică
 * orarul"), ca și resursa Filament. Fără policy, orice cale viitoare de scriere (API mobil, action)
 * rămânea complet negardată — iar Filament v4 autorizează acțiunile prin Gate, nu prin resursă.
 * CITIREA urmează resursa (canViewSchedules): conducerea/dirigintele/profesorul VĂD planificatorul.
 */
class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewSchedules();
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $user->canViewSchedules();
    }

    public function create(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->canManageSchedules();
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->canManageSchedules();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageSchedules();
    }
}
