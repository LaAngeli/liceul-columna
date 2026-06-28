<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consimțământ / luare la cunoștință (Legea 133/2011, spec §7): elevul/părintele confirmă nota de
 * informare. Pe `users` ținem versiunea curentă confirmată (verificare rapidă în middleware); în
 * `consent_acknowledgments` ținem ISTORICUL complet (dovadă: cine, ce versiune, când, de la ce IP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('privacy_acknowledged_version')->nullable()->after('audience_domains');
            $table->timestamp('privacy_acknowledged_at')->nullable()->after('privacy_acknowledged_version');
        });

        Schema::create('consent_acknowledgments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_version');
            $table->timestamp('acknowledged_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_acknowledgments');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['privacy_acknowledged_version', 'privacy_acknowledged_at']);
        });
    }
};
