<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Componenta temporală a temelor (cerința beneficiarului 2026-07-18): `due_on` = TERMENUL —
 * „pentru ce zi e tema". Obligatoriu în formular de-acum înainte; NULLABLE în DB pentru cele
 * ~6.900 de teme legacy (fără termen istoric) — afișările cad pe `assigned_on` (data atribuirii)
 * prin data efectivă COALESCE(due_on, assigned_on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->date('due_on')->nullable()->after('assigned_on')->index();
        });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->dropColumn('due_on');
        });
    }
};
