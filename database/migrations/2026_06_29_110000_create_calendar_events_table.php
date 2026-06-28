<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evenimente de calendar MANUALE (modul Calendar v2): evenimente școlare, ședințe, activități
 * extracurriculare, termene custom. Audiență pe scope (global/treaptă/clasă). Titlul RO e pe model;
 * traducerile RU/EN în calendar_event_translations (vezi migrarea pereche).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type');             // CalendarEventType
            $table->string('visibility_scope'); // CalendarEventScope
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');            // RO (sursă)
            $table->text('description')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = o singură zi
            $table->string('start_time', 5)->nullable(); // HH:MM; null = toată ziua
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_on', 'visibility_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
