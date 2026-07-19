<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\CorigentaSession;
use App\Models\Enrollment;
use App\Models\ExamCommission;
use App\Models\Lesson;
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
     *
     * Lista acoperă toate cele 6 tabele care cascadează pe `academic_year_id` în schema reală.
     * `lessons` ar fi acoperit și tranzitiv (o lecție cere o clasă, clasa cere anul), dar îl
     * verificăm explicit — un raționament tranzitiv nu ține loc de gard. `withTrashed()` doar pe
     * modelele cu SoftDeletes; sesiunile și comisiile nu au, apelul ar arunca.
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        $yearId = $record->getKey();

        return Term::withTrashed()->where('academic_year_id', $yearId)->exists()
            || SchoolClass::withTrashed()->where('academic_year_id', $yearId)->exists()
            || Enrollment::withTrashed()->where('academic_year_id', $yearId)->exists()
            || Lesson::withTrashed()->where('academic_year_id', $yearId)->exists()
            || CorigentaSession::query()->where('academic_year_id', $yearId)->exists()
            || ExamCommission::query()->where('academic_year_id', $yearId)->exists();
    }
}
