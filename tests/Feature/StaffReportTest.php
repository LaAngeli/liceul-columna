<?php

use App\Actions\Documents\BuildStaffReportData;
use App\Enums\StaffReportType;
use App\Enums\UserRole;
use App\Filament\Pages\Reports;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->year = AcademicYear::factory()->create();
});

/** Profesor care predă (classA, subjectX); opțional diriginte al unei clase. */
function reportTeacher(SchoolClass $class, Subject $subject, ?SchoolClass $homeroom = null): User
{
    $user = User::factory()->create();
    $user->assignRole($homeroom !== null ? UserRole::Diriginte->value : UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create(['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id]);

    if ($homeroom !== null) {
        $homeroom->update(['homeroom_teacher_id' => $teacher->id]);
    }

    return $user;
}

// ─── Scoping pe server (canGenerate) — cerința centrală §1 ───────────────────────────────

it('profesorul poate genera situația la disciplina LUI, dar nu la altă clasă/disciplină', function () {
    $classA = SchoolClass::factory()->for($this->year)->create();
    $classB = SchoolClass::factory()->for($this->year)->create();
    $chimie = Subject::factory()->create();
    $biologie = Subject::factory()->create();

    $prof = reportTeacher($classA, $chimie);

    // Predă Chimie la classA → poate situația clasei la Chimie + lista clasei A.
    expect(StaffReportType::ClassSubjectSituation->canGenerate($prof, $classA->id, $chimie->id))->toBeTrue()
        ->and(StaffReportType::ClassRoster->canGenerate($prof, $classA->id, null))->toBeTrue()
        // Nu predă Biologie, nici la classB → interzis.
        ->and(StaffReportType::ClassSubjectSituation->canGenerate($prof, $classA->id, $biologie->id))->toBeFalse()
        ->and(StaffReportType::ClassRoster->canGenerate($prof, $classB->id, null))->toBeFalse()
        // Nu e diriginte → nu poate situația COMPLETĂ a clasei.
        ->and(StaffReportType::ClassFullSituation->canGenerate($prof, $classA->id, null))->toBeFalse();
});

it('dirigintele poate genera situația completă a clasei LUI; administrația orice', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();
    $dirig = reportTeacher($class, $subject, homeroom: $class);

    expect(StaffReportType::ClassFullSituation->canGenerate($dirig, $class->id, null))->toBeTrue();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $anyClass = SchoolClass::factory()->for($this->year)->create();

    expect(StaffReportType::ClassFullSituation->canGenerate($director, $anyClass->id, null))->toBeTrue()
        ->and(StaffReportType::ClassSubjectSituation->canGenerate($director, $anyClass->id, $subject->id))->toBeTrue();
});

it('tipurile disponibile urmează rolul (profesor vs diriginte vs administrație)', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();

    $prof = reportTeacher($class, $subject);
    expect(StaffReportType::availableFor($prof))->toBe([StaffReportType::ClassRoster, StaffReportType::ClassSubjectSituation]);

    $dirig = reportTeacher($class, $subject, homeroom: SchoolClass::factory()->for($this->year)->create());
    expect(StaffReportType::availableFor($dirig))->toContain(StaffReportType::ClassFullSituation);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    expect(StaffReportType::availableFor($director))->toHaveCount(3);
});

// ─── Pagina Rapoarte: acces + generare ──────────────────────────────────────────────────

it('pagina Rapoarte e vizibilă personalului academic, ascunsă administratorului tehnic', function () {
    $tehnic = User::factory()->create();
    $tehnic->assignRole(UserRole::AdministratorTehnic->value);
    actingAs($tehnic);
    expect(Reports::canAccess())->toBeFalse();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);
    expect(Reports::canAccess())->toBeTrue();
});

it('generarea unui raport permis produce un PDF; un raport în afara scope-ului e respins', function () {
    $classA = SchoolClass::factory()->for($this->year)->create();
    $classB = SchoolClass::factory()->for($this->year)->create();
    $chimie = Subject::factory()->create();
    Student::factory()->count(2)->create()->each(fn (Student $s) => Enrollment::factory()->for($s)->for($classA)->for($this->year)->create());

    $prof = reportTeacher($classA, $chimie);
    actingAs($prof);

    // Permis: lista clasei A → descărcare PDF.
    Livewire::test(Reports::class)
        ->fillForm(['report_type' => StaffReportType::ClassRoster->value, 'school_class_id' => $classA->id])
        ->call('generate')
        ->assertFileDownloaded();

    // În afara scope-ului: lista clasei B (nu o predă) → respins, fără descărcare.
    Livewire::test(Reports::class)
        ->fillForm(['report_type' => StaffReportType::ClassRoster->value, 'school_class_id' => $classB->id])
        ->call('generate')
        ->assertNoFileDownloaded();
});

// ─── Constructorul de date ──────────────────────────────────────────────────────────────

it('lista de clasă conține elevii înmatriculați activ', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    Student::factory()->count(3)->create()->each(fn (Student $s) => Enrollment::factory()->for($s)->for($class)->for($this->year)->create());

    $data = app(BuildStaffReportData::class)->build(StaffReportType::ClassRoster, $class->id, null);

    expect($data['students'])->toHaveCount(3)
        ->and($data['className'])->not->toBeEmpty();
});
