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
        Schema::table('term_averages', function (Blueprint $table) {
            // Componentele mediei semestriale (§1.3): media notelor curente și sumativa
            // semestrială (ESS/teză), păstrate pentru pragul pe componente și afișarea breakdown.
            $table->decimal('mc_value', 4, 2)->nullable()->after('value');
            $table->decimal('summative_value', 4, 2)->nullable()->after('mc_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('term_averages', function (Blueprint $table) {
            $table->dropColumn(['mc_value', 'summative_value']);
        });
    }
};
