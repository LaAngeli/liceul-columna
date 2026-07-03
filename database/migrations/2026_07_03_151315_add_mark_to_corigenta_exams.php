<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nota de corigență (0–10) înlocuiește booleanul pass/fail: promovat = notă ≥ 5, iar nota
        // devine rezultatul oficial al disciplinei (scris în foaia matricolă). `passed` → accesor.
        Schema::table('corigenta_exams', function (Blueprint $table) {
            $table->decimal('mark', 4, 2)->nullable()->after('scheduled_on');
        });

        Schema::table('corigenta_exams', function (Blueprint $table) {
            $table->dropColumn('passed');
        });
    }

    public function down(): void
    {
        Schema::table('corigenta_exams', function (Blueprint $table) {
            $table->boolean('passed')->nullable()->after('scheduled_on');
        });

        Schema::table('corigenta_exams', function (Blueprint $table) {
            $table->dropColumn('mark');
        });
    }
};
