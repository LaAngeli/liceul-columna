<?php

namespace App\Observers;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Models\Absence;
use App\Notifications\CatalogNotification;

/**
 * Notifică familia la o absență NOUĂ (spec §5). Importul legacy (query builder) nu declanșează.
 */
class AbsenceObserver
{
    public function __construct(private NotifyStudentFamily $notifier) {}

    public function created(Absence $absence): void
    {
        $student = $absence->student;

        if ($student === null) {
            return;
        }

        $subject = $absence->subject?->name;

        $this->notifier->send($student, new CatalogNotification(
            NotificationType::NewAbsence,
            'Absență nouă · '.$student->full_name,
            'A fost înregistrată o absență'.($subject !== null ? ' la '.$subject : '').'.',
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }
}
