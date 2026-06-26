<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teme academice: tema dată unei clase (treaptă + literă) la o disciplină,
     * cu sarcina obligatorie/suplimentară și linkuri-resursă. Sursă: legacy `bdn_teme_ac`.
     */
    public function up(): void
    {
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete(); // autorul, când e creată din panou
            $table->string('subject_name'); // name_discipl (denormalizat, mereu prezent)
            $table->string('author_name')->nullable(); // autor (numele profesorului, text — legacy)
            $table->unsignedTinyInteger('grade_level'); // class_rang
            $table->string('section', 4)->nullable(); // prim_cl (litera clasei)
            $table->date('assigned_on'); // date_dat
            $table->text('topic')->nullable(); // subiect
            $table->text('required_task')->nullable(); // s_o (sarcina obligatorie)
            $table->text('optional_task')->nullable(); // s_s (sarcina suplimentară)
            $table->json('links')->nullable(); // link1/2/3 (cele nevide)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grade_level', 'section', 'assigned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_assignments');
    }
};
