<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;

/**
 * MEMBRIA rolului „Diriginte", derivată din desemnare (reconvertit pentru multi-rol, 30.07.2026).
 *
 * Era: „rolul derivat" ÎNLOCUIA eticheta (profesor↔diriginte) — corect în lumea un-cont-un-rol
 * (ce6f6cb), dar DISTRUCTIV sub multi-rol: crearea unei clase cu diriginte îi ștergea persoanei
 * rolul Profesor (prins de ContextSeparationTest la construirea F3). Acum: dirigenția ADAUGĂ
 * rolul Diriginte pe lângă ce există; pierderea ultimei clase îl RETRAGE. Principiul rămâne
 * neschimbat: eticheta nu poate minți, fiindcă nu e o afirmație independentă, ci o consecință —
 * iar comutatorul de context arată doar roluri pe care persoana chiar le are.
 *
 * LIMITA DELIBERATĂ: se ating DOAR conturile al căror set de roluri ⊆ {profesor, diriginte}.
 * Conducerea care primește o clasă își PĂSTREAZĂ rolurile — dirigenția e o funcție în plus, nu o
 * retrogradare; puterile ei de context vin din desemnare ({@see User::contextHomeroomClassIds}).
 * Familia și conturile fără rol nu sunt niciodată atinse.
 */
class SyncHomeroomRole
{
    /** Descrierea schimbării aplicate, pentru rapoartele comenzii. */
    public const ADDED = 'diriginte-adaugat';

    public const REMOVED = 'diriginte-retras';

    public const SWAPPED = 'revenit-profesor';

    public function forTeacher(?Teacher $teacher): ?string
    {
        if ($teacher === null) {
            return null;
        }

        return $this->forUser($teacher->user);
    }

    /**
     * Aduce MEMBRIA rolului Diriginte în acord cu desemnarea. Întoarce descrierea schimbării
     * (constantele de mai sus) sau null dacă nu era nimic de făcut / contul nu intră sub regulă.
     */
    public function forUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $roles = $user->getRoleNames()->all();

        if ($roles === []) {
            return null;
        }

        // Doar corpul didactic de bază (vezi limita din docblock).
        if (array_diff($roles, [UserRole::Profesor->value, UserRole::Diriginte->value]) !== []) {
            return null;
        }

        $hasHomeroom = $user->teacher?->homeroomClasses()->exists() === true;
        $isDiriginte = in_array(UserRole::Diriginte->value, $roles, true);

        if ($hasHomeroom && ! $isDiriginte) {
            $user->assignRole(UserRole::Diriginte->value);

            return self::ADDED;
        }

        if (! $hasHomeroom && $isDiriginte) {
            $user->removeRole(UserRole::Diriginte->value);

            // Contul nu are voie să rămână fără niciun rol: mono-dirigintele care pierde ultima
            // clasă revine „Profesor" — exact semantica dinaintea reconversiei.
            if ($user->getRoleNames()->isEmpty()) {
                $user->assignRole(UserRole::Profesor->value);

                return self::SWAPPED;
            }

            return self::REMOVED;
        }

        return null;
    }

    /**
     * Conturile a căror membrie nu mai corespunde desemnării — raportul comenzii de sincronizare.
     *
     * @return list<array{user: User, action: string}>
     */
    public function drifted(): array
    {
        $users = User::query()
            ->role([UserRole::Profesor->value, UserRole::Diriginte->value])
            ->with('teacher.homeroomClasses')
            ->get();

        $drifted = [];

        foreach ($users as $user) {
            $roles = $user->getRoleNames()->all();

            if (array_diff($roles, [UserRole::Profesor->value, UserRole::Diriginte->value]) !== []) {
                continue;
            }

            $hasHomeroom = $user->teacher?->homeroomClasses()->exists() === true;
            $isDiriginte = in_array(UserRole::Diriginte->value, $roles, true);

            if ($hasHomeroom && ! $isDiriginte) {
                $drifted[] = ['user' => $user, 'action' => self::ADDED];
            } elseif (! $hasHomeroom && $isDiriginte) {
                $drifted[] = [
                    'user' => $user,
                    'action' => count($roles) === 1 ? self::SWAPPED : self::REMOVED,
                ];
            }
        }

        return $drifted;
    }
}
