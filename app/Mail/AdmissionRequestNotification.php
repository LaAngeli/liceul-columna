<?php

namespace App\Mail;

use App\Enums\AdmissionRequestType;
use App\Models\AdmissionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mailul către secretariat/administrație, cu datele unei cereri din zona de admitere.
 * Subiectul și conținutul variază după tip (programare vizită vs. cerere de înmatriculare).
 * `replyTo` = părintele, ca personalul să răspundă direct.
 */
class AdmissionRequestNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AdmissionRequest $admission) {}

    public function envelope(): Envelope
    {
        $replyTo = [];

        if ($this->admission->email) {
            $replyTo[] = new Address($this->admission->email, $this->admission->parent_name);
        }

        $key = $this->admission->type === AdmissionRequestType::Visit
            ? 'admission_mail.visit_subject'
            : 'admission_mail.enrollment_subject';

        return new Envelope(
            subject: __($key, ['child' => $this->admission->child_name]),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admission.notification');
    }
}
