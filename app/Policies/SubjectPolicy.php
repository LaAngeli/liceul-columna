<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TermAverage;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/** Nomenclator consultat de tot personalul academic, configurat de administrația școlii. */
class SubjectPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->canSeeAcademicData();
    }

    /** ForceDelete pe o disciplină ar distruge prin cascada FK notele/absențele/mediile/matricola ei. */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return Grade::withTrashed()->where('subject_id', $record->getKey())->exists()
            || Absence::withTrashed()->where('subject_id', $record->getKey())->exists()
            || TermAverage::withTrashed()->where('subject_id', $record->getKey())->exists()
            || AcademicRecord::withTrashed()->where('subject_id', $record->getKey())->exists();
    }
}
