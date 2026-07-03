<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel SEPARAT de `users`: contul de content (panoul /studio) e complet izolat de
        // sistemul academic cu PII de minori. Guard propriu `admin` (config/auth.php) → sesiune
        // și provider distincte. Un singur cont, provizionat din .env prin `php artisan app:cms-admin`.
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // MFA nativ Filament (TOTP), NU Fortify (care e legat de guard-ul `web`). Secretele
            // sunt criptate + ascunse la nivel de model (cast `encrypted` / `encrypted:array`).
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
