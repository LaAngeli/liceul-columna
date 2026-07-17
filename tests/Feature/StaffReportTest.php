<?php

use App\Actions\Documents\BuildStaffReportData;
use App\Enums\StaffReportType;
use App\Enums\UserRole;
use App\Filament\Pages\Reports;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TermAverage;
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
    expect(StaffReportType::availableFor($prof))
        ->toBe([StaffReportType::ClassRoster, StaffReportType::ClassSubjectSituation, StaffReportType::GradeDistribution]);

    $dirig = reportTeacher($class, $subject, homeroom: SchoolClass::factory()->for($this->year)->create());
    expect(StaffReportType::availableFor($dirig))->toContain(StaffReportType::ClassFullSituation)
        ->toContain(StaffReportType::PromotionRate)
        ->not->toContain(StaffReportType::SchoolOverview);

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    expect(StaffReportType::availableFor($director))->toHaveCount(count(StaffReportType::cases()));
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

    // Permis: lista clasei A → descărcare PDF (fluxul navigatorului: categorie → raport → parametri).
    Livewire::test(Reports::class)
        ->call('openCategory', 'elevi')
        ->call('openReport', StaffReportType::ClassRoster->value)
        ->set('data.school_class_id', $classA->id)
        ->call('generate')
        ->assertFileDownloaded();

    // În afara scope-ului: lista clasei B (nu o predă) → respins, fără descărcare.
    Livewire::test(Reports::class)
        ->call('openCategory', 'elevi')
        ->call('openReport', StaffReportType::ClassRoster->value)
        ->set('data.school_class_id', $classB->id)
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

// ─── Navigatorul pe categorii + rapoartele noi (2026-07-17) ──────────────────────────────

it('categoriile navigatorului urmează rolul: profesorul nu vede Profesori/Administrative', function () {
    $classA = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    expect(collect(StaffReportType::categoriesFor($admin))->pluck('value')->all())
        ->toBe(['elevi', 'evaluare', 'frecventa', 'clase', 'profesori', 'administrative']);

    $profesor = reportTeacher($classA, $subject);
    expect(collect(StaffReportType::categoriesFor($profesor))->pluck('value')->all())
        ->toBe(['elevi', 'evaluare']);

    $diriginte = reportTeacher($classA, $subject, $classA);
    expect(collect(StaffReportType::categoriesFor($diriginte))->pluck('value')->all())
        ->toBe(['elevi', 'evaluare', 'frecventa', 'clase']);
});

it('rapoartele de școală sunt doar ale administrației; dirigintele analizează doar clasa lui', function () {
    $classA = SchoolClass::factory()->for($this->year)->create();
    $classB = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();

    $diriginte = reportTeacher($classA, $subject, $classA);

    expect(StaffReportType::TeacherActivity->canGenerate($diriginte, null, null))->toBeFalse()
        ->and(StaffReportType::SchoolOverview->canGenerate($diriginte, null, null))->toBeFalse()
        ->and(StaffReportType::StudentRanking->canGenerate($diriginte, $classA->id, null))->toBeTrue()
        ->and(StaffReportType::StudentRanking->canGenerate($diriginte, $classB->id, null))->toBeFalse()
        ->and(StaffReportType::PromotionRate->canGenerate($diriginte, $classA->id, null))->toBeTrue()
        ->and(StaffReportType::AbsenceStatistics->canGenerate($diriginte, $classB->id, null))->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::AdministratorOperational->value);
    expect(StaffReportType::TeacherActivity->canGenerate($admin, null, null))->toBeTrue()
        ->and(StaffReportType::SchoolOverview->canGenerate($admin, null, null))->toBeTrue();
});

it('clasamentul ordonează descrescător după medie, cu elevii fără medie la coadă', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $subject = Subject::factory()->create();

    $weak = Student::factory()->create(['last_name' => 'AAA', 'first_name' => 'Slab']);
    $strong = Student::factory()->create(['last_name' => 'BBB', 'first_name' => 'Tare']);
    $none = Student::factory()->create(['last_name' => 'CCC', 'first_name' => 'FaraMedie']);

    foreach ([$weak, $strong, $none] as $student) {
        Enrollment::factory()->for($student)->for($class)->for($this->year)->create();
    }

    TermAverage::query()->create(['student_id' => $weak->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 6.5]);
    TermAverage::query()->create(['student_id' => $strong->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 9.8]);

    $data = app(BuildStaffReportData::class)->build(StaffReportType::StudentRanking, $class->id, null);

    expect($data['rows'][0]['name'])->toContain('BBB')
        ->and($data['rows'][0]['rank'])->toBe(1)
        ->and($data['rows'][1]['rank'])->toBe(2)
        ->and($data['rows'][2]['rank'])->toBeNull()
        ->and($data['periodLabel'])->toContain($term->name)
        ->and($data['generatedBy'])->not->toBe('');
});

it('distribuția notelor numără corect pe tranșe și calculează media', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $subject = Subject::factory()->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();

    foreach ([9, 9, 7, 4] as $value) {
        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'term_id' => $term->id,
            'value' => $value,
        ]);
    }

    $data = app(BuildStaffReportData::class)->build(StaffReportType::GradeDistribution, $class->id, $subject->id);

    expect($data['total'])->toBe(4)
        ->and($data['buckets'][9])->toBe(2)
        ->and($data['buckets'][7])->toBe(1)
        ->and($data['buckets'][4])->toBe(1)
        ->and($data['mean'])->toBe(7.25);
});

it('promovabilitatea numără statuturile și disciplinele cu restanțe', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $subject = Subject::factory()->create(['name' => 'Matematica']);

    $ok = Student::factory()->create();
    $failing = Student::factory()->create();
    foreach ([$ok, $failing] as $student) {
        Enrollment::factory()->for($student)->for($class)->for($this->year)->create();
    }

    TermAverage::query()->create(['student_id' => $ok->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 8]);
    TermAverage::query()->create(['student_id' => $failing->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 4]);

    $data = app(BuildStaffReportData::class)->build(StaffReportType::PromotionRate, $class->id, null);

    expect($data['statusCounts']['promovat'])->toBe(1)
        ->and($data['statusCounts']['corigent'])->toBe(1)
        ->and($data['promotionPercent'])->toBe(50)
        ->and($data['failingSubjects'])->toHaveKey('Matematica');
});

it('sinteza școlii agregă pe clasele anului curent, fără PII de elevi', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $term = Term::factory()->for($this->year)->create(['is_current' => true]);
    $subject = Subject::factory()->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($this->year)->create();
    TermAverage::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'school_class_id' => $class->id, 'term_id' => $term->id, 'value' => 4.5]);

    $data = app(BuildStaffReportData::class)->build(StaffReportType::SchoolOverview, null, null);

    $row = collect($data['rows'])->firstWhere('students', 1);
    expect($row)->not->toBeNull()
        ->and($row['failing'])->toBe(1)
        ->and($data['totals']['students'])->toBe(1)
        ->and(StaffReportType::SchoolOverview->containsStudentPii())->toBeFalse();
});

it('generarea unui raport nou (clasament) produce PDF pentru diriginte', function () {
    $class = SchoolClass::factory()->for($this->year)->create();
    $subject = Subject::factory()->create();
    $diriginte = reportTeacher($class, $subject, $class);

    actingAs($diriginte);

    Livewire::test(Reports::class)
        ->call('openCategory', 'elevi')
        ->call('openReport', StaffReportType::StudentRanking->value)
        ->set('data.school_class_id', $class->id)
        ->call('generate')
        ->assertHasNoErrors();
});
