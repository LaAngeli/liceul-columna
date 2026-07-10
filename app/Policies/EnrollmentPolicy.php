<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

/** Înmatriculările (elev × clasă × an): alocarea e atribuția configuratorilor (§3.2). */
class EnrollmentPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdministrator();
    }
}
