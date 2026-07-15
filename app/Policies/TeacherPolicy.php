<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

/** Fișele de profesor: vizibile doar administrației academice, configurate de administrația școlii. */
class TeacherPolicy
{
    use ConfiguredBySchoolAdmins;

    /**
     * Registrul e vizibil și cadrelor didactice — SCOPED la echipa claselor lor
     * (TeacherResource::getEloquentQuery); fișa individuală (view/edit) rămâne a administrației.
     */
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->isAdministrator();
    }
}
