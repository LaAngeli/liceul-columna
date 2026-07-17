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
        $studentName = (string) $request->student?->full_name;
        // Link direct în coada tipului respectiv — secretariatul aterizează pe cererea de procesat.
        $queueUrl = '/admin/document-requests?tip='.$request->type->value;

        DB::afterCommit(function () use ($docType, $studentName, $queueUrl): void {
            $this->notifier->byRole(
                [
                    UserRole::Admin->value,
                    UserRole::AdministratorOperational->value,
                ],
                new CatalogNotification(
                    NotificationType::DocumentRequestSubmitted,
                    ['doc_type' => $docType, 'student' => $studentName],
                    $queueUrl,
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

        // Tip DEDICAT (nu StatusChange, care e al statutului academic): familia află CE cerere
        // s-a închis și cu ce decizie, iar linkul aterizează direct pe tabul Cereri al copilului.
        $this->family->send($student, new CatalogNotification(
            NotificationType::DocumentRequestClosed,
            [
                'doc_type' => $request->type->getLabel(),
                'student' => $student->full_name,
                'status' => mb_strtolower($request->status->getLabel()),
            ],
            route('cabinet.student', ['student' => $student->id, 'tab' => 'requests'], false),
        ));
    }

    /**
     * Invariantul de igienă „rând șters ⇒ fișier șters": la dispariția DEFINITIVĂ a cererii
     * (forceDelete — soft delete-ul păstrează fișierele, rândul e restaurabil), PDF-ul ei ȘI
     * justificativul atașat (PII de minor) nu rămân orfani în storage-ul privat — un fișier fără
     * rând-mamă nu mai poate fi găsit la o cerere de ștergere a persoanei vizate (L133).
     */
    public function forceDeleted(DocumentRequest $request): void
    {
        foreach ([$request->pdf_path, $request->attachment_path] as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
