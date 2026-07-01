<?php

use App\Enums\AdmissionRequestType;
use App\Enums\UserRole;
use App\Filament\Resources\AdmissionRequests\AdmissionRequestResource;
use App\Mail\AdmissionRequestConfirmation;
use App\Mail\AdmissionRequestNotification;
use App\Models\AdmissionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('afișează formularul public de înscriere', function () {
    $this->get('/inregistrarea-student')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/admitere/inregistrare'));
});

it('salvează o cerere de înmatriculare validă și trimite e-mail către administrație', function () {
    Mail::fake();

    $this->post('/inregistrarea-student', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'email' => 'maria@example.com',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
    ])->assertRedirect();

    $this->assertDatabaseHas('admission_requests', [
        'type' => 'enrollment',
        'child_name' => 'Ion Popescu',
        'parent_name' => 'Maria Popescu',
        'status' => 'nou',
    ]);

    Mail::assertQueued(AdmissionRequestNotification::class, function (AdmissionRequestNotification $mail) {
        return $mail->hasTo((string) config('contact.mailbox'))
            && $mail->admission->type === AdmissionRequestType::Enrollment
            && $mail->admission->child_name === 'Ion Popescu';
    });

    /* Confirmare către expeditor (părinte) — pe email-ul oferit, cu același payload. */
    Mail::assertQueued(AdmissionRequestConfirmation::class, function (AdmissionRequestConfirmation $mail) {
        return $mail->hasTo('maria@example.com')
            && $mail->admission->type === AdmissionRequestType::Enrollment
            && $mail->admission->parent_name === 'Maria Popescu';
    });
});

it('nu trimite confirmare către expeditor dacă nu a oferit email', function () {
    Mail::fake();

    $this->post('/inregistrarea-student', [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
    ])->assertRedirect();

    Mail::assertQueued(AdmissionRequestNotification::class);
    Mail::assertNotQueued(AdmissionRequestConfirmation::class);
});

it('confirmarea pleacă în limba paginii de pe care s-a făcut POST', function (string $uri, string $expectedLocale, string $expectedSubjectFragment) {
    Mail::fake();

    $this->post($uri, [
        'parent_name' => 'Maria Popescu',
        'phone' => '069123456',
        'email' => 'maria@example.com',
        'child_name' => 'Ion Popescu',
        'child_age' => 7,
        'desired_class' => 'Clasa I',
    ])->assertRedirect();

    Mail::assertQueued(AdmissionRequestConfirmation::class, function (AdmissionRequestConfirmation $mail) use ($expectedLocale, $expectedSubjectFragment) {
        return $mail->locale === $expectedLocale
            && str_contains($mail->envelope()->subject, $expectedSubjectFragment);
    });
})->with([
    'RO root → confirmare în RO' => ['/inregistrarea-student', 'ro', 'Am primit cererea'],
    'RU prefix → confirmare în RU' => ['/ru/inregistrarea-student', 'ru', 'Мы получили'],
    'EN prefix → confirmare în EN' => ['/en/inregistrarea-student', 'en', 'We received'],
]);

it('respinge înmatricularea cu nume care conține cifre și cu vârstă incoerentă cu clasa', function () {
    $this->post('/inregistrarea-student', [
        'parent_name' => '12345',
        'phone' => '069123456',
        'child_name' => 'Ion Popescu',
        'child_age' => 3,
        'desired_class' => 'Clasa IX',
    ])->assertSessionHasErrors(['parent_name', 'child_age']);

    expect(AdmissionRequest::count())->toBe(0);
});

it('respinge cererea fără câmpurile obligatorii', function () {
    $this->post('/inregistrarea-student', ['parent_name' => ''])
        ->assertSessionHasErrors(['parent_name', 'phone', 'child_name']);

    expect(AdmissionRequest::count())->toBe(0);
});

it('doar administrația academică vede cererile de înscriere în panou', function (UserRole $role, bool $access) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    $this->actingAs($user);

    expect(AdmissionRequestResource::canAccess())->toBe($access);
})->with([
    'super-admin' => [UserRole::Admin, true],
    'director' => [UserRole::Director, true],
    'prim-vicedirector' => [UserRole::PrimVicedirector, true],
    'administrator operațional' => [UserRole::AdministratorOperational, true],
    'administrator tehnic' => [UserRole::AdministratorTehnic, false],
    'profesor' => [UserRole::Profesor, false],
    'diriginte' => [UserRole::Diriginte, false],
    'părinte' => [UserRole::Parinte, false],
]);
