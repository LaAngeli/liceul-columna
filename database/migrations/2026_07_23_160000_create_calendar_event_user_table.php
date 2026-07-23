<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria selecției de PĂRINȚI la audiența nominală cu reach = „doar părinții" (feedback
 * beneficiar 2026-07-23): la editare, formularul re-afișează exact conturile alese, nu o
 * derivare din elevi. Vizibilitatea evenimentului NU citește acest pivot — rămâne pe
 * calendar_event_student × audience_reach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_user', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['calendar_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_user');
    }
};
