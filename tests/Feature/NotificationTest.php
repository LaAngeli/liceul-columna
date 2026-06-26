<?php

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Grade;
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

it('via() respectă preferințele și sare canalele sociale fără contact', function () {
    $user = User::factory()->create([
        'notification_preferences' => ['new_grade' => ['cabinet', 'telegram']],
        'notification_contacts' => [],
    ]);

    $notification = new CatalogNotification(NotificationType::NewGrade, 'Test');

    // Fără contact Telegram → doar cabinet (database).
    expect($notification->via($user))->toBe(['database']);

    // Cu contact Telegram → se adaugă canalul social.
    $user->update(['notification_contacts' => ['telegram' => '123456']]);
    expect($notification->via($user))->toBe(['database', TelegramChannel::class]);
});

it('implicit (fără preferințe) merge doar pe cabinet', function () {
    $user = User::factory()->create();

    expect((new CatalogNotification(NotificationType::NewHomework, 'X'))->via($user))->toBe(['database']);
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

it('inboxul se randează și marcarea „citit" funcționează', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Parinte->value);
    $user->notify(new CatalogNotification(NotificationType::Announcement, 'Anunț', 'Corp'));

    $this->actingAs($user)->get(route('cabinet.notifications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('cabinet/notifications')->has('notifications', 1));

    $id = $user->notifications()->firstOrFail()->id;
    $this->actingAs($user)->post(route('cabinet.notifications.read', $id))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
