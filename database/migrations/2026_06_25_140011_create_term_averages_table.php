<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_averages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('type')->nullable(); // st_n (4/5/6)
            $table->decimal('value', 4, 2)->nullable();
            $table->string('calificativ', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'subject_id', 'term_id', 'type'], 'term_average_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_averages');
    }
};
