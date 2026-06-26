<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * §1: notele nu se șterg niciodată — se ANULEAZĂ cu motiv, rămân în istoric.
     * O notă anulată nu contează la medii și nu apare în cabinet, dar e vizibilă în panou.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->timestamp('annulled_at')->nullable()->after('value');
            $table->foreignId('annulled_by_user_id')->nullable()->after('annulled_at')->constrained('users')->nullOnDelete();
            $table->string('annulment_reason')->nullable()->after('annulled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annulled_by_user_id');
            $table->dropColumn(['annulled_at', 'annulment_reason']);
        });
    }
};
