<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANULAREA unei absențe (cerința beneficiarului, 07.08.2026): până acum o absență introdusă din
 * greșeală nu se putea desface — statutul avea trei stări (motivată / nemotivată / fără statut),
 * niciuna însemnând „nu s-a întâmplat".
 *
 * Aceleași trei coloane ca la note (`2026_06_26_133141`), fiindcă e același act: rândul RĂMÂNE în
 * istoric (cine a consemnat, când, ce), dar nu mai contează nicăieri. Ștergerea, chiar și soft, ar
 * fi pierdut MOTIVUL și autorul îndreptării — iar o absență care dispare fără urmă e exact tipul de
 * incertitudine pe care mecanismul îl repară.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->timestamp('annulled_at')->nullable()->after('motivation_locked_at');
            $table->foreignId('annulled_by_user_id')->nullable()->after('annulled_at')->constrained('users')->nullOnDelete();
            $table->string('annulment_reason')->nullable()->after('annulled_by_user_id');

            // Fiecare listă și numărătoare filtrează pe „neanulată"; fără index, filtrul ar fi
            // costat un scan pe toată tabela (13.950 de rânduri importate + creșterea anuală).
            $table->index('annulled_at');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table): void {
            $table->dropIndex(['annulled_at']);
            $table->dropConstrainedForeignId('annulled_by_user_id');
            $table->dropColumn(['annulled_at', 'annulment_reason']);
        });
    }
};
