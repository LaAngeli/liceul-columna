<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimină eticheta legacy `position` de pe fișele de profesor (decizia beneficiarului, varianta 1).
 *
 * Câmpul venea din importul legacy (`func` 3 → „Diriginte", altfel „Profesor"), era text liber
 * necontrolat și MINȚEA: auditul de fidelitate a găsit fișe „Diriginte" fără nicio clasă în
 * coordonare — motiv pentru care registrul Profesori îl ocolea deja și deriva funcția din date
 * (dirigenția anului curent + alocările). Raportul de personal, ultimul consumator, are propria
 * coloană „Diriginte al" — sub-eticheta era redundanță. Fără down cu date: informația e derivabilă.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('position')->nullable();
        });
    }
};
