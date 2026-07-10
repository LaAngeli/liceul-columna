<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

/** Fișele de profesor: vizibile doar administrației academice, configurate de administrația școlii. */
class TeacherPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->isAdministrator();
    }
}
