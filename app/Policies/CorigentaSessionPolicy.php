<?php

namespace App\Policies;

use App\Models\CorigentaSession;
use App\Models\User;

/**
 * Sesiunile de corigență = CALENDARUL examenelor de lichidare, deschis prin ordin al directorului
 * (§2.5). E configurare, nu conținut pedagogic — deci intră în perimetrul celor care administrează
 * corigența (conducere + responsabilul domeniului „Instruire"), inclusiv administratorul
 * operațional, care programează fără a nota.
 *
 * Fără policy, Filament v4 (mod ne-strict) autorizează IMPLICIT: `canEdit`/`canDelete` cădeau pe
 * „permis" pentru ORICE rol — inclusiv părinte — pe orice cale care nu trece prin `canAccess()`
 * al resursei (API viitor, acțiune montată, RelationManager). Vezi {@see HolidayPolicy}.
 */
class CorigentaSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }

    public function view(User $user, CorigentaSession $session): bool
    {
        return $user->canManageCorigenta();
    }

    public function create(User $user): bool
    {
        return $user->canManageCorigenta();
    }

    public function update(User $user, CorigentaSession $session): bool
    {
        return $user->canManageCorigenta();
    }

    public function delete(User $user, CorigentaSession $session): bool
    {
        return $user->canManageCorigenta();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }
}
