<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/**
 * Semestrele: structura anului — vizibile administrației academice, scrise de configuratori.
 *
 * Peste dreptul de configurare se aplică gărzile de STARE (dublate la nivel de model, ca nicio
 * cale de scriere să nu le ocolească — {@see Term::booted}):
 *  - anul ÎNCHIS îngheață structura (nici editare, nici ștergere, nici restaurare): mutarea
 *    granițelor ar declanșa realinierea notelor unui catalog arhivat;
 *  - semestrul CURENT și cel cu ISTORIC academic nu se șterg (nici măcar soft): ar lăsa
 *    derivarea semestrului fără reper, respectiv catalogul legat de un rând invizibil.
 */
class TermPolicy
{
    use ConfiguredBySchoolAdmins {
        update as private configuratorUpdate;
        delete as private configuratorDelete;
        restore as private configuratorRestore;
        forceDelete as private configuratorForceDelete;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Term $term): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Model $record): bool
    {
        return $this->configuratorUpdate($user, $record) && ! $this->yearIsClosed($record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Term $record */
        return $this->configuratorDelete($user, $record)
            && ! $record->is_current
            && ! $this->yearIsClosed($record)
            && ! $record->hasAcademicHistory();
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->configuratorRestore($user, $record) && ! $this->yearIsClosed($record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        /** @var Term $record */
        return $this->configuratorForceDelete($user, $record)
            && ! $record->is_current
            && ! $this->yearIsClosed($record);
    }

    /**
     * ForceDelete pe un semestru ar distruge prin cascada FK toate notele/absențele/mediile lui.
     * Sursa listei de dependențe: {@see Term::hasAcademicHistory} (comună cu garda de model și
     * cu semnalele paginii Semestre).
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        /** @var Term $record */
        return $record->hasAcademicHistory();
    }

    private function yearIsClosed(Model $record): bool
    {
        /** @var Term $record */
        return $record->academicYear()->withTrashed()->first()?->isClosed() ?? false;
    }
}
