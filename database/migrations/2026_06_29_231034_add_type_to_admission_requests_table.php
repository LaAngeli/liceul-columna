<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            // Distinge programarea vizitei de cererea de înmatriculare. Cererile existente = înmatriculare.
            $table->string('type')->default('enrollment')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
