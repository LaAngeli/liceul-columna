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
     * Conturi demo: un elev, un părinte cu doi copii și un profesor (legat de o fișă).
     */
    public function run(): void
    {
        foreach ([UserRole::Elev, UserRole::Parinte, UserRole::Profesor] as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        // Cont de profesor (de preferință un diriginte, util pentru scoping ulterior)
        $teacher = Teacher::query()->whereHas('homeroomClasses')->orderBy('id')->first()
            ?? Teacher::query()->orderBy('id')->first();

        if ($teacher) {
            $prof = User::updateOrCreate(
                ['email' => 'profesor@columna.test'],
                ['name' => DemoAccounts::MARKER.' '.$teacher->full_name, 'password' => 'password'],
            );
            $prof->forceFill(['email_verified_at' => now()])->save();
            $prof->syncRoles([UserRole::Profesor->value]);
            $teacher->update(['user_id' => $prof->id]);

            $this->command->info("Profesor: profesor@columna.test / password  →  {$teacher->full_name}");
        }

        $students = Student::query()
            ->whereHas('grades', fn ($q) => $q->whereNotNull('value'))
            ->orderByDesc('id')
            ->take(3)
            ->get();

        if ($students->count() < 3) {
            $this->command->warn('Nu sunt destui elevi cu note numerice pentru conturile demo.');

            return;
        }

        // Cont de elev (legat de propria fișă)
        $student = $students[0];
        $elev = User::updateOrCreate(
            ['email' => 'elev@columna.test'],
            ['name' => DemoAccounts::MARKER.' '.$student->full_name, 'password' => 'password'],
        );
        $elev->forceFill(['email_verified_at' => now()])->save();
        $elev->syncRoles([UserRole::Elev->value]);
        $student->update(['user_id' => $elev->id]);

        // Cont de părinte cu doi copii
        $parent = User::updateOrCreate(
            ['email' => 'parinte@columna.test'],
            ['name' => DemoAccounts::MARKER.' Părinte', 'password' => 'password'],
        );
        $parent->forceFill(['email_verified_at' => now()])->save();
        $parent->syncRoles([UserRole::Parinte->value]);
        $parent->students()->sync([$students[1]->id, $students[2]->id]);

        $this->command->info("Elev:    elev@columna.test / password  →  {$student->full_name}");
        $this->command->info("Părinte: parinte@columna.test / password  →  copii: {$students[1]->full_name}, {$students[2]->full_name}");
    }
}
