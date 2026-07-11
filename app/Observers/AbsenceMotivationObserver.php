<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\AudienceDomain;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use App\Models\User;
use App\Notifications\CatalogNotification;

/**
 * La o cerere NOUĂ de motivare a absențelor, anunță VALIDATORUL ei real (spec §2.1 / §5):
 * cererile normale → dirigintele clasei; EXCEPȚIILE (tardive) → vicedirectorul pe educație —
 * dirigintele nu le poate aproba ({@see AbsenceMotivation::canBeReviewedBy}), deci ping-ul lui
 * era zgomot fără acțiune, iar aprobatorul real nu afla. La VALIDARE/RESPINGERE, închide bucla
 * de feedback: anunță familia că statutul cererii s-a schimbat (spec §5, tipul StatusChange).
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

        $notification = new CatalogNotification(
            NotificationType::AbsenceMotivationSubmitted,
            ['student' => $student->full_name],
        );

        if ($motivation->is_exception) {
            $handlers = User::query()
                ->whereJsonContains('audience_domains', AudienceDomain::Educatie->value)
                ->get();

            if ($handlers->isNotEmpty()) {
                foreach ($handlers as $handler) {
                    $this->notifier->toUser($handler, $notification);
                }

                return;
            }
            // Nimeni nu poartă domeniul „educație" → cădem pe diriginte, ca cererea să nu
            // rămână complet tăcută (el o vede, chiar dacă nu o poate aproba).
        }

        $this->notifier->toUser($student->homeroomUser(), $notification);
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
