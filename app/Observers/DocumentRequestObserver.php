<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\DocumentRequest;
use App\Notifications\CatalogNotification;

/**
 * La o cerere tipică NOUĂ (adeverință, transfer...), anunță secretariatul — administratorul
 * operațional, directorul și super-adminul (spec §4.3 / §5).
 */
class DocumentRequestObserver
{
    public function __construct(private NotifyStaff $notifier) {}

    public function created(DocumentRequest $request): void
    {
        $this->notifier->byRole(
            [
                UserRole::Admin->value,
                UserRole::Director->value,
                UserRole::AdministratorOperational->value,
            ],
            new CatalogNotification(
                NotificationType::DocumentRequestSubmitted,
                ['doc_type' => $request->type->getLabel()],
            ),
        );
    }
}
