<?php

/**
 * Audiența granulară a anunțurilor (cerința beneficiarului 2026-07-23): de la „toate familiile"
 * (defaultul istoric, neschimbat) la instituție întreagă, clase, elevi anume cu reach familial,
 * profesorii unei discipline și conturi alese direct. Sursa unică a rezolvării destinatarilor =
 * {@see BroadcastAnnouncement::resolveRecipients} — folosită și de publicare, și de rezumatul din
 * formular, deci numărul confirmat e numărul difuzat.
 */

use App\Actions\BroadcastAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Enums\AudienceReach;
use App\Enums\UserRole;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 7]);
});

/** Elev înmatriculat activ în clasa dată, cu cont propriu + un părinte legat. */
function audienceStudent(mixed $ctx, ?SchoolClass $class = null): array
{
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class ?? $ctx->class)->for($ctx->year)->create([
        'enrolled_on' => '2025-09-01',
        'left_on' => null,
    ]);

    $studentUser = User::factory()->create();
    $studentUser->assignRole(UserRole::Elev->value);
    $student->update(['user_id' => $studentUser->id]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$student->fresh(), $studentUser, $parent];
}

it('audiența „clase" = familiile elevilor activi din clasele alese, nu alte clase, nu plecații', function () {
    [, $inUser, $inParent] = audienceStudent($this);

    $otherClass = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    [, $outUser] = audienceStudent($this, $otherClass);

    // Elev PLECAT din clasa vizată (left_on setat) — nu mai primește anunțurile ei.
    $departed = Student::factory()->create();
    Enrollment::factory()->for($departed)->for($this->class)->for($this->year)->create([
        'enrolled_on' => '2025-09-01',
        'left_on' => '2026-01-15',
    ]);
    $departedUser = User::factory()->create();
    $departedUser->assignRole(UserRole::Elev->value);
    $departed->update(['user_id' => $departedUser->id]);

    $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Classes]);
    $announcement->schoolClasses()->attach($this->class->id);

    $ids = app(BroadcastAnnouncement::class)->resolveRecipients($announcement)->pluck('id');

    expect($ids)->toContain($inUser->id)
        ->and($ids)->toContain($inParent->id)
        ->and($ids)->not->toContain($outUser->id)
        ->and($ids)->not->toContain($departedUser->id);
});

it('audiența „elevi anume" respectă reach-ul: doar părinții → tutorele, nu elevul', function () {
    [$student, $studentUser, $parent] = audienceStudent($this);

    $announcement = Announcement::factory()->create([
        'audience' => AnnouncementAudience::Students,
        'audience_reach' => AudienceReach::Guardians,
    ]);
    $announcement->students()->attach($student->id);

    $ids = app(BroadcastAnnouncement::class)->resolveRecipients($announcement)->pluck('id');

    expect($ids)->toContain($parent->id)
        ->and($ids)->not->toContain($studentUser->id);
});

it('audiența „profesorii disciplinei" = cadrele cu alocări la disciplină, nu restul', function () {
    $subject = Subject::factory()->create();

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'school_class_id' => $this->class->id,
    ]);

    $otherUser = User::factory()->create();
    $otherUser->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $otherUser->id]);

    $announcement = Announcement::factory()->create([
        'audience' => AnnouncementAudience::SubjectTeachers,
        'subject_id' => $subject->id,
    ]);

    $ids = app(BroadcastAnnouncement::class)->resolveRecipients($announcement)->pluck('id');

    expect($ids)->toContain($teacherUser->id)
        ->and($ids)->not->toContain($otherUser->id);
});

it('audiența „conturi anume" = exact conturile alese; suspendații sunt excluși din ORICE audiență', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole(UserRole::Profesor->value);

    $suspended = User::factory()->create(['suspended_at' => now()]);
    $suspended->assignRole(UserRole::Parinte->value);

    $announcement = Announcement::factory()->create(['audience' => AnnouncementAudience::Users]);
    $announcement->users()->attach([$teacher->id, $suspended->id]);

    $ids = app(BroadcastAnnouncement::class)->resolveRecipients($announcement)->pluck('id');

    expect($ids)->toContain($teacher->id)
        ->and($ids)->not->toContain($suspended->id);
});

it('audiența „toată instituția" include personalul; „toate familiile" nu', function () {
    [, $studentUser] = audienceStudent($this);

    $teacherUser = User::factory()->create();
    $teacherUser->assignRole(UserRole::Profesor->value);

    $families = Announcement::factory()->create(['audience' => AnnouncementAudience::Families]);
    $school = Announcement::factory()->create(['audience' => AnnouncementAudience::School]);

    $resolver = app(BroadcastAnnouncement::class);

    expect($resolver->resolveRecipients($families)->pluck('id'))->toContain($studentUser->id)
        ->and($resolver->resolveRecipients($families)->pluck('id'))->not->toContain($teacherUser->id)
        ->and($resolver->resolveRecipients($school)->pluck('id'))->toContain($studentUser->id)
        ->and($resolver->resolveRecipients($school)->pluck('id'))->toContain($teacherUser->id);
});

it('publicarea trimite EXACT audienței rezolvate, cu recipients_count corect', function () {
    Notification::fake();

    [$student, $studentUser, $parent] = audienceStudent($this);
    $outsider = User::factory()->create();
    $outsider->assignRole(UserRole::Parinte->value);

    $announcement = Announcement::factory()->create([
        'audience' => AnnouncementAudience::Students,
        'audience_reach' => AudienceReach::Both,
    ]);
    $announcement->students()->attach($student->id);

    app(BroadcastAnnouncement::class)->publish($announcement);

    expect($announcement->refresh()->recipients_count)->toBe(2);

    Notification::assertSentTo($studentUser, CatalogNotification::class);
    Notification::assertSentTo($parent, CatalogNotification::class);
    Notification::assertNotSentTo($outsider, CatalogNotification::class);
});

it('conducerea creează un anunț pe clase prin formular și pivotul se salvează', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $second = SchoolClass::factory()->for($this->year)->create(['grade_level' => 9]);

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'title' => 'Ședință pe clase',
            'body' => 'Detalii în cabinet.',
            'audience' => AnnouncementAudience::Classes->value,
            'school_classes' => [$this->class->id, $second->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $announcement = Announcement::query()->where('title', 'Ședință pe clase')->firstOrFail();

    expect($announcement->audience)->toBe(AnnouncementAudience::Classes)
        ->and($announcement->schoolClasses()->pluck('school_classes.id')->sort()->values()->all())
        ->toBe(collect([$this->class->id, $second->id])->sort()->values()->all());
});

it('schimbarea audienței pe ciornă golește pivotul vechi la salvare', function () {
    [$student] = audienceStudent($this);

    $announcement = Announcement::factory()->create([
        'audience' => AnnouncementAudience::Students,
        'audience_reach' => AudienceReach::Both,
    ]);
    $announcement->students()->attach($student->id);

    // Autorul se răzgândește: audiența devine „toate familiile" → elevii aleși nu mai au sens.
    $announcement->update(['audience' => AnnouncementAudience::Families, 'audience_reach' => null]);
    AnnouncementResource::syncAudience($announcement, [], [$student->id], []);

    expect($announcement->students()->count())->toBe(0);
});

it('anunțurile existente (pre-migrare) rămân pe „toate familiile" — defaultul coloanei', function () {
    $legacy = Announcement::factory()->create();

    expect($legacy->refresh()->audience)->toBe(AnnouncementAudience::Families);
});
