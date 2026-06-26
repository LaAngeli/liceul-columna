<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
        {email : Adresa de email a administratorului}
        {--name=Administrator : Numele afișat}
        {--password= : Parola (dacă lipsește, se generează una)}';

    protected $description = 'Creează sau promovează un Super Administrator (rolul `admin` — acces total la panou).';

    public function handle(): int
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $generated = false;
        $password = (string) $this->option('password');
        if ($password === '') {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => (string) $this->option('name'), 'password' => $password],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole(UserRole::Admin->value);

        $this->info("Super Administrator pregătit: {$user->email}");
        if ($generated) {
            $this->warn("Parolă generată (schimb-o după prima logare): {$password}");
        }

        return self::SUCCESS;
    }
}
