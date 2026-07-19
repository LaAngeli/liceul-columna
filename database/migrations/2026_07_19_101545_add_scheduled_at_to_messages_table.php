<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendar v3 — audiența PROGRAMATĂ: conducerea (destinatarul solicitării de audiență) fixează
 * data + ora întâlnirii pe mesajul-RĂDĂCINĂ de tip „audience". Programarea se proiectează în
 * calendarul instituțional al staff-ului și în calendarul familiei solicitante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('audience_domain')->index();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
