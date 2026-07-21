<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexuri de INVESTIGARE pe jurnalul de audit (fix 2026-07-21): pachetul owen-it indexează doar
 * perechile morph (auditable, user), dar viewer-ul sortează MEREU pe `created_at desc`, filtrează
 * pe `event` și listează pe categorie = `whereIn(auditable_type) + order created_at` — pe zeci de
 * mii de rânduri (5k+/săptămână deja), fiecare din astea era un full scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->index('created_at', 'audits_created_at_index');
            $table->index('event', 'audits_event_index');
            $table->index(['auditable_type', 'created_at'], 'audits_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->dropIndex('audits_created_at_index');
            $table->dropIndex('audits_event_index');
            $table->dropIndex('audits_type_created_index');
        });
    }
};
