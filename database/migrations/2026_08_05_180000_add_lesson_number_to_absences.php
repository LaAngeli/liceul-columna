<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitatea de ORĂ a absenței (cerința beneficiarului, 05.08.2026): un elev poate avea în
 * aceeași zi două ore consecutive ale aceleiași discipline și poate lipsi la AMBELE — două
 * absențe distincte, fiecare a orei ei. Până acum tripleta (elev, zi, disciplină) era considerată
 * duplicat, deci a doua absență nici nu se putea consemna.
 *
 * `lesson_number` = a câta lecție a zilei (1–8), nullable: rândurile istorice și consemnarea
 * rapidă fără orar rămân „ziua, fără oră precizată" — un slot propriu în regula anti-duplicat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('lesson_number')->nullable()->after('occurred_on');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropColumn('lesson_number');
        });
    }
};
