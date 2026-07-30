<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * „Termenul" temei (due_on) se ELIMINĂ complet (decizia beneficiarului, 2026-07-31): școala
     * lucrează pe DATA LECȚIEI (assigned_on) — ea redevine axa unică a sortărilor, filtrelor,
     * cabinetului și calendarului. Importul legacy nu popula niciodată coloana.
     */
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            // Întâi indexul (din migrarea de adăugare), apoi coloana — SQLite (testele) refuză
            // să arunce o coloană încă indexată; MySQL le-ar fi curățat împreună.
            $table->dropIndex('homework_assignments_due_on_index');
            $table->dropColumn('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('assigned_on')->index();
        });
    }
};
