<?php

namespace App\Console\Commands;

use App\Actions\ComputeTermAverage;
use App\Models\Grade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrare de DATE (unică): notele individuale cu zecimale devin întregi.
 *
 * Nota e un număr ÎNTREG pe scala 1–10; zecimalele aparțin exclusiv mediilor (§2.4, sutimi fără
 * rotunjire). Dovada că așa lucrează școala e chiar în datele ei: din cele 52.228 de note importate
 * din sistemul vechi, NICIUNA nu are zecimale. Valorile de tipul 6,5 au apărut ulterior, din două
 * generatoare de date demo (`app:seed-demo-zone`, `app:simulate-demo-activity`), care compuneau
 * nota ca `random_int(50, 100) / 10` — corectate la sursă.
 *
 * Comanda:
 *   • DRY-RUN implicit (`--apply` scrie efectiv), cu raport pe valori;
 *   • rotunjește la cel mai apropiat întreg (6,5 → 7) și mărginește la 1–10;
 *   • RECALCULEAZĂ mediile semestriale atinse — altfel media ar rămâne cea derivată din valorile
 *     vechi și ar contrazice notele afișate sub ea;
 *   • idempotentă: a doua rulare nu mai găsește nimic.
 *
 * Scrie prin query builder, deci ocolește garda din {@see Grade} — deliberat: garda
 * există tocmai ca să respingă valorile pe care comanda asta le repară.
 */
class FixDecimalGrades extends Command
{
    protected $signature = 'app:fix-decimal-grades {--apply : Scrie efectiv (implicit: doar raportează)}';

    protected $description = 'Rotunjește notele cu zecimale la întreg și recalculează mediile atinse';

    public function handle(ComputeTermAverage $computeAverage): int
    {
        $apply = (bool) $this->option('apply');

        $rows = DB::table('grades')
            ->whereNotNull('value')
            ->whereRaw('value <> ROUND(value)')
            ->get(['id', 'student_id', 'subject_id', 'term_id', 'value']);

        if ($rows->isEmpty()) {
            $this->info('Nicio notă cu zecimale — toate sunt deja întregi.');

            return self::SUCCESS;
        }

        /** @var array<string, int> $changes  „6.5 → 7" => de câte ori */
        $changes = [];
        /** @var array<string, array{student: int, subject: int, term: int}> $affected */
        $affected = [];

        foreach ($rows as $row) {
            $old = (float) $row->value;
            $new = (int) max(1, min(10, round($old)));

            $changes[rtrim(rtrim(number_format($old, 2, '.', ''), '0'), '.').' → '.$new] ??= 0;
            $changes[rtrim(rtrim(number_format($old, 2, '.', ''), '0'), '.').' → '.$new]++;

            $affected[$row->student_id.'-'.$row->subject_id.'-'.$row->term_id] = [
                'student' => (int) $row->student_id,
                'subject' => (int) $row->subject_id,
                'term' => (int) $row->term_id,
            ];

            if ($apply) {
                // Doar coloana `value`: nu atingem `updated_at` pe un rând de istoric.
                DB::table('grades')->where('id', $row->id)->update(['value' => $new]);
            }
        }

        ksort($changes);
        $this->table(
            ['Corecție', 'Note'],
            array_map(static fn (string $k, int $v): array => [$k, $v], array_keys($changes), $changes),
        );

        $this->line('Medii semestriale atinse: '.count($affected));

        if (! $apply) {
            $this->warn('DRY-RUN — nimic nu a fost scris. '.$rows->count().' note ar fi rotunjite.');
            $this->line('Rulează din nou cu --apply pentru a aplica.');

            return self::SUCCESS;
        }

        // Mediile se recalculează DUPĂ ce toate notele sunt corectate — altfel prima medie ar fi
        // calculată dintr-un amestec de valori vechi și noi.
        $recomputed = 0;
        foreach ($affected as $key) {
            $computeAverage->execute($key['student'], $key['subject'], $key['term']);
            $recomputed++;
        }

        $this->info("Rotunjite: {$rows->count()} note · medii recalculate: {$recomputed}.");

        return self::SUCCESS;
    }
}
