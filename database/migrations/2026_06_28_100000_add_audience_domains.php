<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audiențe pe domenii (spec §4.2): atribut de „responsabil de domeniu" pe conturi (fără roluri noi)
 * + domeniul concret al fiecărei solicitări de audiență, pentru rutare și raportare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Domeniile de audiență pe care contul le gestionează (instruire / educație). NULL = niciunul.
            $table->json('audience_domains')->nullable()->after('notification_preferences');
        });

        Schema::table('messages', function (Blueprint $table): void {
            // Domeniul unei solicitări de audiență (NULL pentru mesajele directe).
            $table->string('audience_domain')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('audience_domains');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('audience_domain');
        });
    }
};
