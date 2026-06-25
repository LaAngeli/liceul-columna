<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('grade_level'); // cl_rang (1–12)
            $table->string('name', 20); // den_cl (ex: VIII)
            $table->string('section', 4)->nullable(); // prim_id (ex: 1 / A)
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->nullOnDelete(); // diriginte
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'grade_level', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
