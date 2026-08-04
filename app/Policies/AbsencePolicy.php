<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

/**
 * Diviziunea muncii pe absențe (cerința beneficiarului, 04.08.2026): profesorul lecției doar
 * CONSEMNEAZĂ („Absent" — el rareori știe de ce lipsește elevul), iar DIRIGINTELE fixează statutul
 * (motivată/nemotivată) și corectează/retrage consemnările clasei lui. Autoritatea academică le
 * administrează pe toate.
 *
 * De aceea profesorului i-a RĂMAS doar create + view: editarea sau ștergerea unei absențe — chiar
 * a lui, chiar fără statut — e act de gestiune a clasei, nu de consemnare. Greșeala unui profesor
 * o îndreaptă dirigintele (care oricum validează statutul în aceeași zi). Aceeași linie ca la
 * motivare: `canMotivateAbsencesFor` = dirigintele clasei (în context de diriginte, F3) sau
 * administrația academică.
 */
class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, Absence $absence): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return $user->teacher !== null || $user->canAdministerCatalog();
    }

    /** Editarea (inclusiv statutul) = dirigintele CLASEI absenței sau administrația academică. */
    public function update(User $user, Absence $absence): bool
    {
        return $user->canMotivateAbsencesFor((int) $absence->school_class_id);
    }

    /**
     * Retragerea (ștergerea soft) — aceeași autoritate ca editarea: dirigintele clasei sau
     * administrația. Profesorul NU-și mai retrage nici propriile consemnări (le semnalează
     * dirigintelui); ștergerea PERMANENTĂ și restaurarea din coș rămân la autoritatea academică —
     * date de catalog nu se scot definitiv de la nivel de clasă (audit staff, finding CRITIC #2).
     */
    public function delete(User $user, Absence $absence): bool
    {
        return $user->canMotivateAbsencesFor((int) $absence->school_class_id);
    }

    /** Poarta generală a acțiunilor de ștergere: dirigintele (are clase în context) sau administrația. */
    public function deleteAny(User $user): bool
    {
        return $user->canAdministerCatalog() || $user->contextHomeroomClassIds() !== [];
    }

    public function restore(User $user, Absence $absence): bool
    {
        return $user->canAdministerCatalog();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDelete(User $user, Absence $absence): bool
    {
        return $user->canAdministerCatalog();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canAdministerCatalog();
    }
}
