<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/** Anii școlari: deschiderea/închiderea anului e atribuția configuratorilor (§3.2). */
class AcademicYearPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $user->isAdministrator();
    }

    /**
     * ForceDelete pe un an școlar ar distruge în lanț semestrele/clasele/înmatriculările lui —
     * și, prin ele, notele. Blocat cât timp anul are ORICE structură; curățarea se face de jos
     * în sus (întâi semestrele goale, apoi anul).
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return Term::withTrashed()->where('academic_year_id', $record->getKey())->exists()
            || SchoolClass::withTrashed()->where('academic_year_id', $record->getKey())->exists()
            || Enrollment::withTrashed()->where('academic_year_id', $record->getKey())->exists();
    }
}
