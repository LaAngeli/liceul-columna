<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zilele libere capătă CATEGORIE (sărbătoare legală / vacanță / zi instituțională / alta — enum
 * HolidayType) și o notă opțională (temeiul: ordin MEC, decizie a administrației). Rândurile
 * existente devin „instituționale" prin default — cea mai neutră presupunere; administratorul
 * le re-încadrează din planificator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->string('type')->default('institutional')->after('name');
            $table->string('note', 500)->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropColumn(['type', 'note']);
        });
    }
};
