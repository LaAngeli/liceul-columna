<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

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
}
