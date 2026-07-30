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

    public const TEACHER_ADDED = 'profesor-adaugat';

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
     * MEMBRIA rolului PROFESOR, derivată din alocările de predare (cumul, decizia beneficiarului
     * 2026-07-31 — „varianta A"). Simetrică cu dirigenția, dar ASIMETRICĂ la retragere:
     *
     * - se ADAUGĂ contului care predă efectiv măcar o (clasă, disciplină) și nu poartă rolul;
     * - NU se retrage niciodată automat. „Profesor" e statutul de BAZĂ al corpului didactic; un
     *   cadru rămas temporar fără alocări (între ani, în concediu) e tot profesor. Dirigenția e o
     *   funcție ATRIBUITĂ pe o clasă anume, deci se retrage când clasa dispare — de aceea acolo
     *   regula e simetrică, iar aici nu.
     *
     * Efectul pe conturile moștenite (importate ca `func=3` → „diriginte" mono-rol, deși predau):
     * devin {Diriginte, Profesor} → comutatorul de context li se deschide, iar vederea fuzionată
     * se desparte în „ce predau" / „clasa mea". Aterizarea implicită o setează comanda de cumul.
     */
    public function grantTeacherMembership(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $roles = $user->getRoleNames()->all();

        if ($roles === [] || array_diff($roles, [UserRole::Profesor->value, UserRole::Diriginte->value]) !== []) {
            return null;
        }

        if (in_array(UserRole::Profesor->value, $roles, true)) {
            return null;
        }

        if ($user->teacher?->teachingAssignments()->exists() !== true) {
            return null;
        }

        $user->assignRole(UserRole::Profesor->value);

        return self::TEACHER_ADDED;
    }

    /**
     * Conturile care PREDAU dar nu poartă rolul Profesor — candidații cumul-ului.
     *
     * @return list<User>
     */
    public function missingTeacherMembership(): array
    {
        $users = User::query()
            ->role([UserRole::Profesor->value, UserRole::Diriginte->value])
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', UserRole::Profesor->value))
            ->whereHas('teacher.teachingAssignments')
            ->with('teacher')
            ->get();

        /** @var list<User> $candidates */
        $candidates = [];

        foreach ($users as $user) {
            // Conducerea/familia nu intră sub regulă nici dacă poartă și un rol pedagogic.
            if (array_diff($user->getRoleNames()->all(), [UserRole::Profesor->value, UserRole::Diriginte->value]) !== []) {
                continue;
            }

            $candidates[] = $user;
        }

        return $candidates;
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
