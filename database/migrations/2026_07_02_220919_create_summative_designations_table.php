<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summative_designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            // Ordinul care stabilește disciplina cu sumativă (directorul la gimnaziu, MEC la liceu).
            $table->string('order_reference')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'school_class_id'], 'summative_designation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summative_designations');
    }
};
