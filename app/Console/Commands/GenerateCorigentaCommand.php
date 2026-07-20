<?php

namespace App\Console\Commands;

use App\Actions\GenerateCorigentaExams;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Generează în masă intrările de corigență ale unui semestru, pentru elevii al căror statut a fost
 * deja VALIDAT OFICIAL ca „corigent" (vezi {@see GenerateCorigentaExams::forTerm()} — comanda nu
 * decide corigențe, doar materializează hotărârile Consiliului).
 *
 * Fără argument lucrează pe semestrul curent. Idempotentă: se poate relua fără efecte secundare.
 */
#[Signature('app:generate-corigenta {term? : ID-ul semestrului (implicit: semestrul curent)}')]
#[Description('Generează intrările de corigență pentru elevii validați „corigent" într-un semestru')]
class GenerateCorigentaCommand extends Command
{
    public function handle(GenerateCorigentaExams $action): int
    {
        $termId = $this->argument('term');

        $term = $termId !== null
            ? Term::query()->find((int) $termId)
            : SchoolCalendar::currentTerm();

        if ($term === null) {
            $this->error($termId !== null
                ? "Semestrul #{$termId} nu există."
                : 'Nu există un semestru curent. Indicați explicit un ID de semestru.');

            return self::FAILURE;
        }

        $result = $action->forTerm($term);

        $this->info(sprintf(
            'Semestrul %s: %d elevi, %d intrări de corigență.',
            $term->name ?? ('#'.$term->id),
            $result['students'],
            $result['exams'],
        ));

        if ($result['pending'] > 0) {
            // Semnal, nu eroare: acești elevi au medii sub 5, dar Consiliul nu s-a pronunțat încă.
            $this->warn(sprintf(
                '%d elevi au medii sub 5 fără statut validat — de dus în fața Consiliului profesoral.',
                $result['pending'],
            ));
        }

        return self::SUCCESS;
    }
}
