<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\GradeCorrection;
use App\Notifications\CatalogNotification;

/**
 * La o cerere NOUĂ de corecție de notă, anunță aprobatorii (prim-vicedirector + director, plus
 * super-admin) — „pe nișa lor" (spec §3.1 / §5).
 */
class GradeCorrectionObserver
{
    public function __construct(private NotifyStaff $notifier) {}

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
}
