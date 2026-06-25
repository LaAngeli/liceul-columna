<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // n_d
            $table->string('abbreviation', 30)->nullable(); // abr
            $table->unsignedTinyInteger('min_grade')->nullable(); // de_la
            $table->unsignedTinyInteger('max_grade')->nullable(); // pana_la
            $table->string('grading_type', 2)->default('n'); // n/c/cd/d
            $table->unsignedSmallInteger('report_order')->nullable(); // ordine în foaia matricolă
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
