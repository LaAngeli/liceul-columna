<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\TermAverage;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/** Clasele: consultate de personalul academic (scoped în query), configurate de administrație. */
class SchoolClassPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return $user->canSeeAcademicData();
    }

    /** ForceDelete pe o clasă ar distruge prin cascada FK notele/absențele/mediile/înmatriculările ei. */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return Grade::withTrashed()->where('school_class_id', $record->getKey())->exists()
            || Absence::withTrashed()->where('school_class_id', $record->getKey())->exists()
            || TermAverage::withTrashed()->where('school_class_id', $record->getKey())->exists()
            || Enrollment::withTrashed()->where('school_class_id', $record->getKey())->exists();
    }
}
