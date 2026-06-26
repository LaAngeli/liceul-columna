<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orarele publicabile (cele 9 secțiuni din pagina Calendar, spec §2.1). UN tabel generic:
 * editat în panou (sursa unică), citit read-only pe site doar pentru rândurile `is_public`.
 * Forma `headers`/`rows` păstrează exact structura afișată azi (label + tabel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // ScheduleType (slug-ul paginii publice)
            $table->string('label');
            $table->json('headers');
            $table->json('rows');
            $table->unsignedInteger('position')->default(0);
            // Gardul de securitate: doar rândurile is_public ajung pe site (citire publică filtrată).
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_public', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
