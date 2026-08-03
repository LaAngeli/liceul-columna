<?php

namespace App\Policies;

use App\Models\SummativeDesignation;
use App\Models\User;

/**
 * Desemnările de sumativă (disciplină × clasă): VĂZUTE de toată administrația academică, SCRISE
 * doar de cine administrează catalogul — super-admin, director, prim-vicedirector (§3.3). E o
 * decizie pedagogică, nu de configurare: sumativa intră cu 50% în media semestrială, deci schimbă
 * notele. De-aceea administratorul operațional o CONSULTĂ, dar nu o modifică.
 *
 * ⚠️ Fără această policy, `Resource::canCreate()` NU ajungea la butoane: Filament îl consultă
 * într-un singur loc — `CreateRecord::authorizeAccess()`, adică abia la deschiderea paginii (403).
 * Vizibilitatea acțiunilor trece prin Gate, iar un model FĂRĂ policy e implicit permis, așa că
 * administratorul operațional vedea „Adăugare" și primea 403 la click (raport beneficiar
 * 2026-08-03). Policy-ul face din capabilitate sursa unică: aceleași reguli ascund butonul ȘI
 * apără pagina.
 */
class SummativeDesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, SummativeDesignation $designation): bool
    {
        return $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->canAdministerCatalog();
    }

    public function update(User $user, SummativeDesignation $designation): bool
    {
        return $user->canAdministerCatalog();
    }

    public function delete(User $user, SummativeDesignation $designation): bool
    {
        return $user->canAdministerCatalog();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }
}
