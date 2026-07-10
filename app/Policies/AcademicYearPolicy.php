<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

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
}
