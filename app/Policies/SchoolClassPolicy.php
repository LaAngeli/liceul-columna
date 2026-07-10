<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

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
}
