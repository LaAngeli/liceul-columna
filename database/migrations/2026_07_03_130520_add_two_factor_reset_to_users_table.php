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
        // Resetarea 2FA de către staff (recuperare cont): cine/când/de ce — tiparul anulării
        // de note. Coloanele NU sunt în auditExclude → schimbarea apare integral în audit (L133).
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_reset_at')->nullable()->after('two_factor_email_enabled_at');
            $table->foreignId('two_factor_reset_by_user_id')->nullable()->after('two_factor_reset_at')
                ->constrained('users')->nullOnDelete();
            $table->string('two_factor_reset_reason')->nullable()->after('two_factor_reset_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('two_factor_reset_by_user_id');
            $table->dropColumn(['two_factor_reset_at', 'two_factor_reset_reason']);
        });
    }
};
