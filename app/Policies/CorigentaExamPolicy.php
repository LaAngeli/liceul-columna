<?php

namespace App\Policies;

use App\Filament\Resources\CorigentaExams\CorigentaExamResource;
use App\Filament\Resources\CorigentaExams\Schemas\CorigentaExamForm;
use App\Models\CorigentaExam;
use App\Models\User;

/**
 * Examenele de corigență: rândurile se GENEREAZĂ (la validarea statutului „corigent"), nu se
 * creează manual — de aceea `create` e refuzat tuturor, în oglindă cu
 * {@see CorigentaExamResource::canCreate()}.
 *
 * Separarea atribuțiilor (§3.2/§3.3): PROGRAMAREA (sesiune, comisie, dată) e configurare — o poate
 * face și administratorul operațional; CONSEMNAREA NOTEI e act de autoritate academică și îi este
 * expres interzisă AO („Nu introduce/editează note"). Nota se gardează la nivel de CÂMP, în
 * {@see CorigentaExamForm}, fiindcă e o restricție
 * pe un atribut, nu pe întregul rând.
 *
 * Ștergerea urmează regula deja consacrată în RoleInteractionCoherenceTest: examenul NEDAT rămâne
 * curățabil, cel DAT e istoric de examen și nu se mai șterge din UI.
 */
class CorigentaExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }

    public function view(User $user, CorigentaExam $exam): bool
    {
        return $user->canManageCorigenta();
    }

    /** Rândurile vin din GenerateCorigentaExams — niciodată din formular. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CorigentaExam $exam): bool
    {
        return $user->canManageCorigenta();
    }

    /** Examenul cu notă consemnată = istoric; se șterge doar cât timp e încă neefectuat. */
    public function delete(User $user, CorigentaExam $exam): bool
    {
        return $user->canManageCorigenta() && $exam->mark === null;
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCorigenta();
    }
}
