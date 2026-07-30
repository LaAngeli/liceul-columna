<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * ROLUL ACTIV al unei sesiuni de panou — miezul comutării de context multi-rol (F1, decizia
 * beneficiarului din 30.07.2026 pe raport-multirole-context-audit.md).
 *
 * Modelul: un cont poate purta mai multe roluri de STAFF, dar la un moment dat lucrează sub UNUL
 * singur. Fațada de capabilități ({@see User}) răspunde după rolul activ; accesul la aplicație și
 * notificările rămân pe reuniunea rolurilor. Comutarea există EXCLUSIV în panoul staff — cabinetul
 * familiei nu are și nu va avea switch (părinte↔părinte sau părinte↔elev), decizie explicită.
 *
 * Rezolvarea, în ordine:
 *   1. cont mono-rol → rolul lui, întotdeauna (contractul F0: nimic nu se schimbă; nici sesiunea
 *      nu e consultată — comportament identic bit cu bit, inclusiv în consolă/queue);
 *   2. sesiunea — DOAR pentru utilizatorul autentificat al cererii curente: rolul activ e al
 *      sesiunii LUI; când administrația inspectează contul altcuiva, alegerea ei de context nu
 *      are voie să se scurgă peste contul inspectat;
 *   3. `users.preferred_role` — ultima alegere persistată (default-ul de la login);
 *   4. rolul cu privilegiul cel mai înalt (ordinea din {@see UserRole} e ordinea de prioritate) —
 *      predictibil pentru conducere și singurul răspuns posibil în consolă/queue, unde nu există
 *      sesiune.
 */
class ActiveRole
{
    public const SESSION_KEY = 'staff_active_role';

    public static function resolve(User $user): ?UserRole
    {
        $roles = $user->getRoleNames()->all();

        if ($roles === []) {
            return null;
        }

        if (count($roles) === 1) {
            return UserRole::tryFrom((string) $roles[0]);
        }

        $switchable = self::switchableValues($roles);

        $fromSession = self::sessionValueFor($user);

        if ($fromSession !== null && in_array($fromSession, $switchable, true)) {
            return UserRole::tryFrom($fromSession);
        }

        $preferred = $user->preferred_role;

        if (is_string($preferred) && in_array($preferred, $switchable, true)) {
            return UserRole::tryFrom($preferred);
        }

        foreach (UserRole::cases() as $case) {
            if (in_array($case->value, $roles, true)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Activează un rol pentru utilizatorul AUTENTIFICAT: sesiune (contextul de acum) + preferință
     * persistată (default-ul de mâine). Refuză tăcut ce nu-i aparține sau nu e rol de panou —
     * apelantul (controllerul comutatorului) validează și el, cu mesaj.
     */
    public static function activate(User $user, UserRole $role): bool
    {
        if (! in_array($role->value, self::switchableValues($user->getRoleNames()->all()), true)) {
            return false;
        }

        session()->put(self::SESSION_KEY, $role->value);

        if ($user->preferred_role !== $role->value) {
            $user->forceFill(['preferred_role' => $role->value])->save();
        }

        return true;
    }

    /**
     * Rolurile între care contul poate comuta: ale lui ∩ rolurile de PANOU. Familia nu intră
     * niciodată în comutator (decizia beneficiarului) — de altfel F4 interzice cumulul.
     *
     * @param  array<int, mixed>  $roles
     * @return list<string>
     */
    public static function switchableValues(array $roles): array
    {
        $panel = UserRole::panelRoleValues();
        $values = [];

        // Ordinea enum-ului = ordinea de afișare în comutator (prioritate descrescătoare).
        foreach (UserRole::cases() as $case) {
            if (in_array($case->value, $panel, true) && in_array($case->value, $roles, true)) {
                $values[] = $case->value;
            }
        }

        return $values;
    }

    /**
     * Valoarea din sesiune, DOAR dacă acest user e utilizatorul autentificat al cererii curente
     * și cererea chiar are sesiune (în consolă/queue nu există — se cade pe preferință/prioritate).
     */
    private static function sessionValueFor(User $user): ?string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        if (! app()->bound('session.store')) {
            return null;
        }

        if (auth('web')->id() !== $user->getKey()) {
            return null;
        }

        $value = session()->get(self::SESSION_KEY);

        return is_string($value) ? $value : null;
    }
}
