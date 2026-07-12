<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fluxul real contestație→corecție (decizia userului, #36): o corecție de notă poate proveni
 * dintr-o CONTESTAȚIE depusă de familie (DocumentRequest type=contestatie). Legătura permite:
 * (a) trasabilitate (secretariatul vede ce corecție a produs cererea), (b) notificarea FAMILIEI
 * la respingerea reexaminării (altfel verdictul mergea doar la staff-ul care a deschis corecția).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_corrections', function (Blueprint $table): void {
            $table->foreignId('document_request_id')
                ->nullable()
                ->after('requested_by_user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grade_corrections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('document_request_id');
        });
    }
};
