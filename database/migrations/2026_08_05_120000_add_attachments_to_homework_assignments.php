<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fișiere atașate temei (cerința beneficiarului, 05.08.2026): profesorul încarcă fișe de lucru /
 * prezentări, elevul le descarcă din cabinet.
 *
 * Două coloane, convenția Filament (`storeFileNamesIn`): `attachments` = căile din storage (nume
 * generate aleator — numele venit de la utilizator nu ajunge NICIODATĂ pe disc), `attachment_names`
 * = harta cale → numele original, afișat în cabinet și pus pe descărcare. Fișierele stau pe discul
 * PRIVAT (`local`): conținut didactic pentru clasă, servit doar printr-o rută autentificată care
 * respectă vizibilitatea temei — fără URL public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->json('attachments')->nullable()->after('printed_resources');
            $table->json('attachment_names')->nullable()->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('homework_assignments', function (Blueprint $table): void {
            $table->dropColumn(['attachments', 'attachment_names']);
        });
    }
};
