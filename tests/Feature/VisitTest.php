<?php

use App\Enums\AdmissionRequestType;
use App\Mail\AdmissionRequestConfirmation;
use App\Mail\AdmissionRequestNotification;
use App\Models\AdmissionRequest;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('afișează formularul de programare vizită bespoke', function () {
    $this->get('/programeaza-vizita')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/admitere/programare-vizita'));
});

it('salvează o programare de vizită validă cu data aleasă și trimite e-mail', function () {
    Mail::fake();

    $this->post('/programeaza-vizita', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069 123 456',
        'email' => 'maria@example.com',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
        'preferred_time' => now()->addWeek()->format('Y-m-d').'T14:30',
    ])->assertRedirect();

    $this->assertDatabaseHas('admission_requests', [
        'type' => 'visit',
        'child_name' => 'Ion Popescu',
        'status' => 'nou',
    ]);

    Mail::assertQueued(AdmissionRequestNotification::class, fn (AdmissionRequestNotification $mail) => $mail->admission->type === AdmissionRequestType::Visit);

    /* Confirmare către expeditor — pe email-ul oferit, cu același payload. */
    Mail::assertQueued(AdmissionRequestConfirmation::class, function (AdmissionRequestConfirmation $mail) {
        return $mail->hasTo('maria@example.com') && $mail->admission->type === AdmissionRequestType::Visit;
    });
});

it('nu trimite confirmare către expeditor dacă vizita a fost depusă fără email', function () {
    Mail::fake();

    $this->post('/programeaza-vizita', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069 123 456',
        'child_name' => 'Ion Popescu',
        'preferred_time' => now()->addWeek()->format('Y-m-d').'T14:30',
    ])->assertRedirect();

    Mail::assertQueued(AdmissionRequestNotification::class);
    Mail::assertNotQueued(AdmissionRequestConfirmation::class);
});

it('confirmarea vizitei pleacă în limba paginii de pe care s-a făcut POST', function (string $uri, string $expectedLocale, string $expectedSubjectFragment) {
    Mail::fake();

    $this->post($uri, [
        'parent_name' => 'Maria Popescu',
        'phone' => '069 123 456',
        'email' => 'maria@example.com',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
        'preferred_time' => now()->addWeek()->format('Y-m-d').'T14:30',
    ])->assertRedirect();

    Mail::assertQueued(AdmissionRequestConfirmation::class, function (AdmissionRequestConfirmation $mail) use ($expectedLocale, $expectedSubjectFragment) {
        return $mail->locale === $expectedLocale
            && str_contains($mail->envelope()->subject, $expectedSubjectFragment);
    });
})->with([
    'RO root → confirmare în RO' => ['/programeaza-vizita', 'ro', 'Am primit programarea'],
    'RU prefix → confirmare în RU' => ['/ru/zapis-na-vizit', 'ru', 'Мы получили'],
    'EN prefix → confirmare în EN' => ['/en/book-a-visit', 'en', 'We received'],
]);

it('respinge programarea fără dată/oră aleasă', function () {
    $this->post('/programeaza-vizita', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'child_name' => 'Ion Popescu',
    ])->assertSessionHasErrors(['preferred_time']);

    expect(AdmissionRequest::count())->toBe(0);
});

it('respinge programarea cu o dată din trecut', function () {
    $this->post('/programeaza-vizita', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'child_name' => 'Ion Popescu',
        'preferred_time' => '2020-01-01T10:00',
    ])->assertSessionHasErrors(['preferred_time']);

    expect(AdmissionRequest::count())->toBe(0);
});
