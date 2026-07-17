<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Justificativ atașabil la cererile tipice (§4.3): bilet medical la învoire, cererea școlii noi
 * la transfer, foto lucrării la contestație. Fișier PRIVAT (PII de minor) — coloana ține calea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->string('attachment_path')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn('attachment_path');
        });
    }
};
