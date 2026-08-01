<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meniul cantinei (cerință 2026-08-01): o zi = un rând, cu structura FIXĂ a meniului oficial al
 * liceului (sursa: PDF-ul lunar al cantinei) — dejunul pe patru poziții, prânzul pe șase. Coloane
 * numite, nu JSON liber: formularul rămâne zece câmpuri clare pentru administratorul operațional,
 * iar afișarea (panou + cabinet) are mereu aceeași formă, fără parsare.
 *
 * FĂRĂ soft deletes, deliberat (precedentul Lesson): ziua de meniu e conținut publicabil, nu act
 * academic — nimic nu o referă, iar indexul unic pe dată + un rând șters „moale" ar bloca pe veci
 * re-crearea aceleiași zile, fără nicio cale de restaurare din interfață.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteen_menus', function (Blueprint $table): void {
            $table->id();
            $table->date('menu_date')->unique();

            // Dejun: felul principal, fructele, produsul de panificație, băutura.
            $table->string('breakfast_main', 200)->nullable();
            $table->string('breakfast_fruit', 200)->nullable();
            $table->string('breakfast_bakery', 200)->nullable();
            $table->string('breakfast_drink', 200)->nullable();

            // Prânz: felul I, felul II, garnitura, salata, băutura, fructele.
            $table->string('lunch_first', 200)->nullable();
            $table->string('lunch_second', 200)->nullable();
            $table->string('lunch_side', 200)->nullable();
            $table->string('lunch_salad', 200)->nullable();
            $table->string('lunch_drink', 200)->nullable();
            $table->string('lunch_fruit', 200)->nullable();

            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_menus');
    }
};
