<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retenția notificărilor (cerința beneficiarului, 2026-07-21): notificările NU se mai șterg manual —
 * cele citite se ARHIVEAZĂ automat după o perioadă configurabilă (`config/notifications.php`).
 * `archived_at` = momentul mutării în arhivă (null = activă, în lista principală).
 *
 * Indexuri: lista principală și arhiva filtrează mereu pe (destinatar, archived_at), iar măturarea
 * zilnică + viitoarea politică de purge scanează (archived_at, created_at) — fără ele, fiecare
 * deschidere de inbox ar parcurge toate rândurile tabelei.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('read_at');
            $table->index(['notifiable_type', 'notifiable_id', 'archived_at'], 'notifications_notifiable_archived_index');
            $table->index(['archived_at', 'created_at'], 'notifications_archived_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_archived_index');
            $table->dropIndex('notifications_archived_created_index');
            $table->dropColumn('archived_at');
        });
    }
};
