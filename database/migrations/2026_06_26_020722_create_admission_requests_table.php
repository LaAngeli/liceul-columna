<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_requests', function (Blueprint $table) {
            $table->id();
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('child_name');
            $table->unsignedTinyInteger('child_age')->nullable();
            $table->string('desired_class')->nullable();
            $table->text('preferred_time')->nullable();
            $table->string('status')->default('nou')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_requests');
    }
};
