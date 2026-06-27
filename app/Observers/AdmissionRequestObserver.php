<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AdmissionRequest;
use App\Notifications\CatalogNotification;

/**
 * La o cerere de înscriere NOUĂ (de pe site-ul public), anunță administratorul operațional,
 * directorul și super-adminul — „pe nișa lor" de admitere (spec §5).
 */
class AdmissionRequestObserver
{
    public function __construct(private NotifyStaff $notifier) {}

    public function created(AdmissionRequest $request): void
    {
        $this->notifier->byRole(
            [
                UserRole::Admin->value,
                UserRole::Director->value,
                UserRole::AdministratorOperational->value,
            ],
            new CatalogNotification(
                NotificationType::AdmissionRequestSubmitted,
                ['child' => $request->child_name],
            ),
        );
    }
}
