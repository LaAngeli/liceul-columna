<?php

namespace App\Observers;

use App\Actions\ComputeTermAverage;
use App\Models\Grade;

/**
 * Recalculează media semestrială (cache în term_averages) la fiecare schimbare a unei
 * note din panou. Importul legacy folosește query builder (fără evenimente Eloquent),
 * deci nu declanșează asta — pentru el se rulează `app:compute-averages` o dată.
 */
class GradeObserver
{
    public function __construct(private ComputeTermAverage $compute) {}

    public function saved(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function deleted(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function restored(Grade $grade): void
    {
        $this->recompute($grade);
    }

    public function forceDeleted(Grade $grade): void
    {
        $this->recompute($grade);
    }

    private function recompute(Grade $grade): void
    {
        $this->compute->execute(
            (int) $grade->student_id,
            (int) $grade->subject_id,
            (int) $grade->term_id,
        );
    }
}
