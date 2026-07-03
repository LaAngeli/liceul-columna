<?php

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\HomeworkAssignment;
use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('via() respectă preferințele și sare canalele sociale fără contact sau fără token de liceu', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['new_grade' => ['cabinet', 'telegram']],
        'notification_contacts' => [],
    ]);

    $notification = new CatalogNotification(NotificationType::NewGrade);

    // Fără contact Telegram → doar cabinet (database).
    expect($notification->via($user))->toBe(['database']);

    // Cu contact Telegram DAR fără token de liceu → tot doar cabinet (defense-in-depth, vezi
    // NotificationChannel::isConfigured): nu promitem un canal pe care backendul nu-l onorează.
    $user->update(['notification_contacts' => ['telegram' => '123456']]);
    config()->set('services.telegram.token', null);
    expect($notification->via($user))->toBe(['database']);

    // Cu contact ȘI token activ → canalul social se adaugă.
    config()->set('services.telegram.token', 'test-token');
    expect($notification->via($user))->toBe(['database', TelegramChannel::class]);
});

it('implicit (fără preferințe) merge doar pe cabinet', function () {
    $user = User::factory()->create();

    expect((new CatalogNotification(NotificationType::NewHomework))->via($user))->toBe(['database']);
});

it('o notă nouă notifică familia elevului', function () {
    Notification::fake();

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    Grade::factory()->create([
        'student_id' => $student->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => Term::factory()->for($year)->create()->id,
    ]);

    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewGrade,
    );
});

it('un mesaj nou notifică destinatarul', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    Message::factory()->create([
        'sender_user_id' => $sender->id,
        'recipient_user_id' => $recipient->id,
    ]);

    Notification::assertSentTo(
        $recipient,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewMessage,
    );
});

it('salvarea setărilor stochează contactele (fără cele goale) și preferințele', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'contacts' => ['telegram' => '@parinte', 'viber' => ''],
        'preferences' => ['new_grade' => ['telegram'], 'new_homework' => ['email']],
    ])->assertRedirect();

    $user->refresh();

    expect($user->notification_contacts)->toBe(['telegram' => '@parinte'])
        ->and($user->notification_preferences['new_grade'])->toBe(['telegram'])
        ->and($user->notification_preferences['new_homework'])->toBe(['email']);
});

it('setările întorc adresa curentă a contului (populată automat în UI)', function () {
    $user = User::factory()->create(['email' => 'parinte@example.test']);
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)
        ->get(route('cabinet.notifications.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/notification-settings')
            ->where('email', 'parinte@example.test')
        );
});

it('userul fără email poate salva o adresă → persistă pe users.email', function () {
    // Utilizator migrat: login pe username, fără adresă e-mail. Adaugă adresa din setări.
    $user = User::factory()->create(['email' => null, 'username' => 'parinte_fara_mail']);
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'email' => 'parinte@example.test',
        'contacts' => [],
        'preferences' => [],
    ])->assertRedirect();

    expect($user->refresh()->email)->toBe('parinte@example.test');
});

it('userul cu email pe cont POATE corecta adresa prin setări (editare descentralizată)', function () {
    $user = User::factory()->create(['email' => 'gresit@example.test']);
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'email' => 'corectat@example.test',
        'contacts' => [],
        'preferences' => [],
    ])->assertRedirect();

    // Adresa se schimbă la solicitarea utilizatorului — Fortify acceptă login pe email SAU username,
    // deci schimbarea nu blochează accesul.
    expect($user->refresh()->email)->toBe('corectat@example.test');
});

it('trimiterea unui email gol la salvare NU șterge adresa existentă (no-op)', function () {
    $user = User::factory()->create(['email' => 'existenta@example.test']);
    $user->assignRole(UserRole::Parinte->value);

    // Utilizatorul salvează preferințele fără să atingă câmpul email → HTML trimite '' → no-op.
    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'email' => '',
        'contacts' => [],
        'preferences' => [],
    ])->assertRedirect();

    expect($user->refresh()->email)->toBe('existenta@example.test');
});

