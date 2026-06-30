<?php

namespace App\Observers;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Jobs\RecomputeTermAverage;
use App\Models\Grade;
use App\Notifications\CatalogNotification;

/**
 * Recalculează media semestrială (cache în term_averages) la fiecare schimbare a unei
 * note din panou și notifică familia la o notă NOUĂ (spec §5). Recalculul pleacă pe coadă
 * ({@see RecomputeTermAverage}), ca salvarea să nu aștepte calculul. Importul legacy folosește
 * query builder (fără evenimente Eloquent), deci nu declanșează nici recalculul, nici notificarea.
 */
class GradeObserver
{
    public function __construct(
        private NotifyStudentFamily $notifier,
    ) {}

    public function created(Grade $grade): void
    {
        $student = $grade->student;

        if ($student === null) {
            return;
        }

        $this->notifier->send($student, new CatalogNotification(
            NotificationType::NewGrade,
            [
                'student' => $student->full_name,
                'subject' => $grade->subject->name,
            ],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }

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
        RecomputeTermAverage::dispatch(
            (int) $grade->student_id,
            (int) $grade->subject_id,
            (int) $grade->term_id,
        );
    }
}
