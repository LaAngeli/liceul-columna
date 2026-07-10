<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;

class StudentPolicy
{
    // Fișa de elev se creează/editează/șterge doar de configuratorii școlii (§3.3).
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    /**
     * Cine poate vedea profilul unui elev:
     * - personalul academic (tot personalul cu acces la panou, mai puțin administratorul tehnic,
     *   care nu consultă date academice în uz normal — §3.2); scoping-ul fin se adaugă ulterior;
     * - elevul însuși (contul legat);
     * - un părinte/tutore atribuit elevului.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(UserRole::panelRoleValues()) && ! $user->isTechnicalAdmin()) {
            return true;
        }

        if ($student->user_id === $user->id) {
            return true;
        }

        return $user->students()->whereKey($student->getKey())->exists();
    }
}
