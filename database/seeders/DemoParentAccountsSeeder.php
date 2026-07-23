<?php

namespace Database\Seeders;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Conturi demo de PĂRINTE legate de elevi reali (cerința beneficiarului 2026-07-23): sursa legacy
 * n-are părinți, deci audiențele „doar părinții"/„familiile" nu se puteau proba pe date realiste.
 * Compoziția acoperă cazurile interesante: o familie cu DOI părinți la același copil, un părinte
 * cu DOI copii, restul 1-la-1 — pe elevi activi din clase diferite, fără părinți deja legați.
 *
 * Toate conturile poartă marcajul „[DEMO]" în nume → le vede și le șterge `app:demo-accounts`.
 * Idempotent: updateOrCreate pe email + sync pe copii. Necesită date importate + un an curent.
 */
class DemoParentAccountsSeeder extends Seeder
{
    private const FIRST_NAMES = ['Victor', 'Elena', 'Andrei', 'Maria', 'Ion', 'Aurelia', 'Sergiu', 'Diana'];

    public function run(): void
    {
        Role::findOrCreate(UserRole::Parinte->value, 'web');

        $students = $this->pickStudents()->all();

        if (count($students) < 8) {
            $this->command->warn('Nu sunt destui elevi activi fără părinți legați pentru părinții demo.');

            return;
        }

        // Familia 1: DOI părinți, același copil. Familia 2: UN părinte cu DOI copii. Restul: 1-la-1.
        $plan = [
            [self::FIRST_NAMES[0], [$students[0]]],
            [self::FIRST_NAMES[1], [$students[0]]],
            [self::FIRST_NAMES[2], [$students[1], $students[2]]],
            [self::FIRST_NAMES[3], [$students[3]]],
            [self::FIRST_NAMES[4], [$students[4]]],
            [self::FIRST_NAMES[5], [$students[5]]],
            [self::FIRST_NAMES[6], [$students[6]]],
            [self::FIRST_NAMES[7], [$students[7]]],
        ];

        foreach ($plan as $index => [$firstName, $children]) {
            $number = $index + 1;

            $parent = User::updateOrCreate(
                ['email' => "parinte.demo{$number}@columna.test"],
                [
                    'name' => DemoAccounts::MARKER.' Părinte '.$children[0]->last_name.' '.$firstName,
                    'username' => "parinte-demo-{$number}",
                    'password' => 'password',
                ],
            );
            $parent->forceFill(['email_verified_at' => now()])->save();
            $parent->syncRoles([UserRole::Parinte->value]);
            $parent->students()->sync(collect($children)->pluck('id')->all());

            $names = collect($children)->map(fn (Student $student): string => $student->full_name)->implode(', ');
            $this->command->info("Părinte demo {$number}: parinte.demo{$number}@columna.test / password  →  copii: {$names}");
        }
    }

    /**
     * Elevi ACTIVI (anul curent, neplecați) fără niciun părinte legat, din clase cât mai diferite —
     * deterministic, ca re-rularea să regăsească aceiași elevi.
     *
     * @return Collection<int, Student>
     */
    private function pickStudents(): Collection
    {
        $yearId = SchoolCalendar::currentYearId();

        $candidates = Student::query()
            // „Fără părinți" = fără niciun părinte în afara celor creați de ACEST seeder — altfel
            // legăturile proprii ar descalifica elevii la re-rulare și s-ar alege alt set.
            ->whereDoesntHave('guardians', fn ($query) => $query
                ->where('users.email', 'not like', 'parinte.demo%@columna.test'))
            ->whereHas('enrollments', fn ($query) => $query
                ->when($yearId !== null, fn ($inner) => $inner->where('academic_year_id', $yearId))
                ->whereNull('left_on'))
            ->with(['enrollments' => fn ($query) => $query
                ->when($yearId !== null, fn ($inner) => $inner->where('academic_year_id', $yearId))
                ->whereNull('left_on')])
            ->orderBy('id')
            ->take(60)
            ->get();

        // Întâi câte unul din fiecare clasă (diversitate), apoi completare cu restul.
        $byClass = [];
        $rest = [];

        foreach ($candidates as $student) {
            $enrollment = $student->enrollments->first();
            $classId = $enrollment->school_class_id ?? 0;

            if (! array_key_exists($classId, $byClass)) {
                $byClass[$classId] = $student;
            } else {
                $rest[] = $student;
            }
        }

        return collect($byClass)->values()->concat($rest)->take(8)->values();
    }
}
