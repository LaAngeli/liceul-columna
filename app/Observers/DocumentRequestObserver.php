<?php

namespace App\Observers;

use App\Actions\NotifyStaff;
use App\Actions\NotifyStudentFamily;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\DocumentRequest;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La o cerere tipică NOUĂ (adeverință, transfer...), anunță SECRETARIATUL — administratorul
 * operațional (nișa lui per §3.2) + super-adminul break-glass. Directorul a fost scos din
 * destinatari: matricea lui din Setări (NotificationType::forRole) nu conține
 * DocumentRequestSubmitted, deci primea un zgomot pe care nu-l putea dezactiva/configura.
 * La PROCESARE/RESPINGERE, anunță familia că statutul s-a schimbat (StatusChange).
 */
class DocumentRequestObserver
{
    public function __construct(
        private NotifyStaff $notifier,
        private NotifyStudentFamily $family,
    ) {}

    public function created(DocumentRequest $request): void
    {
        // afterCommit: depunerea rulează într-o tranzacție (cerere + PDF, #37) — dacă generarea PDF
        // pică, rândul se dă înapoi; notificarea nu trebuie să anunțe o cerere anulată. Fără tranzacție
        // activă, closure-ul rulează imediat.
        $docType = $request->type->getLabel();

        DB::afterCommit(function () use ($docType): void {
            $this->notifier->byRole(
                [
                    UserRole::Admin->value,
                    UserRole::AdministratorOperational->value,
                ],
                new CatalogNotification(
                    NotificationType::DocumentRequestSubmitted,
                    ['doc_type' => $docType],
                ),
            );
        });
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

    /**
     * Invariantul de igienă „rând șters ⇒ fișier șters": la dispariția DEFINITIVĂ a cererii
     * (forceDelete — soft delete-ul păstrează fișierul, rândul e restaurabil), PDF-ul ei (PII de
     * minor) nu rămâne orfan în storage-ul privat — un fișier fără rând-mamă nu mai poate fi
     * găsit la o cerere de ștergere a persoanei vizate (L133).
     */
    public function forceDeleted(DocumentRequest $request): void
    {
        if (is_string($request->pdf_path) && $request->pdf_path !== '') {
            Storage::disk('local')->delete($request->pdf_path);
        }
    }
}
