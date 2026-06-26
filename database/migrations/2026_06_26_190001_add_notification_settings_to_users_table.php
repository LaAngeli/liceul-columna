<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferințele de notificare ale utilizatorului (spec §5): contactele pentru canale sociale și
 * matricea „ce tip pe ce canal". Ambele JSON, pe `users` (single-tenant; nu justifică tabel separat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // {telegram: "...", viber: "...", messenger: "...", whatsapp: "..."}
            $table->json('notification_contacts')->nullable();
            // {new_grade: ["cabinet","telegram"], new_homework: ["email"], ...}
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notification_contacts', 'notification_preferences']);
        });
    }
};
