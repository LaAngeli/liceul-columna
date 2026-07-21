<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

/**
 * Înmatriculările (elev × clasă × an): alocarea e atribuția configuratorilor (§3.2).
 *
 * Peste dreptul de configurare, gărzile de REGISTRU (dublate la nivel de model —
 * {@see Enrollment::booted}): rândul unui an în care elevul are istoric academic nu se șterge
 * (nici soft, nici definitiv) — plecarea se marchează cu `left_on`, nu prin ștergere.
 */
class EnrollmentPolicy
{
    use ConfiguredBySchoolAdmins {
        delete as private configuratorDelete;
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Enrollment $record */
        return $this->configuratorDelete($user, $record) && ! $record->hasAcademicHistory();
    }

    /**
     * ForceDelete: aceeași gardă — hook-ul trait-ului o aplică peste dreptul de configurare.
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        /** @var Enrollment $record */
        return $record->hasAcademicHistory();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdministrator();
    }
}
