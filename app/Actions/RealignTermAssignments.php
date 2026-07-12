<?php

namespace App\Actions;

use App\Jobs\RecomputeTermAverage;
use App\Models\Absence;
use App\Models\Grade;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;

/**
 * Realiniază notele/absențele la semestre după mutarea granițelor unui semestru (#31): term_id e
 * DERIVAT din dată la introducere ({@see Term::forDate}), dar editarea ulterioară a intervalului
 * lăsa evaluările existente în semestrul vechi — mediile semestriale rămâneau calculate pe o
 * componență care nu mai corespundea datelor.
 *
 * Parcurge toate semestrele anului (granița e comună între vecini), mută rândurile a căror dată a
 * ieșit din intervalul propriului semestru și recalculează mediile ambelor semestre afectate.
 * Rândurile a căror dată nu mai cade în NICIUN semestru definit rămân pe loc (nu orfanizăm).
 */
class RealignTermAssignments
{
    /**
     * @return array{grades: int, absences: int}
     */
    public function run(Term $term): array
    {
        $movedGrades = 0;
        $movedAbsences = 0;

        $siblingTerms = Term::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->get();

        foreach ($siblingTerms as $candidate) {
            Grade::query()
                ->where('term_id', $candidate->id)
                ->where(fn (Builder $query) => $query
                    ->whereDate('graded_on', '<', $candidate->starts_on)
                    ->orWhereDate('graded_on', '>', $candidate->ends_on))
                ->get()
                ->each(function (Grade $grade) use (&$movedGrades): void {
                    $correct = Term::forDate($grade->graded_on);

                    if ($correct === null || (int) $correct->id === (int) $grade->term_id) {
                        return;
                    }

                    $previousTermId = (int) $grade->term_id;
                    // update() prin model: GradeObserver::saved recalculează media termenului NOU,
                    // iar mutarea rămâne în audit (cine/când — §1).
                    $grade->update(['term_id' => $correct->id]);
                    RecomputeTermAverage::dispatch(
                        (int) $grade->student_id,
                        (int) $grade->subject_id,
                        $previousTermId,
                    );
                    $movedGrades++;
                });

            // Absențele nu intră în medii, dar filtrele pe semestru + motivările le folosesc.
            Absence::query()
                ->where('term_id', $candidate->id)
                ->where(fn (Builder $query) => $query
                    ->whereDate('occurred_on', '<', $candidate->starts_on)
                    ->orWhereDate('occurred_on', '>', $candidate->ends_on))
                ->get()
                ->each(function (Absence $absence) use (&$movedAbsences): void {
                    $correct = Term::forDate($absence->occurred_on);

                    if ($correct === null || (int) $correct->id === (int) $absence->term_id) {
                        return;
                    }

                    $absence->update(['term_id' => $correct->id]);
                    $movedAbsences++;
                });
        }

        return ['grades' => $movedGrades, 'absences' => $movedAbsences];
    }
}
