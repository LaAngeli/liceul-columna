<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 4, 2)->nullable(); // nota
            $table->string('calificativ', 10)->nullable(); // calif
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
