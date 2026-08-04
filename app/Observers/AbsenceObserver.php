<?php

namespace App\Observers;

use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Models\Absence;
use App\Notifications\CatalogNotification;
use App\Support\CabinetLinks;
use App\Support\WorkingDays;

/**
 * Notifică familia la o absență NOUĂ (spec §5). Importul legacy (query builder) nu declanșează.
 */
class AbsenceObserver
{
    public function __construct(private NotifyStudentFamily $notifier) {}

    /**
     * Stabilește termenul de depunere a motivării (occurred_on + 5 zile lucrătoare, §2.1) pentru
     * absențele NOI încă nemotivate — inclusiv cele FĂRĂ STATUT: fereastra familiei curge de la
     * data absenței, nu de la decizia dirigintelui. Importul legacy (query builder) NU trece prin
     * observer — istoricul nu primește termen (e deja consolidat).
     */
    public function creating(Absence $absence): void
    {
        // O absență consemnată RETROACTIV care cade într-o perioadă cu motivare deja APROBATĂ e
        // motivată din start (dovada acoperă o PERIOADĂ, nu o absență anume) — simetric cu
        // EditAbsence::syncMotivationWithDate. Se aplică și absenței FĂRĂ STATUT: dovada există
        // deja, dirigintele nu mai are ce decide. Fără asta, absența introdusă târziu (după
        // aprobare) rămânea nemotivată, primea termen nou și intra în contoarele „nemotivate"/
        // riscul de amânare, deși familia vedea motivarea aprobată pe exact acea dată (#37).
        if ($absence->is_motivated !== true && $absence->hasApprovedMotivationOn($absence->occurred_on)) {
            $absence->is_motivated = true;
        }

        if ($absence->is_motivated !== true && $absence->motivation_deadline === null) {
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
            CabinetLinks::absenceRegister($student->id),
        ));
    }
}
