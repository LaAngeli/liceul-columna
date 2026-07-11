<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\Grade;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/** Semestrele: structura anului — vizibile administrației academice, scrise de configuratori. */
class TermPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Term $term): bool
    {
        return $user->isAdministrator();
    }

    /** ForceDelete pe un semestru ar distruge prin cascada FK toate notele/absențele/mediile lui. */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return Grade::withTrashed()->where('term_id', $record->getKey())->exists()
            || Absence::withTrashed()->where('term_id', $record->getKey())->exists()
            || TermAverage::withTrashed()->where('term_id', $record->getKey())->exists();
    }
}
