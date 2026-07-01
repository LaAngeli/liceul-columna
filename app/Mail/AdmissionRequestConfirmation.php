<?php

namespace App\Mail;

use App\Enums\AdmissionRequestType;
use App\Models\AdmissionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mailul de confirmare către PĂRINTE (expeditor) — confirmă că cererea a fost primită,
 * cu rezumat (programare vizită vs. înmatriculare) și semnătura liceului.
 * Randat în limba expeditorului (`->locale()`).
 */
class AdmissionRequestConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AdmissionRequest $admission) {}

    public function envelope(): Envelope
    {
        $key = $this->admission->type === AdmissionRequestType::Visit
            ? 'admission_mail.visit_confirmation_subject'
            : 'admission_mail.enrollment_confirmation_subject';

        return new Envelope(
            subject: __($key, ['child' => $this->admission->child_name]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admission.confirmation');
    }
}
