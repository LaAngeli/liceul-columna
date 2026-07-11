<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\GradeCorrection;
use App\Notifications\CatalogNotification;
use Illuminate\Validation\ValidationException;

/**
 * La o cerere NOUĂ de corecție de notă, anunță aprobatorii (prim-vicedirector + director, plus
 * super-admin) — „pe nișa lor" (spec §3.1 / §5). La soluționare, notificarea urmează CINE e afectat:
 *  - APROBARE → valoarea notei s-a schimbat, deci FAMILIA e anunțată (eveniment de notă, simetric cu
 *    notă nouă / anulare). Solicitantul vede rezultatul în arhivă.
 *  - RESPINGERE → nota NU s-a schimbat și familia n-a fost implicată (corecția e teacher↔conducere) →
 *    anunțăm SOLICITANTUL (cu motivul în arhivă), NU familia. Fără zgomot pentru familie.
 */
class GradeCorrectionObserver
{
    public function __construct(
        private NotifyStaff $notifier,
        private NotifyStudentFamily $family,
    ) {}

    /**
     * Invariant: o notă nu poate avea două cereri de corecție în așteptare simultan (administrația
     * ar judeca două propuneri de valoare pentru aceeași notă). UI-ul ascunde deja acțiunea, dar
     * regula trăiește aici, unde nicio cale — seeder, import, API viitor — nu o poate ocoli.
     */
    public function creating(GradeCorrection $correction): void
    {
        if ($correction->status !== CorrectionStatus::Pending) {
            return;
        }

        $exists = GradeCorrection::query()
            ->where('grade_id', $correction->grade_id)
            ->where('status', CorrectionStatus::Pending)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'grade_id' => __('panel.actions.request_correction.already_pending'),
            ]);
        }
    }

    public function created(GradeCorrection $correction): void
    {
        $this->notifier->byRole(
            [
                UserRole::Admin->value,
                UserRole::Director->value,
                UserRole::PrimVicedirector->value,
            ],
            new CatalogNotification(
                NotificationType::GradeCorrectionRequest,
                [
                    'teacher' => $correction->requestedBy->name,
                    'student' => $correction->grade->student->full_name,
                ],
            ),
        );
    }

    public function updated(GradeCorrection $correction): void
    {
        if (! $correction->wasChanged('status')) {
            return;
        }

        $grade = $correction->grade;
        $student = $grade?->student;

        if ($correction->status === CorrectionStatus::Approved && $grade !== null && $student !== null) {
            // Valoarea notei tocmai s-a schimbat → familia află (eveniment de notă).
            $this->family->send($student, new CatalogNotification(
                NotificationType::GradeCorrected,
                ['student' => $student->full_name, 'subject' => $grade->subject->name],
                route('cabinet.student', ['student' => $student->id], false),
            ));

            return;
        }

        if ($correction->status === CorrectionStatus::Rejected) {
            // Solicitantul (profesorul) trebuie să afle verdictul + motivul (arhivă), altfel redepune
            // orbește. Familia n-a fost implicată și nota n-a fost atinsă → nu o notificăm.
            $this->notifier->toUser(
                $correction->requestedBy,
                new CatalogNotification(
                    NotificationType::GradeCorrectionRejected,
                    ['student' => $student !== null ? $student->full_name : ''],
                ),
            );
        }
    }
}
