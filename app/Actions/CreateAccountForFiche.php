<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creează contul de autentificare AL UNEI FIȘE care există deja în registru (cerința
 * beneficiarului 2026-07-24).
 *
 * Cazul real: importul legacy a adus 555 de elevi și 56 de profesori ca FIȘE, dar nu toți au
 * primit cont (18 profesori și 3 elevi sunt încă fără). Până acum, singura opțiune din fișă era
 * „leagă un cont existent" — inutilă exact când n-ai ce lega, și periculoasă când lista nu era
 * filtrată. Aici contul se NAȘTE din fișă: identitatea vine din registru (nu se re-tastează, nu
 * se poate diverge), iar operatorul completează doar ce ține de acces.
 *
 * Tranzacție unică: contul, rolul și legătura cu fișa reușesc împreună sau deloc — altfel ar
 * rămâne un cont orfan, exact problema pe care o rezolvăm.
 */
class CreateAccountForFiche
{
    /**
     * @param  array{username: string, email?: string|null, password: string, role?: string|null, send_credentials?: bool}  $data
     */
    public function create(Teacher|Student $fiche, array $data): User
    {
        if ($fiche->user_id !== null) {
            throw ValidationException::withMessages([
                'username' => __('panel.forms.fiche_account.already_linked'),
            ]);
        }

        $role = $this->resolveRole($fiche, $data['role'] ?? null);

        $user = DB::transaction(function () use ($fiche, $data, $role): User {
            $user = User::query()->create([
                // Identitatea vine DIN FIȘĂ, nu din formular: fișa e sursa de adevăr a persoanei,
                // iar contul e doar cheia ei de acces.
                'name' => $fiche->full_name,
                'username' => $data['username'],
                'email' => filled($data['email'] ?? null) ? $data['email'] : null,
                'password' => $data['password'],
                'must_change_password' => true,
            ]);

            $user->syncRoles([$role->value]);

            // Doar o fișă încă LIBERĂ primește contul — dacă altcineva a legat-o între timp,
            // rândul nu se atinge și tranzacția cade cu mesaj, în loc să suprascrie tăcut.
            $linked = $fiche->newQuery()
                ->whereKey($fiche->getKey())
                ->whereNull('user_id')
                ->update(['user_id' => $user->getKey()]);

            if ($linked === 0) {
                throw ValidationException::withMessages([
                    'username' => __('panel.forms.fiche_account.already_linked'),
                ]);
            }

            return $user;
        });

        // Credențialele pleacă DUPĂ tranzacție: un rollback nu trebuie să lase e-mailuri trimise.
        if (($data['send_credentials'] ?? false) && filled($user->email)) {
            $user->notify(new TemporaryCredentials($data['password']));
        }

        return $user;
    }

    /**
     * Rolul: la elev e determinat de fișă (nu se alege); la personalul pedagogic operatorul alege
     * între profesor și diriginte, dar numai dintre rolurile pe care CHIAR le poate administra.
     */
    private function resolveRole(Teacher|Student $fiche, ?string $requested): UserRole
    {
        if ($fiche instanceof Student) {
            return UserRole::Elev;
        }

        // Lipsa rolului = implicit „profesor"; un rol PREZENT dar nepedagogic e o cerere invalidă
        // (POST fabricat) — se respinge, nu se „corectează" tăcut într-altul.
        $role = blank($requested) ? UserRole::Profesor : UserRole::tryFrom((string) $requested);

        if ($role === null || ! in_array($role, [UserRole::Profesor, UserRole::Diriginte], true)) {
            throw ValidationException::withMessages([
                'role' => __('panel.forms.fiche_account.role_not_allowed'),
            ]);
        }

        $actor = auth('web')->user();

        if ($actor instanceof User && ! in_array($role->value, $actor->manageableRoleValues(), true)) {
            throw ValidationException::withMessages([
                'role' => __('panel.forms.fiche_account.role_not_allowed'),
            ]);
        }

        return $role;
    }

    /**
     * Utilizator SUGERAT din numele fișei („iorga.alida"), garantat unic prin sufix numeric.
     * Doar o propunere — operatorul îl poate schimba înainte de a confirma.
     */
    public static function suggestUsername(Teacher|Student $fiche): string
    {
        $base = Str::slug(Str::ascii(trim($fiche->last_name.' '.$fiche->first_name)), '.');
        $base = trim(preg_replace('/[^a-z0-9.]+/', '', mb_strtolower($base)) ?? '', '.');

        if ($base === '') {
            $base = 'cont';
        }

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.$suffix;
        }

        return $candidate;
    }
}
