<?php

namespace App\Observers;

use App\Actions\SyncHomeroomRole;
use App\Models\TeachingAssignment;

/**
 * MEMBRIA rolului Profesor urmează alocările de predare (cumul, 2026-07-31).
 *
 * Perechea observerului de pe clase (care gestionează membria Diriginte): fără el, cumul-ul ar fi
 * o reparație unică, iar orice cont creat mâine DOAR ca „diriginte" și înzestrat cu alocări ar
 * rămâne mono-rol, fără comutator — aceeași problemă, peste o lună.
 *
 * ASIMETRIC deliberat: se adaugă la primirea alocărilor, NU se retrage la pierderea lor
 * ({@see SyncHomeroomRole::grantTeacherMembership} pentru motivare).
 */
class TeachingAssignmentObserver
{
    public function __construct(private SyncHomeroomRole $syncHomeroomRole) {}

    public function created(TeachingAssignment $assignment): void
    {
        $this->grant($assignment);
    }

    public function restored(TeachingAssignment $assignment): void
    {
        $this->grant($assignment);
    }

    public function updated(TeachingAssignment $assignment): void
    {
        // Realocarea către alt profesor: cel care primește ora capătă membria.
        if ($assignment->wasChanged('teacher_id')) {
            $this->grant($assignment);
        }
    }

    private function grant(TeachingAssignment $assignment): void
    {
        $this->syncHomeroomRole->grantTeacherMembership($assignment->teacher?->user);
    }
}
