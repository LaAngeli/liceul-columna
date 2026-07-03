<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CmsAdmin extends Command
{
    protected $signature = 'app:cms-admin
        {--email= : Adresa de email (implicit CMS_ADMIN_EMAIL din .env)}
        {--name= : Numele afișat (implicit CMS_ADMIN_NAME din .env)}
        {--password= : Parola (implicit CMS_ADMIN_PASSWORD din .env; se generează una dacă lipsește la creare)}';

    protected $description = 'Provizionează contul UNIC de administrare a conținutului (panoul /studio), din .env. Idempotent.';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: config('cms.admin.email'));

        if ($email === '') {
            $this->error('Lipsește adresa de email. Setează CMS_ADMIN_EMAIL în .env sau folosește --email.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: config('cms.admin.name') ?: 'Administrator conținut');

        $existing = Admin::query()->where('email', $email)->first();

        // Parola: opțiune > .env. La creare, dacă lipsește, generăm una și o afișăm o singură dată.
        // La actualizarea unui cont existent fără parolă dată, NU o suprascriem (o păstrăm pe cea curentă).
        $password = (string) ($this->option('password') ?: config('cms.admin.password'));
        $generated = false;

        if ($password === '' && $existing === null) {
            $password = Str::password(20);
            $generated = true;
        }

        $attributes = ['name' => $name];
        if ($password !== '') {
            $attributes['password'] = $password;
        }

        $admin = Admin::updateOrCreate(['email' => $email], $attributes);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->info(($existing ? 'Cont de conținut actualizat: ' : 'Cont de conținut creat: ').$admin->email);

        if ($generated) {
            $this->warn("Parolă generată (schimb-o după prima logare): {$password}");
        }

        $this->line('Autentificare: '.url('/studio').' (MFA obligatoriu la prima logare).');

        return self::SUCCESS;
    }
}
