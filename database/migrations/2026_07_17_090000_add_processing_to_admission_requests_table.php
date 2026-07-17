<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            // Urma procesării (cine/când a lucrat cererea) — până acum statusul se schimba
            // anonim, fără moment și fără responsabil: inacceptabil pe date de minori.
            $table->timestamp('contacted_at')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('contacted_at');
            $table->foreignId('processed_by_id')->nullable()->after('processed_at')
                ->constrained('users')->nullOnDelete();
            $table->text('staff_note')->nullable()->after('processed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('admission_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by_id');
            $table->dropColumn(['contacted_at', 'processed_at', 'staff_note']);
        });
    }
};
