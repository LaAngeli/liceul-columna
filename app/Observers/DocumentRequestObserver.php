<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\DocumentRequest;
use App\Notifications\CatalogNotification;

/**
 * La o cerere tipică NOUĂ (adeverință, transfer...), anunță secretariatul — administratorul
 * operațional, directorul și super-adminul (spec §4.3 / §5). La PROCESARE/RESPINGERE, anunță familia
 * că statutul cererii s-a schimbat (bucla de feedback, tipul StatusChange).
 */
class DocumentRequestObserver
{
    public function __construct(
        private NotifyStaff $notifier,
        private NotifyStudentFamily $family,
    ) {}

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

    public function updated(DocumentRequest $request): void
    {
        if (! $request->wasChanged('status') || $request->status === RequestStatus::Pending) {
            return;
        }

        $student = $request->student;

        if ($student === null) {
            return;
        }

        $this->family->send($student, new CatalogNotification(
            NotificationType::StatusChange,
            ['student' => $student->full_name, 'status' => $request->status->getLabel()],
            route('cabinet.student', ['student' => $student->id], false),
        ));
    }
}
