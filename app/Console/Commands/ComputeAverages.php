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
        $this->info('Gata. Medii în term_averages: '.TermAverage::query()->count());

        return self::SUCCESS;
    }
}
