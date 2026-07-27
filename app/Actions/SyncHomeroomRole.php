<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;

/**
 * Rolul „Diriginte" DERIVAT din desemnare, nu ales manual (decizie 2026-07-27).
 *
 * De ce: rolul nu dădea niciun drept — toate drepturile de dirigenție vin din
 * `school_classes.homeroom_teacher_id`. Rămăsese o COPIE MANUALĂ a unei realități care se schimbă
 * fără el (o clasă reatribuită nu atingea rolul), deci eticheta putea minți: pe date reale erau
 * 23 de conturi cu rolul „Diriginte" față de 21 de fișe cu dirigenție și cont. Acum eticheta nu
 * mai e o afirmație independentă, ci o consecință — nu mai are cum să se desincronizeze.
 *
 * LIMITA DELIBERATĂ: se ating DOAR conturile al căror rol curent e `profesor` sau `diriginte`.
 * Un director sau un administrator care primește o clasă în dirigenție ÎȘI PĂSTREAZĂ rolul —
 * dirigenția e o funcție în plus, nu o retrogradare; drepturile ei le are oricum din desemnare.
 * Conturile fără rol, suspendate sau cu rol de familie nu sunt niciodată atinse.
 */
class SyncHomeroomRole
{
    /**
     * Aduce rolul contului în acord cu desemnarea. Întoarce rolul NOU dacă s-a schimbat ceva,
     * null dacă nu era nimic de făcut (contul e deja corect sau nu intră sub incidența regulii).
     */
    public function forTeacher(?Teacher $teacher): ?UserRole
    {
        if ($teacher === null) {
            return null;
        }

        return $this->forUser($teacher->user);
    }

    public function forUser(?User $user): ?UserRole
    {
        if ($user === null) {
            return null;
        }

        $current = $user->getRoleNames()->first();

        // Doar corpul didactic de bază intră sub regulă (vezi limita din docblock).
        if (! in_array($current, [UserRole::Profesor->value, UserRole::Diriginte->value], true)) {
            return null;
        }

        $expected = $this->expectedRoleFor($user);

        if ($current === $expected->value) {
            return null;
        }

        $user->syncRoles([$expected->value]);

        return $expected;
    }

    /** Rolul pe care desemnarea îl impune: dirigenție ⇒ Diriginte, altfel Profesor. */
    public function expectedRoleFor(User $user): UserRole
    {
        return $user->teacher?->homeroomClasses()->exists() === true
            ? UserRole::Diriginte
            : UserRole::Profesor;
    }

    /**
     * Conturile a căror etichetă nu mai corespunde desemnării — sursa raportului comenzii de
     * sincronizare și a verificării „ce s-ar schimba" înainte de a schimba ceva.
     *
     * @return list<array{user: User, from: string, to: UserRole}>
     */
    public function drifted(): array
    {
        $users = User::query()
            ->role([UserRole::Profesor->value, UserRole::Diriginte->value])
            ->with('teacher.homeroomClasses')
            ->get();

        $drifted = [];

        foreach ($users as $user) {
            $current = (string) $user->getRoleNames()->first();
            $expected = $this->expectedRoleFor($user);

            if ($current !== $expected->value) {
                $drifted[] = ['user' => $user, 'from' => $current, 'to' => $expected];
            }
        }

        return $drifted;
    }
}
