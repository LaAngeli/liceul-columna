<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Starea contului (spec administrare conturi, 2026-07-16): un cont SUSPENDAT nu se mai poate
     * autentifica (FortifyServiceProvider), nu accesează panoul (canAccessPanel), iar sesiunile
     * existente sunt închise la următoarea cerere (EnsureAccountActive). Timestamp, nu boolean —
     * păstrează și CÂND a fost suspendat (audit).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });
    }
};
