<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\User;

/**
 * Jurnalul de audit: STRICT read-only, pentru oricine (spec §7 / L133). Vizualizarea urmează
 * capabilitatea `canViewAuditLog` (matricea §3.3); scrierea/ștergerea sunt refuzate TUTUROR —
 * inclusiv super-adminului — în oglindă cu garda de imuabilitate de pe model ({@see Audit::booted}).
 * Retenția legală (12 ani) se execută doar din consolă, prin query builder, în afara politicii.
 */
class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewAuditLog();
    }

    public function view(User $user, Audit $audit): bool
    {
        return $user->canViewAuditLog();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Audit $audit): bool
    {
        return false;
    }

    public function delete(User $user, Audit $audit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Audit $audit): bool
    {
        return false;
    }

    public function restore(User $user, Audit $audit): bool
    {
        return false;
    }
}
