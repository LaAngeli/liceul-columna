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
            // Clopoțelul panoului duce direct în coada de validare (un click = pe cerere).
            '/admin/absence-motivations',
        );

        if ($motivation->is_exception) {
            $handlers = User::query()
                ->handlingAudienceDomain(AudienceDomain::Educatie)
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

        $params = [
            'student' => $student->full_name,
            'period' => $motivation->period_start->format('d.m.Y').' – '.$motivation->period_end->format('d.m.Y'),
            'status' => $motivation->status->getLabel(),
        ];

        // Familia (solicitantul): verdictul, cu perioada — motivul respingerii îl citește în
        // cabinet, pe cerere (nu punem textul liber al validatorului într-un email/canal extern).
        $this->family->send($student, new CatalogNotification(
            NotificationType::AbsenceMotivationDecided,
            $params,
            route('cabinet.student', ['student' => $student->id], false),
        ));

        // Dirigintele clasei, când verdictul l-a dat ALTCINEVA (vicedirectorul pe excepții,
        // administrația): absențele clasei lui se schimbă fără acțiunea lui — află, cu link
        // pe fișa cererii. Când chiar el a judecat, nu se auto-anunță.
        $homeroom = $student->homeroomUser();

        if ($homeroom !== null && $homeroom->id !== $motivation->reviewed_by_user_id) {
            $this->notifier->toUser($homeroom, new CatalogNotification(
                NotificationType::AbsenceMotivationDecided,
                $params,
                '/admin/absence-motivations/'.$motivation->id,
            ));
        }
    }
}
