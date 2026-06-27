<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limba în care utilizatorul vrea să PRIMEASCĂ notificările (spec §5). Separată de `locale`
 * (limba interfeței): un părinte poate naviga în RO, dar cere notificările în RU. Null → se
 * folosește `locale`, apoi RO (vezi `User::notificationLocale()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('notification_locale', 5)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_locale');
        });
    }
};
