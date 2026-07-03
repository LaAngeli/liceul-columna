<?php

use App\Mail\ContactConfirmation;
use App\Mail\ContactNotification;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('trimite mesajul: e-mail către școală (replyTo expeditor) + confirmare către expeditor, apoi redirect', function () {
    Mail::fake();

    $this->post('/contacte', [
        'name' => 'Ion Popescu',
        'email' => 'ion@example.com',
        'phone' => '069123456',
        'subject' => 'Întrebare admitere',
        'message' => 'Aș dori detalii despre înscrierea în clasa I.',
        'consent' => true,
    ])
        ->assertRedirect('/contacte/multumim')
        ->assertSessionHas('contact.name', 'Ion Popescu');

    Mail::assertQueued(ContactNotification::class, fn (ContactNotification $m): bool => $m->hasTo(config('contact.mailbox')));
    Mail::assertQueued(ContactConfirmation::class, fn (ContactConfirmation $m): bool => $m->hasTo('ion@example.com'));
});

it('respinge formularul cu câmpuri invalide sau fără consimțământ', function () {
    Mail::fake();

    $this->from('/contacte')
        ->post('/contacte', ['name' => 'Ion', 'email' => 'nu-i-email', 'message' => 'scurt'])
        ->assertSessionHasErrors(['email', 'subject', 'message', 'consent']);

    Mail::assertNothingQueued();
});

it('honeypot: dacă botul completează câmpul ascuns, pare succes dar nu se trimite nimic', function () {
    Mail::fake();

    $this->post('/contacte', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'subject' => 'spam',
        'message' => 'mesaj de spam destul de lung',
        'consent' => true,
        'website' => 'http://spam.example',
    ])->assertRedirect('/contacte/multumim');

    Mail::assertNothingQueued();
});

it('pagina de mulțumim nu e accesibilă direct, fără o trimitere reușită', function () {
    $this->get('/contacte/multumim')->assertRedirect('/contacte');
});

it('pagina de mulțumim se afișează după trimitere (flash din sesiune)', function () {
    $this->withSession(['contact.name' => 'Maria'])
        ->get('/contacte/multumim')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/contact-multumim')->where('name', 'Maria'));
});

it('confirmarea de contact pleacă în limba paginii (POST păstrează prefixul de URL)', function (string $uri, string $expectedLocale, string $expectedSubjectFragment) {
    Mail::fake();

    $this->post($uri, [
        'name' => 'Maria Popescu',
        'email' => 'maria@example.com',
        'phone' => '069123456',
        'subject' => 'Test',
        'message' => 'Mesaj de test cu suficiente caractere pentru validare.',
        'consent' => true,
    ])->assertRedirect();

    Mail::assertQueued(ContactConfirmation::class, function (ContactConfirmation $mail) use ($expectedLocale, $expectedSubjectFragment) {
        return $mail->locale === $expectedLocale
            && str_contains($mail->envelope()->subject, $expectedSubjectFragment);
    });
})->with([
    'RO root' => ['/contacte', 'ro', 'Am primit mesajul'],
    'RU prefix' => ['/ru/kontakty', 'ru', 'Мы получили'],
    'EN prefix' => ['/en/contact', 'en', 'We received'],
]);
