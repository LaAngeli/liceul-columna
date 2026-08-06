<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitatea de ORĂ a notei (cerința beneficiarului, 06.08.2026) — simetrică absențelor: la două
 * ore ale aceleiași discipline în aceeași zi, fiecare oră își poate primi nota EI, iar slotul
 * (elev, zi, disciplină, oră) devine exclusiv — o oră nu poate purta și notă, și absență (elevul
 * ori a fost în bancă și a răspuns, ori a lipsit; ambele deodată e o neatenție, nu o realitate).
 *
 * `lesson_number` = a câta lecție a zilei (1–8), nullable: cele ~52.000 de note istorice (import
 * legacy) și notele din formularul clasic fără oră aleasă rămân „ziua, fără oră precizată" —
 * în afara regulii de exclusivitate (nu li se poate ști ora).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->unsignedTinyInteger('lesson_number')->nullable()->after('graded_on');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropColumn('lesson_number');
        });
    }
};
