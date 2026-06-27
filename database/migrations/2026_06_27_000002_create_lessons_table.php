<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orarul STRUCTURAT (spec §2.1) — un slot = o lecție a unei clase într-o zi/oră, cu disciplina,
 * profesorul și sala. Distinct de orarele publicabile #39 (tabele la nivel de școală). Tiparul e
 * săptămânal unic (fără alternanță 1/2). Elevii moștenesc orarul clasei lor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = luni … 6 = sâmbătă
            $table->unsignedTinyInteger('lesson_number'); // 1 … 8
            $table->string('room')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // O clasă nu poate avea două lecții în același slot (zi + nr. lecție) într-un an.
            $table->unique(
                ['school_class_id', 'academic_year_id', 'day_of_week', 'lesson_number'],
                'lesson_class_slot_unique',
            );
            $table->index(['school_class_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
