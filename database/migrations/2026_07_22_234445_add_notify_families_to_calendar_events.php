<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comutatorul „Anunță familiile" pe un eveniment de calendar: implicit PORNIT (comportamentul de
 * până acum — orice eveniment viitor notifică). Oprit → evenimentul doar apare în calendar, fără
 * notificare la creare sau la anulare. Evenimentele existente rămân pe „notifică" (default true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->boolean('notify_families')->default(true)->after('audience_reach');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('notify_families');
        });
    }
};
