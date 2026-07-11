<?php

use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
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

    $year = AcademicYear::factory()->create();
    Term::factory()->for($year)->create(['number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => false]);
    Term::factory()->for($year)->create(['number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'is_current' => true]);

    $dirig = User::factory()->create();
    $dirig->assignRole(UserRole::Diriginte->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $dirig->id]);
    $this->class = SchoolClass::factory()->for($year)->create(['homeroom_teacher_id' => $this->teacher->id]);
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($year)->create();

    $this->actingAs($dirig);
});

it('motivarea inline la creare creează un AbsenceMotivation aprobat cu dovadă și marchează absența', function () {
    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'occurred_on' => '2026-03-10',
            'motivate_now' => true,
            'motivation_reason' => 'Certificat medical',
            'motivation_document' => UploadedFile::fake()->create('certificat.pdf', 120, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $absence = Absence::query()->firstOrFail();
    $motivation = AbsenceMotivation::query()->firstOrFail();

    expect($absence->is_motivated)->toBeTrue()
        ->and($motivation->status->value)->toBe('approved')
        ->and($motivation->student_id)->toBe($this->student->id)
        ->and($motivation->document_path)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $motivation->document_path);
});

it('fără toggle, absența se creează NEMOTIVATĂ și fără AbsenceMotivation', function () {
    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'occurred_on' => '2026-03-10',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absence::query()->firstOrFail()->is_motivated)->toBeFalse()
        ->and(AbsenceMotivation::query()->count())->toBe(0);
});

it('respinge data în viitor cu mesaj clar, fără ora cu secunde', function () {
    Livewire::test(CreateAbsence::class)
        ->fillForm([
            'school_class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'occurred_on' => today()->addDay()->toDateString(),
        ])
        ->call('create')
        // Mesajul custom „Data nu poate fi în viitor." în locul celui generic cu „…egală cu 2026-…-… HH:MM:SS".
        ->assertHasFormErrors(['occurred_on' => __('validation.not_future_date')]);

    expect(Absence::query()->count())->toBe(0);
});
