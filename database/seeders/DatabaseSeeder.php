<?php

namespace Database\Seeders;

use App\Console\Commands\DemoAccounts;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Admin de dezvoltare (cont demo). În producție creezi un admin real cu
        // `php artisan app:create-admin` — acela NU are marcajul [DEMO], deci nu e curățat.
        $admin = User::factory()->create([
            'name' => DemoAccounts::MARKER.' Administrator',
            'email' => 'admin@liceul-columna.test',
        ]);
        $admin->assignRole(UserRole::Admin->value);
    }
}
