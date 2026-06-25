<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Cine poate vedea profilul unui elev:
     * - personalul școlii (admin/director/director-adjunct/diriginte/profesor) — deocamdată toți;
     *   scoping-ul fin (profesorul doar clasele lui) se adaugă ulterior;
     * - elevul însuși (contul legat);
     * - un părinte/tutore atribuit elevului.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->hasAnyRole(UserRole::panelRoleValues())) {
            return true;
        }

        if ($student->user_id === $user->id) {
            return true;
        }

        return $user->students()->whereKey($student->getKey())->exists();
    }
}
