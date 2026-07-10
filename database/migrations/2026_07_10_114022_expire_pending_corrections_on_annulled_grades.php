<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Curăță inconsistența pe care `GradeObserver::updated` o previne de acum înainte: cereri de
 * corecție rămase „în așteptare" pe note ANULATE între timp. Aprobarea lor ar rescrie valoarea
 * unei note moarte, iar coada administrației arată cereri imposibil de judecat.
 *
 * Migrare de DATE, nu de schemă: `down()` nu are sens — nu putem ști care cereri caduce fuseseră
 * în așteptare, iar readucerea lor ar reintroduce chiar inconsistența.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('grade_corrections')
            ->whereIn('grade_id', DB::table('grades')->whereNotNull('annulled_at')->select('id'))
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'reviewed_at' => now(),
                'review_note' => 'Cererea a rămas fără obiect: nota a fost anulată.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Ireversibilă intenționat (vezi nota de sus).
    }
};
