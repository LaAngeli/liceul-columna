<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Orarele publicabile: VĂZUTE de toți cei cărora §3.3 le dă dreptul (conducere, diriginte,
 * profesor), SCRISE doar de administratorul operațional (§3.2 „publică orarul") + super-admin.
 *
 * Modelul are SoftDeletes, iar fără policy `restore`/`forceDelete` cădeau pe „permis" pentru orice
 * rol — Filament v4 în mod ne-strict autorizează implicit ce nu e refuzat explicit.
 */
class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewSchedules();
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $user->canViewSchedules();
    }

    public function create(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->canManageSchedules();
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->canManageSchedules();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function restore(User $user, Schedule $schedule): bool
    {
        return $user->canManageSchedules();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canManageSchedules();
    }

    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return $user->canManageSchedules();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canManageSchedules();
    }
}
