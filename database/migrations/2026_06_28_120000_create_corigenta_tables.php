<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar de lichidare a corigenței (spec §2.5 / #33): comisii de examen, sesiuni (vară/iarnă,
 * bază/repetată) cu flux de aprobare propunere→ordin→publicare, și intrările per-elev generate
 * automat la marcarea statutului „corigent".
 */
return new class extends Migration
{
    public function up(): void
    {
        // Comisii de examen (disciplină + președinte + membri).
        Schema::create('exam_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('president_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exam_commission_teacher', function (Blueprint $table): void {
            $table->foreignId('exam_commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->primary(['exam_commission_id', 'teacher_id']);
        });

        // Sesiuni de corigență (propuse de vicedirector → aprobate de director → publicate de AO).
        Schema::create('corigenta_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('season');  // iarna / vara
            $table->string('type');    // baza / repetata
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('draft');
            $table->string('order_reference')->nullable(); // ordinul directorului (nr./dată)
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Intrarea per-elev: generată automat la marcarea „corigent" (disciplină restantă).
        Schema::create('corigenta_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('season');
            $table->foreignId('corigenta_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_commission_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_on')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamps();

            // O singură intrare per (elev, disciplină, semestru).
            $table->unique(['student_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corigenta_exams');
        Schema::dropIfExists('corigenta_sessions');
        Schema::dropIfExists('exam_commission_teacher');
        Schema::dropIfExists('exam_commissions');
    }
};
