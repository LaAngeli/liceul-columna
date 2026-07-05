<?php

use App\Enums\AdmissionStatus;
use App\Enums\CorrectionStatus;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\EditGrade;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Widgets\ActivityMonitor;
use App\Filament\Widgets\NeedsAttention;
use App\Models\AcademicYear;
use App\Models\AdmissionRequest;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Cache-urile statice memoizate per-request scurg între teste (același proces PHP) — resetăm.
    NeedsAttention::flushCache();
});

it('NeedsAttention (triaj) se afișează administrației și se ascunde profesorului fără fișă/elemente', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);
    expect(NeedsAttention::canView())->toBeTrue();

    // Profesor FĂRĂ fișă Teacher și fără drepturi de aprobare → niciun element de triaj → ascuns.
    $prof = User::factory()->create();
    $prof->assignRole(UserRole::Profesor->value);

    NeedsAttention::flushCache(); // memoizat per-request; testul schimbă utilizatorul curent
    $this->actingAs($prof);
    expect(NeedsAttention::canView())->toBeFalse();
});

it('NeedsAttention (triaj) randează și afișează contul de corecții pending', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    GradeCorrection::factory()->count(3)->create();
    GradeCorrection::factory()->create(['status' => CorrectionStatus::Approved]);

    $this->actingAs($director);

    Livewire::test(NeedsAttention::class)
        ->assertOk()
        ->assertSee('Corecții note')
        ->assertSee('3');
});

it('ActivityMonitor e vizibil oricărui membru al staff-ului (smoke render)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);
    expect(ActivityMonitor::canView())->toBeTrue();

    Livewire::test(ActivityMonitor::class)->assertOk();
});

it('ActivityMonitor e vizibil și profesorului (monitor personal, standard pt. tot staff-ul)', function () {
    $prof = User::factory()->create();
    $prof->assignRole(UserRole::Profesor->value);
    $this->actingAs($prof);

    // Redesign: monitorul e PERSONAL și standard pentru orice membru al staff-ului, nu doar conducerea.
    expect(ActivityMonitor::canView())->toBeTrue();
});

it('AdmissionRequest status e cast ca enum AdmissionStatus', function () {
    $req = AdmissionRequest::create([
        'parent_name' => 'P',
        'phone' => '0700000000',
        'child_name' => 'C',
        'status' => 'nou',
    ]);

    expect($req->status)->toBe(AdmissionStatus::Nou)
        ->and($req->status->color())->toBe('warning')
        ->and($req->status->label())->toBe('Nou');
});

it('logo-ul panoului trimite la homepage-ul site-ului public (homeUrl)', function () {
    // filament()->getHomeUrl() = panel->getHomeUrl() ?? panel->getUrl(); logo-ul din sidebar/topbar
    // îl folosește. Setat pe „/" → click pe logo duce la homepage, nu la /admin.
    expect(Filament\Facades\Filament::getPanel('admin')->getHomeUrl())->toBe('/');
});

it('digestul zilnic de teme notifică familia elevilor din clasa-țintă (cale unică, nu per-temă)', function () {
    Notification::fake();

    $year = AcademicYear::factory()->create(['is_current' => true]);
    $class = SchoolClass::factory()->for($year)->create([
        'grade_level' => 7,
        'section' => 'A',
    ]);
    $studentUser = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $studentUser->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'school_class_id' => $class->id,
        'academic_year_id' => $year->id,
    ]);

    // Crearea temei NU trimite per-temă (observerul a fost dezactivat — §spec, evităm spamul).
    HomeworkAssignment::create([
        'subject_id' => Subject::factory()->create()->id,
        'subject_name' => 'Matematică',
        'author_name' => 'Prof X',
        'grade_level' => 7,
        'section' => 'A',
        'assigned_on' => now(),
        'topic' => 'Geometrie',
        'required_task' => 'Ex. 1-3',
    ]);

    Notification::assertNothingSentTo($studentUser);

    // Digestul zilnic (un singur rezumat/clasă) trimite notificarea NewHomework cu params class/count.
    $this->artisan('app:send-homework-digest')->assertExitCode(0);

    Notification::assertSentTo(
        $studentUser,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewHomework
            && ($n->params['class'] ?? null) === '7-A'
            && ($n->params['count'] ?? null) === '1',
    );
});

it('pagina EditGrade afișează RelationManager-ul de audit pentru rolurile cu drept (smoke)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $grade = Grade::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'subject_id' => Subject::factory()->create()->id,
        'school_class_id' => $class->id,
        'term_id' => Term::factory()->for($year)->create()->id,
        'value' => 8,
    ]);

    $this->actingAs($director);

    // Pagina se încarcă fără să eșueze (RelationManager nu rupe randarea).
    Livewire::test(EditGrade::class, ['record' => $grade->getRouteKey()])
        ->assertOk();
});

it('pagina EditStudent afișează cele 4 RelationManagers academice + audit (smoke)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $student = Student::factory()->create();

    $this->actingAs($director);

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->assertOk();
});

it('pagina EditSchoolClass afișează RelationManager-ul Elevi (smoke)', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);

    $this->actingAs($director);

    Livewire::test(EditSchoolClass::class, ['record' => $class->getRouteKey()])
        ->assertOk();
});
