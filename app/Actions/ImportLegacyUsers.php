<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creează utilizatori reali din conturile de login vechi (bdn_users), legați prin nume
 * de fișele de elev/profesor. Parola veche e re-hash-uită bcrypt; userii sunt forțați
 * să schimbe parola la prima logare. Idempotentă (sare peste username-urile existente).
 */
class ImportLegacyUsers
{
    /**
     * @return array{created:int, elevi:int, profesori:int, diriginti:int, directori:int, skippedExist:int, skippedNoMatch:int, duplicateLogins:int}
     */
    public function execute(): array
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Hărți nume normalizat → id (prima potrivire câștigă la nume duplicate).
        $studentByName = [];
        foreach (Student::query()->get() as $student) {
            $studentByName[$this->normalize($student->last_name.' '.$student->first_name)] ??= $student->id;
        }
        $teacherByName = [];
        foreach (Teacher::query()->get() as $teacher) {
            $teacherByName[$this->normalize($teacher->last_name.' '.$teacher->first_name)] ??= $teacher->id;
        }

        $now = Carbon::now();
        $loginSeen = [];
        $created = 0;
        $skippedExist = 0;
        $skippedNoMatch = 0;
        $duplicateLogins = 0;
        $perRole = [];

        foreach (DB::connection('legacy')->table('bdn_users')->orderBy('id')->get() as $row) {
            // func=4 = conducere → director. `admin` e rol TEHNIC, creat doar manual
            // (app:create-admin), niciodată din import.
            $role = match ((string) $row->func) {
                '1' => UserRole::Elev,
                '2' => UserRole::Profesor,
                '3' => UserRole::Diriginte,
                '4' => UserRole::Director,
                default => null,
            };

            if ($role === null) {
                continue;
            }

            $key = $this->normalize(((string) $row->name_1).' '.((string) $row->name_2));
            $studentId = $role === UserRole::Elev ? ($studentByName[$key] ?? null) : null;
            $teacherId = in_array($role, [UserRole::Profesor, UserRole::Diriginte, UserRole::Director], true)
                ? ($teacherByName[$key] ?? null)
                : null;

            // Elevii/profesorii fără fișă potrivită (ex. absolvenți) nu se creează.
            if ($role === UserRole::Elev && $studentId === null) {
                $skippedNoMatch++;

                continue;
            }
            if (in_array($role, [UserRole::Profesor, UserRole::Diriginte], true) && $teacherId === null) {
                $skippedNoMatch++;

                continue;
            }

            $base = Str::lower(trim((string) $row->login));
            if ($base === '') {
                $skippedNoMatch++;

                continue;
            }

            // Username determinist din ordinea de procesare → re-rularea e idempotentă.
            $loginSeen[$base] = ($loginSeen[$base] ?? 0) + 1;
            if ($loginSeen[$base] > 1) {
                $duplicateLogins++;
            }
            $username = $loginSeen[$base] === 1 ? $base : $base.$loginSeen[$base];

            if (User::query()->where('username', $username)->exists()) {
                $skippedExist++;

                continue;
            }

            $user = new User;
            $user->forceFill([
                'name' => trim(((string) $row->name_1).' '.((string) $row->name_2)),
                'username' => $username,
                'email' => null,
                'password' => (string) $row->password, // cast-ul `hashed` aplică bcrypt — nicio parolă în clar
                'must_change_password' => true,
                'email_verified_at' => $now, // cont de încredere (migrat) — trece de `verified`
            ])->save();

            $user->assignRole($role->value);

            if ($studentId !== null) {
                Student::query()->whereKey($studentId)->update(['user_id' => $user->id]);
            }
            if ($teacherId !== null) {
                Teacher::query()->whereKey($teacherId)->update(['user_id' => $user->id]);
            }

            $created++;
            $perRole[$role->value] = ($perRole[$role->value] ?? 0) + 1;
        }

        return [
            'created' => $created,
            'elevi' => $perRole[UserRole::Elev->value] ?? 0,
            'profesori' => $perRole[UserRole::Profesor->value] ?? 0,
            'diriginti' => $perRole[UserRole::Diriginte->value] ?? 0,
            'directori' => $perRole[UserRole::Director->value] ?? 0,
            'skippedExist' => $skippedExist,
            'skippedNoMatch' => $skippedNoMatch,
            'duplicateLogins' => $duplicateLogins,
        ];
    }

    /**
     * Rândurile de rezumat pentru afișare în consolă.
     *
     * @param  array{created:int, elevi:int, profesori:int, diriginti:int, directori:int, skippedExist:int, skippedNoMatch:int, duplicateLogins:int}  $stats
     * @return array<int, array{0: string, 1: int}>
     */
    public static function summaryRows(array $stats): array
    {
        return [
            ['Create', $stats['created']],
            ['  elevi', $stats['elevi']],
            ['  profesori', $stats['profesori']],
            ['  diriginți', $stats['diriginti']],
            ['  directori', $stats['directori']],
            ['Sărite — deja existau', $stats['skippedExist']],
            ['Sărite — fără fișă potrivită', $stats['skippedNoMatch']],
            ['Login-uri duplicate dezambiguizate', $stats['duplicateLogins']],
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
