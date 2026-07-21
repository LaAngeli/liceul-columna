<?php

namespace App\Console\Commands;

use App\Actions\RealignTermAssignments;
use App\Models\Term;
use App\Observers\TermObserver;
use Illuminate\Console\Command;

/**
 * Realiniază notele/absențele la semestrul DAT DE DATĂ ({@see Term::forDate}) — calea manuală a
 * aceleiași logici pe care {@see TermObserver} o rulează la mutarea intervalelor.
 * Utilă după un import legacy care a lăsat evaluări cu term_id inconsecvent cu data lor (semnalul
 * de drift din pagina Semestre). Recalculează mediile ambelor semestre afectate (pe coadă).
 *
 * `--dry-run` raportează ce s-ar muta, fără nicio scriere. Idempotentă: a doua rulare mută 0.
 */
class RealignTerms extends Command
{
    protected $signature = 'app:realign-terms {--dry-run : Doar raportează ce s-ar muta, fără a scrie}';

    protected $description = 'Realiniază notele/absențele la semestrul dat de dată (Term::forDate); recalculează mediile afectate.';

    public function handle(RealignTermAssignments $realigner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $yearIds = Term::query()
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->distinct()
            ->pluck('academic_year_id');

        if ($yearIds->isEmpty()) {
            $this->warn('Niciun semestru cu interval definit — nimic de realiniat.');

            return self::SUCCESS;
        }

        $totalGrades = 0;
        $totalAbsences = 0;
        $noTargetGrades = 0;
        $noTargetAbsences = 0;

        foreach ($yearIds as $yearId) {
            // Un singur termen per an ajunge: run()/preview() parcurg TOȚI frații anului.
            $term = Term::query()
                ->where('academic_year_id', $yearId)
                ->whereNotNull('starts_on')
                ->orderBy('number')
                ->first();

            if ($term === null) {
                continue;
            }

            if ($dryRun) {
                $preview = $realigner->preview($term);
                $totalGrades += $preview['grades'];
                $totalAbsences += $preview['absences'];
                $noTargetGrades += $preview['grades_no_target'];
                $noTargetAbsences += $preview['absences_no_target'];

                $this->line(sprintf(
                    'An %d: ar muta %d note + %d absențe (rămân %d note + %d absențe fără semestru-țintă).',
                    $yearId,
                    $preview['grades'],
                    $preview['absences'],
                    $preview['grades_no_target'],
                    $preview['absences_no_target'],
                ));
            } else {
                $result = $realigner->run($term);
                $totalGrades += $result['grades'];
                $totalAbsences += $result['absences'];

                $this->line(sprintf('An %d: mutat %d note + %d absențe.', $yearId, $result['grades'], $result['absences']));
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn(sprintf(
                'DRY-RUN — s-ar muta %d note + %d absențe. Rămân fără semestru-țintă (datate în afara oricărui semestru): %d note + %d absențe — anomalii reale de dată, de revizuit uman.',
                $totalGrades,
                $totalAbsences,
                $noTargetGrades,
                $noTargetAbsences,
            ));
            $this->line('Rulează fără `--dry-run` pentru a aplica.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Realiniere finalizată: %d note + %d absențe mutate. Mediile semestriale afectate se recalculează pe coadă (RecomputeTermAverage).',
            $totalGrades,
            $totalAbsences,
        ));

        return self::SUCCESS;
    }
}
