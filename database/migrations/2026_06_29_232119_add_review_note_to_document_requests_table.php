<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivul respingerii unei cereri tipice (paritate cu GradeCorrection/AbsenceMotivation, care au
 * deja review_note). Permite secretariatului să RESPINGĂ o cerere cu motiv, nu doar să o proceseze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn('review_note');
        });
    }
};
