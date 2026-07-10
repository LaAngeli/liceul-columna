<?php

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    Storage::fake('local');

    $this->year = AcademicYear::factory()->create();
    $this->student = Student::factory()->create();

    // Diriginte al clasei elevului.
    $dirigUser = User::factory()->create();
    $dirigUser->assignRole(UserRole::Diriginte->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $dirigUser->id]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['homeroom_teacher_id' => $this->teacher->id]);
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    $this->dirigUser = $dirigUser;
    $this->actingAs($dirigUser);
});

function unmotivatedAbsence(int $studentId, int $classId): Absence
{
    return Absence::factory()->create([
        'student_id' => $studentId,
        'school_class_id' => $classId,
        'subject_id' => null,
        'is_motivated' => false,
        'occurred_on' => '2025-10-15',
    ]);
}

it('acțiunea „Motivează" e vizibilă pe o absență nemotivată și ascunsă pe una motivată', function () {
    $unmotivated = unmotivatedAbsence($this->student->id, $this->class->id);
    $motivated = Absence::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'is_motivated' => true,
        'occurred_on' => '2025-10-16',
    ]);

    Livewire::test(ListAbsences::class)
        ->assertTableActionVisible('motivate', $unmotivated)
        ->assertTableActionHidden('motivate', $motivated);
});

it('motivarea cu dovadă marchează absențele din interval + stochează privat justificativul', function () {
    $absence = unmotivatedAbsence($this->student->id, $this->class->id);
    // A doua absență a aceluiași elev, în interval — o singură dovadă le acoperă pe ambele.
    $inRange = Absence::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'is_motivated' => false,
        'occurred_on' => '2025-10-17',
    ]);

    Livewire::test(ListAbsences::class)
        ->callTableAction('motivate', $absence, data: [
            'period_start' => '2025-10-15',
            'period_end' => '2025-10-17',
            'reason' => 'Certificat medical',
            'document_path' => UploadedFile::fake()->create('certificat.pdf', 120, 'application/pdf'),
        ])
        ->assertHasNoTableActionErrors();

    $motivation = AbsenceMotivation::query()->firstOrFail();

    expect($motivation->status->value)->toBe('approved')
        ->and($motivation->reviewed_by_user_id)->toBe($this->dirigUser->id)
        ->and($motivation->document_path)->not->toBeNull()
        ->and($absence->refresh()->is_motivated)->toBeTrue()
        ->and($inRange->refresh()->is_motivated)->toBeTrue();

    Storage::disk('local')->assertExists((string) $motivation->document_path);
});

it('administratorul operațional vede absențele dar NU poate motiva (nu administrează catalogul)', function () {
    $absence = unmotivatedAbsence($this->student->id, $this->class->id);

    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value); // isAdministrator (vede tot), dar fără fișă + fără canAdministerCatalog
    $this->actingAs($ao);

    Livewire::test(ListAbsences::class)
        ->assertTableActionHidden('motivate', $absence);
});

/**
 * Decizie de produs (10.07.2026, în urma auditului live): motivarea cu dovadă e a DIRIGINTELUI
 * clasei sau a administrației. Profesorul de disciplină consemnează absențe, dar nu le motivează:
 * o dovadă acoperă ziua întreagă, deci ar motiva și absențele de la orele colegilor (spec §2.1).
 */
it('profesorul de disciplină vede absența de la ora lui, dar nu o poate motiva', function () {
    $subject = Subject::factory()->create();
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
    ]);

    // Absență la ORA LUI → o vede în listă (altfel testul n-ar spune nimic despre acțiune).
    $absence = Absence::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
        'is_motivated' => false,
    ]);

    $this->actingAs($profesor);

    expect($profesor->canMotivateAbsencesFor($this->class->id))->toBeFalse();

    Livewire::test(ListAbsences::class)
        ->assertCanSeeTableRecords([$absence])
        ->assertTableActionHidden('motivate', $absence);
});

it('serverul ignoră „motivează acum" trimis de un profesor care nu e dirigintele clasei', function () {
    $subject = Subject::factory()->create();
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $profesor->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $subject->id,
    ]);

    Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    $this->actingAs($profesor);

    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'student_id' => $this->student->id,
            'occurred_on' => '2026-03-10',
            'motivate_now' => true,
            'motivation_reason' => 'Certificat medical.',
            'motivation_document' => UploadedFile::fake()->create('certificat.pdf', 120, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absence::query()->count())->toBe(1)
        ->and(Absence::query()->first()->is_motivated)->toBeFalse()
        ->and(AbsenceMotivation::query()->count())->toBe(0);
});

it('dirigintele vede bifa „motivează acum" doar pentru clasa lui', function () {
    Term::factory()->for($this->year)->create([
        'number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true,
    ]);

    $altaClasa = SchoolClass::factory()->for($this->year)->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $altaClasa->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);

    Livewire::test(CreateAbsence::class)
        ->fillForm(['school_class_id' => $this->class->id])
        ->assertFormFieldVisible('motivate_now')
        ->fillForm(['school_class_id' => $altaClasa->id])
        ->assertFormFieldHidden('motivate_now');
});
