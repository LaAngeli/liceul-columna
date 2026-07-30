<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corecțiile de teme devin DIRECTE (decizia beneficiarului, 2026-07-31): profesorul-autor și
     * dirigintele clasei își aplică singuri corecția, fără cerere → aprobare. Tabelul rămâne ca
     * REGISTRU al corecțiilor aplicate (cine, când, vechi → nou):
     *
     *  - `reason` devine opțional — corecția directă nu mai cere motivare; rândurile istorice
     *    (fluxul vechi) și-l păstrează;
     *  - cererile rămase ÎN AȘTEPTARE se APLICĂ retroactiv: autorul le-a vrut, iar noua regulă
     *    spune că nu mai are nevoie de aprobarea nimănui — nu le lăsăm suspendate pe veci.
     *    Prin query builder, deliberat: fără observers/notificări; rândul corecției rămâne
     *    documentul schimbării (vechi → nou), nota de sistem explică verdictul.
     */
    public function up(): void
    {
        Schema::table('homework_corrections', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
        });

        $pending = DB::table('homework_corrections')->where('status', 'pending')->get();

        foreach ($pending as $correction) {
            $changes = array_filter([
                'topic' => $correction->new_topic,
                'required_task' => $correction->new_required_task,
                'optional_task' => $correction->new_optional_task,
            ], fn (?string $value): bool => $value !== null);

            if ($changes !== []) {
                DB::table('homework_assignments')
                    ->where('id', $correction->homework_assignment_id)
                    ->update([...$changes, 'updated_at' => now()]);
            }

            DB::table('homework_corrections')->where('id', $correction->id)->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'review_note' => 'Aplicată automat: corecțiile de teme nu mai necesită aprobare (31.07.2026).',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('homework_corrections', function (Blueprint $table) {
            $table->text('reason')->nullable(false)->change();
        });
    }
};
