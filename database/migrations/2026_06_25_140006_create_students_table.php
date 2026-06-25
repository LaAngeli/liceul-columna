<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name'); // name_1
            $table->string('last_name')->nullable(); // name_2
            $table->string('sex', 1)->nullable(); // f/m
            $table->string('register_number', 10)->nullable(); // id_reg
            $table->unsignedTinyInteger('english_group')->nullable(); // engl_gr
            $table->string('second_language', 4)->default('nu'); // str_2 (fr/gm/nu)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
