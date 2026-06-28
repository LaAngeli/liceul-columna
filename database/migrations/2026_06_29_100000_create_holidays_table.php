<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zile libere / vacanțe (modul Calendar + #41/§8). Sursă UNICĂ a „zilei nelucrătoare", folosită de
 * calendar (fundal), de WorkingDays (termenele de 5/2 zile lucrătoare ale motivărilor) și de
 * expandarea orarului. Întreținută de administratorul operațional. O zi (ends_on null) sau un interval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = o singură zi
            $table->timestamps();

            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
