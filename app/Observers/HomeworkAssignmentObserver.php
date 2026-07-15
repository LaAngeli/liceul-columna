<?php

namespace App\Observers;

use App\Enums\CorrectionStatus;
use App\Models\HomeworkAssignment;

/**
 * Igiena corecțiilor la retragerea temei: o cerere în așteptare pe o temă ștearsă „moale" rămâne
 * fără obiect → expiră (administrația nu mai are ce judeca). La ștergerea PERMANENTĂ, corecțiile
 * se curăță prin FK cascade (tema dispare definitiv, împreună cu arhiva ei).
 */
class HomeworkAssignmentObserver
{
    public function deleted(HomeworkAssignment $homework): void
    {
        if ($homework->isForceDeleting()) {
            return;
        }

        $homework->corrections()
            ->where('status', CorrectionStatus::Pending)
            ->get()
            ->each(fn ($correction) => $correction->expire());
    }
}
