<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Policies\GradePolicy;
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

    /**
     * Ștergerea PERMANENTĂ e permisă doar pe rânduri FĂRĂ istoric academic dependent: FK-urile
     * din `grades`/`absences`/... sunt `cascadeOnDelete`, deci ForceDelete pe un nomenclator cu
     * date ar distruge definitiv note/absențe/medii — încălcând §1 („notele nu se șterg
     * NICIODATĂ"; {@see GradePolicy} neagă forceDelete chiar și super-adminului).
     * Rândurile create din greșeală (fără date) rămân curățabile de configuratori.
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return $user->canConfigureSchool() && ! $this->hasDependentAcademicHistory($record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canConfigureSchool();
    }

    /**
     * Hook per policy: rândul are istoric academic (note/absențe/medii/matricolă/înmatriculări)
     * pe care cascada FK l-ar distruge la ForceDelete. Include rândurile soft-deleted — și ele
     * ar fi distruse de cascadă.
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return false;
    }
}
