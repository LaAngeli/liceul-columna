<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arhiva per-utilizator a poștei interne (folderul „Arhivă", semantică Gmail): firul iese din
     * Primite fără a fi șters și fără a atinge cutia celuilalt participant. Independentă de coș
     * (trashed_at) și de stea (starred_at) — toate pe aceeași stare (rădăcina firului, utilizator).
     */
    public function up(): void
    {
        Schema::table('message_states', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::table('message_states', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
