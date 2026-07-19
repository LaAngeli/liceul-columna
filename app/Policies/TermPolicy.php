<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\CorigentaExam;
use App\Models\Grade;
use App\Models\SemesterValidation;
use App\Models\StatusAcknowledgement;
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

    /**
     * ForceDelete pe un semestru ar distruge prin cascada FK toate notele/absențele/mediile lui.
     * Lista acoperă TOATE tabelele care cascadează pe `term_id` (derivate din schemă, nu din
     * memorie): cele trei de catalog + examenele de corigență, validările de semestru și
     * confirmările de statut. `withTrashed()` DOAR pe modelele cu SoftDeletes — pe celelalte ar
     * arunca BadMethodCallException.
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        $termId = $record->getKey();

        return Grade::withTrashed()->where('term_id', $termId)->exists()
            || Absence::withTrashed()->where('term_id', $termId)->exists()
            || TermAverage::withTrashed()->where('term_id', $termId)->exists()
            || CorigentaExam::query()->where('term_id', $termId)->exists()
            || SemesterValidation::query()->where('term_id', $termId)->exists()
            || StatusAcknowledgement::query()->where('term_id', $termId)->exists();
    }
}
