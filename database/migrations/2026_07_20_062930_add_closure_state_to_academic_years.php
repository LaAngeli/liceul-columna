<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Închiderea anului școlar devine o STARE, nu doar un efect.
 *
 * Arhivarea scria mediile în foaia matricolă și trecea mai departe, dar anul rămânea, din punctul de
 * vedere al bazei, la fel de scriibil ca oricare altul: o notă introdusă a doua zi intra fără nicio
 * obiecție într-un an deja consemnat oficial, iar foaia matricolă și catalogul spuneau de atunci
 * lucruri diferite. `closed_at` marchează momentul, `closed_by_user_id` persoana — răspunderea are
 * nume, cum cere disciplina de audit a evaluărilor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('is_current');
            $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn('closed_at');
        });
    }
};
