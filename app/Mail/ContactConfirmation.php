<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mailul de confirmare către EXPEDITOR — confirmă că mesajul a fost primit,
 * cu un rezumat și semnătura liceului. Randat în limba expeditorului (`->locale()`).
 */
class ContactConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, phone: ?string, subject: string, message: string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('contact_mail.confirmation_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact.confirmation');
    }
}
