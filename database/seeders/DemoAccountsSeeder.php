<?php

namespace Database\Seeders;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoAccountsSeeder extends Seeder
{
    /**
     * Conturi demo: doi elevi, un părinte cu doi copii, un profesor și un diriginte
     * (fiecare legat de o fișă reală distinctă).
     */
    public function run(): void
    {
        foreach ([UserRole::Elev, UserRole::Parinte, UserRole::Profesor, UserRole::Diriginte] as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Cont de profesor (de preferință un diriginte, util pentru scoping ulterior)
        $teacher = Teacher::query()->whereHas('homeroomClasses')->orderBy('id')->first()
            ?? Teacher::query()->orderBy('id')->first();

        if ($teacher) {
            $prof = User::updateOrCreate(
                ['email' => 'profesor@columna.test'],
                ['name' => DemoAccounts::MARKER.' '.$teacher->full_name, 'username' => 'profesor', 'password' => 'password'],
            );
            $prof->forceFill(['email_verified_at' => now()])->save();
            $prof->syncRoles([UserRole::Profesor->value]);
            $teacher->update(['user_id' => $prof->id]);

            $this->command->info("Profesor: profesor@columna.test / password  →  {$teacher->full_name}");
        }

        // Cont de diriginte cu rolul Diriginte (nu Profesor) — altă fișă de profesor, cu clasă
        // de diriginte proprie (user_id e 1-la-1, nu poate fi aceeași fișă ca profesor@ de mai sus).
        $homeroomTeacher = Teacher::query()
            ->whereHas('homeroomClasses')
            ->when($teacher, fn ($q) => $q->whereKeyNot($teacher->id))
            ->orderBy('id')
            ->first();

        if ($homeroomTeacher) {
            $diriginte = User::updateOrCreate(
                ['email' => 'diriginte@columna.test'],
                ['name' => DemoAccounts::MARKER.' '.$homeroomTeacher->full_name, 'username' => 'diriginte', 'password' => 'password'],
            );
            $diriginte->forceFill(['email_verified_at' => now()])->save();
            $diriginte->syncRoles([UserRole::Diriginte->value]);
            $homeroomTeacher->update(['user_id' => $diriginte->id]);

            $this->command->info("Diriginte: diriginte@columna.test / password  →  {$homeroomTeacher->full_name}");
        }

        $students = Student::query()
            ->whereHas('grades', fn ($q) => $q->whereNotNull('value'))
            ->orderByDesc('id')
            ->take(4)
            ->get();

        if ($students->count() < 4) {
            $this->command->warn('Nu sunt destui elevi cu note numerice pentru conturile demo.');

            return;
        }

        // Cont de elev (legat de propria fișă)
        $student = $students[0];
        $elev = User::updateOrCreate(
            ['email' => 'elev@columna.test'],
            ['name' => DemoAccounts::MARKER.' '.$student->full_name, 'username' => 'elev', 'password' => 'password'],
        );
        $elev->forceFill(['email_verified_at' => now()])->save();
        $elev->syncRoles([UserRole::Elev->value]);
        $student->update(['user_id' => $elev->id]);

        // Cont de părinte cu doi copii
        $parent = User::updateOrCreate(
            ['email' => 'parinte@columna.test'],
            ['name' => DemoAccounts::MARKER.' Părinte', 'username' => 'parinte', 'password' => 'password'],
        );
        $parent->forceFill(['email_verified_at' => now()])->save();
        $parent->syncRoles([UserRole::Parinte->value]);
        $parent->students()->sync([$students[1]->id, $students[2]->id]);

        // Al doilea cont de elev (fișă distinctă, util pentru testare cu doi conturi elev simultan)
        $student2 = $students[3];
        $elev2 = User::updateOrCreate(
            ['email' => 'elev2@columna.test'],
            ['name' => DemoAccounts::MARKER.' '.$student2->full_name, 'username' => 'elev2', 'password' => 'password'],
        );
        $elev2->forceFill(['email_verified_at' => now()])->save();
        $elev2->syncRoles([UserRole::Elev->value]);
        $student2->update(['user_id' => $elev2->id]);

        $this->command->info("Elev:    elev@columna.test / password  →  {$student->full_name}");
        $this->command->info("Părinte: parinte@columna.test / password  →  copii: {$students[1]->full_name}, {$students[2]->full_name}");
        $this->command->info("Elev 2:  elev2@columna.test / password  →  {$student2->full_name}");
    }
}
