<?php

namespace App\Console\Commands;

use App\Actions\ComputeTermAverage;
use App\Models\Grade;
use App\Models\TermAverage;
use Illuminate\Console\Command;

class ComputeAverages extends Command
{
    protected $signature = 'app:compute-averages';

    protected $description = 'Recalculează toate mediile semestriale (term_averages) din note — util după importul legacy.';

    public function handle(ComputeTermAverage $compute): int
    {
        $combos = Grade::query()
            ->whereNotNull('value')
            ->select('student_id', 'subject_id', 'term_id')
            ->distinct()
            ->get();

        $this->info('Calcul medii pentru '.$combos->count().' combinații (elev × disciplină × semestru)…');

        $bar = $this->output->createProgressBar($combos->count());
        $bar->start();
        foreach ($combos as $combo) {
            $compute->execute((int) $combo->student_id, (int) $combo->subject_id, (int) $combo->term_id);
            $bar->advance();
        }
        $bar->finish();

        $this->newLine(2);

        $this->info('Gata. Medii în term_averages: '.TermAverage::query()->count().$this->pruneOrphans());

        return self::SUCCESS;
    }

    /**
     * Retrage mediile rămase FĂRĂ NOTE. Comanda doar adăuga/actualiza, deci o medie supraviețuia
     * ștergerii notelor din care fusese calculată — iar media e derivată, nu un fapt de sine
     * stătător: fără note, ea afirmă ceva ce nu s-a întâmplat.
     *
     * Prins pe zona demo (04.08.2026): curățarea alocărilor imposibile a șters notele de Fizică
     * ale unei clase I, dar mediile lor au rămas — copiii de clasa I aveau, în cabinet, medie la
     * Fizică. Regula e generală, nu doar pentru demo: aceeași fantomă apare după orice ștergere de
     * note (anulare în masă, curățare de import).
     *
     * Prudent, deliberat: se șterg doar mediile fără NICIO notă activă rămasă. O disciplină care
     * mai are note (fie ele doar calificative, fără medie numerică) nu se atinge.
     */
    private function pruneOrphans(): string
    {
        $deleted = TermAverage::query()
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('grades')
                ->whereColumn('grades.student_id', 'term_averages.student_id')
                ->whereColumn('grades.subject_id', 'term_averages.subject_id')
                ->whereColumn('grades.term_id', 'term_averages.term_id')
                ->whereNull('grades.deleted_at'))
            ->delete();

        return $deleted > 0 ? " (retrase {$deleted} medii rămase fără note)" : '';
    }
}
