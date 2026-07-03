<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Momentul activării 2FA pe email (metoda alternativă la TOTP). Nullable = inactiv.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_email_enabled_at')->nullable()->after('two_factor_confirmed_at');
        });

        // Codul OTP activ per utilizator (UN singur cod odată): hash sha256 (niciodată în clar),
        // TTL + contor de încercări (anti brute-force, pe lângă rate-limiter). `pending_email` =
        // adresa în curs de verificare la activare (594/603 conturi nu au email — fluxul de
        // activare o adaugă și o verifică prin același cod). Efemer — fără soft deletes.
        Schema::create('two_factor_email_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->string('pending_email')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_email_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_email_enabled_at');
        });
    }
};
