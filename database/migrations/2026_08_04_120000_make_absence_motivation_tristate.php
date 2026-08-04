<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absența capătă un al TREILEA statut: „fără statut" (cerința beneficiarului, 04.08.2026).
 *
 * Profesorul care predă la clasă rareori știe DE CE lipsește elevul — doar dirigintele află,
 * pe parcursul zilei. Până acum formularul îl obliga pe profesor să aleagă motivat/nemotivat
 * la consemnare, deci ghicea. De-acum profesorul doar consemnează („Absent"), iar statutul îl
 * fixează dirigintele.
 *
 * Reprezentare: `is_motivated` devine NULLABLE — `null` = în așteptarea dirigintelui,
 * `true`/`false` = statut fixat. Coloana NU se redenumește și rândurile existente NU se ating:
 * toate interogările istorice (`where('is_motivated', true/false)`) își păstrează sensul exact,
 * iar absențele deja statuate rămân statuate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->boolean('is_motivated')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // La revenire, absențele fără statut redevin „nemotivate" — sensul vechiului default.
        Schema::table('absences', function (Blueprint $table): void {
            $table->boolean('is_motivated')->nullable(false)->default(false)->change();
        });
    }
};
