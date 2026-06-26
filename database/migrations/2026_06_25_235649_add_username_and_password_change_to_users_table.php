<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autentificare hibridă: userii migrați din vechiul sistem au `username` (login-ul vechi)
     * și pot avea email gol; toți sunt forțați să-și schimbe parola la prima logare.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->boolean('must_change_password')->default(false)->after('password');
            // Userii migrați n-au email — autentificarea se poate face și pe username.
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'must_change_password']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
