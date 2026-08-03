<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motivul plecării, lângă data ei. Nullable, fără valoare implicită și fără backfill: rândurile
 * istorice descriu plecări reale, dar motivul lor nu se poate DEDUCE — a-l ghici („probabil
 * transfer") ar însemna date inventate într-un registru. Rămân null și se completează doar de cine
 * le cunoaște.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('departure_reason', 20)->nullable()->after('left_on');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('departure_reason');
        });
    }
};
