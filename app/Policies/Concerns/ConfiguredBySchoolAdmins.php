<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Scrierea în nomenclatoare și configurarea școlii (elevi, discipline, clase, profesori, ani
 * școlari, semestre, înmatriculări) = doar cei cu drept de configurare: super-admin, director,
 * administrator operațional (§3.3). Vizualizarea se definește în fiecare policy în parte.
 *
 * Filament v4 consultă policy-ul pentru vizibilitatea acțiunilor (`getUpdateAuthorizationResponse`
 * → Gate), NU overrides-urile statice `Resource::canEdit()` — care gate-uiesc doar PAGINA. Fără
 * aceste metode, butoanele apar și duc în 403 (audit staff, finding sistemic #5).
 */
trait ConfiguredBySchoolAdmins
{
    public function create(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->canConfigureSchool();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->canConfigureSchool();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->canConfigureSchool();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->canConfigureSchool();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }
}
