<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete(); // autorul notei
            $table->date('graded_on');
            $table->unsignedTinyInteger('type')->nullable(); // st_n legacy (1–6)
            $table->decimal('value', 4, 2)->nullable(); // notă numerică
            $table->string('calificativ', 10)->nullable(); // calif
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
