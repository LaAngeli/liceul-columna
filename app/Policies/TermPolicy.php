<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

/** Semestrele: structura anului — vizibile administrației academice, scrise de configuratori. */
class TermPolicy
{
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Term $term): bool
    {
        return $user->isAdministrator();
    }
}