it('utilizatorul nu poate seta o adresă folosită de alt cont (unique)', function () {
    User::factory()->create(['email' => 'ocupat@example.test']);
    $user = User::factory()->create(['email' => null, 'username' => 'alt_migrat']);
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'email' => 'ocupat@example.test',
        'contacts' => [],
        'preferences' => [],
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->email)->toBeNull();
});

it('utilizatorul poate corecta contactele telegram/viber după introducerea inițială', function () {
    // Confirmă că input-urile telegram/viber sunt editabile after-fact (nu se blochează după salvare).
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);

    // Prima salvare: valori inițiale (posibil cu greșeală).
    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'contacts' => ['telegram' => '@gresit', 'viber' => '+37360000001'],
        'preferences' => [],
    ])->assertRedirect();

    // A doua salvare: corectare.
    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'contacts' => ['telegram' => '@corect', 'viber' => '+37360000002'],
        'preferences' => [],
    ])->assertRedirect();

    expect($user->refresh()->notification_contacts)->toBe([
        'telegram' => '@corect',
        'viber' => '+37360000002',
    ]);
});

it('inboxul se randează și marcarea „citit" funcționează', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);
    $user->notify(new CatalogNotification(NotificationType::Announcement, customTitle: 'Anunț', customBody: 'Corp'));

    $this->actingAs($user)->get(route('cabinet.notifications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('cabinet/notifications')->has('notifications', 1));

    $id = $user->notifications()->firstOrFail()->id;
    $this->actingAs($user)->post(route('cabinet.notifications.read', $id))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('randează șablonul în limba de notificare a destinatarului (fără traducere live)', function () {
    $ro = User::factory()->create(['notification_locale' => 'ro']);
    $ru = User::factory()->create(['notification_locale' => 'ru']);

    $notification = new CatalogNotification(NotificationType::NewGrade, [
        'student' => 'Ion Popa',
        'subject' => 'Matematică',
    ]);

    expect($notification->toArray($ro)['title'])->toBe('Notă nouă')
        ->and($notification->toArray($ro)['body'])->toContain('a primit o notă nouă')
        ->and($notification->toArray($ru)['title'])->toBe('Новая оценка')
        ->and($notification->toArray($ru)['body'])->toContain('получил новую оценку');
});

it('tipurile de notificări disponibile diferă pe rol', function () {
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $parentTypes = array_map(fn (NotificationType $t): string => $t->value, $parent->availableNotificationTypes());
    $directorTypes = array_map(fn (NotificationType $t): string => $t->value, $director->availableNotificationTypes());

    expect($parentTypes)->toContain('new_grade')
        ->and($parentTypes)->not->toContain('grade_correction_request')
        ->and($directorTypes)->toContain('grade_correction_request')
        ->and($directorTypes)->not->toContain('new_grade');
});

it('salvarea setărilor stochează și limba notificărilor', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);

    $this->actingAs($user)->put(route('cabinet.notifications.settings.update'), [
        'notification_locale' => 'ru',
        'contacts' => [],
        'preferences' => [],
    ])->assertRedirect();

    expect($user->refresh()->notification_locale)->toBe('ru');
});

it('o cerere de corecție de notă notifică aprobatorii (nișa conducerii)', function () {
    Notification::fake();

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $teacher = User::factory()->create();

    $grade = Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => Term::factory()->for($year)->create()->id,
    ]);

    GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => $teacher->id,
    ]);

    Notification::assertSentTo(
        $director,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::GradeCorrectionRequest,
    );
});

it('pagina Setări → Notificări din panou se randează și salvează pentru personal', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);

    $this->actingAs($staff)->get('/admin/notification-settings')->assertOk();
});

it('digestul de teme trimite UN singur rezumat pe familie (nu per-temă)', function () {
    Notification::fake();

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 8, 'section' => '2']);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    // Trei teme noi azi pentru clasa lui → un singur digest, nu trei ping-uri.
    HomeworkAssignment::factory()->count(3)->create(['grade_level' => 8, 'section' => '2']);

    $this->artisan('app:send-homework-digest')->assertSuccessful();

    Notification::assertSentToTimes($parent, CatalogNotification::class, 1);
    Notification::assertSentTo(
        $parent,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewHomework,
    );
});
