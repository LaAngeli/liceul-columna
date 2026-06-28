<?php

namespace App\Observers;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Models\Absence;
use App\Notifications\CatalogNotification;
use App\Support\WorkingDays;

/**
 * Notifică familia la o absență NOUĂ (spec §5). Importul legacy (query builder) nu declanșează.
 */
class AbsenceObserver
{
    public function __construct(private NotifyStudentFamily $notifier) {}

    /**
     * Stabilește termenul de depunere a motivării (occurred_on + 5 zile lucrătoare, §2.1) pentru
     * absențele NOI nemotivate. Importul legacy (query builder) NU trece prin observer — istoricul
     * nu primește termen (e deja consolidat).
     */
    public function creating(Absence $absence): void
    {
        if (! $absence->is_motivated && $absence->motivation_deadline === null) {
            $absence->motivation_deadline = WorkingDays::add($absence->occurred_on, 5);
        }
    }

    public function created(Absence $absence): void
    {
        $student = $absence->student;

        if ($student === null) {
            return;
        }

        $this->notifier->send($student, new CatalogNotification(
            NotificationType::NewAbsence,
            ['student' => $student->full_name],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }
}
