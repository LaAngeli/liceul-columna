<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use App\Notifications\CatalogNotification;

/**
 * La o cerere NOUĂ de motivare a absențelor, anunță dirigintele clasei elevului (cel care
 * validează) — „pe nișa lui" (spec §2.1 / §5). La VALIDARE/RESPINGERE, închide bucla de feedback:
 * anunță familia că statutul cererii s-a schimbat (spec §5, tipul StatusChange).
 */
class AbsenceMotivationObserver
{
    public function __construct(
        private NotifyStaff $notifier,
        private NotifyStudentFamily $family,
    ) {}

    public function created(AbsenceMotivation $motivation): void
    {
        $student = $motivation->student;

        if ($student === null) {
            return;
        }

        $this->notifier->toUser(
            $student->homeroomUser(),
            new CatalogNotification(
                NotificationType::AbsenceMotivationSubmitted,
                ['student' => $student->full_name],
            ),
        );
    }

    public function updated(AbsenceMotivation $motivation): void
    {
        if (! $motivation->wasChanged('status') || $motivation->status === RequestStatus::Pending) {
            return;
        }

        $student = $motivation->student;

        if ($student === null) {
            return;
        }

        $this->family->send($student, new CatalogNotification(
            NotificationType::StatusChange,
            ['student' => $student->full_name, 'status' => $motivation->status->getLabel()],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }
}
