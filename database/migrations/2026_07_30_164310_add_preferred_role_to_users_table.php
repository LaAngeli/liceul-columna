<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolul PREFERAT al unui cont multi-rol — default-ul comutatorului de context la login
 * (migrarea multi-rol F1, raport-multirole-context-audit.md §7).
 *
 * Rolul ACTIV trăiește în SESIUNE (moare cu login-ul, nu rămân contexte agățate); aici se
 * persistă doar ultima alegere, ca profesorul-director să nu re-comute în fiecare dimineață.
 * Nullable: conturile mono-rol — toate cele existente — nu au ce prefera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('preferred_role')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferred_role');
        });
    }
};
