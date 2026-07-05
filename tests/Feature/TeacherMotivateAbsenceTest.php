<?php

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\ListAbsences;
use App\Models\AbsenceMotivation;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
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
