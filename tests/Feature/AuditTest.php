<?php

use App\Enums\UserRole;
use App\Filament\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\AcademicYear;
use App\Models\Audit;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

it('jurnalul de audit din panou e accesibil conducerii, dar nu profesorului', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);

    $this->actingAs($director)->get('/admin/audits')->assertOk();
    $this->actingAs($profesor)->get('/admin/audits')->assertForbidden();
});

it('vizualizarea unui dosar de elev de către personal e jurnalizată (L133 §7)', function () {
    // Profesorul trebuie să PREDEA la clasa elevului — StudentPolicy::view e scopat (lot L133):
    // dosarul unui elev străin nu se mai deschide deloc (vezi testul următor).
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $staff->id]);
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    // Auditarea e oprită în consolă implicit (config audit.console=false); o pornim doar pentru
    // acțiunea testată, DUPĂ ce fixtura e creată (ca să nu auditeze și `created`-ul elevului).
    config(['audit.console' => true]);

    $this->actingAs($staff)->get("/cabinet/elev/{$student->id}")->assertOk();

    $audit = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $student->id)
        ->where('event', 'viewed')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe($staff->id);
});

it('profesorul NU poate deschide dosarul unui elev din afara claselor lui (L133 — scoping)', function () {
    $staff = User::factory()->create();
    $staff->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $staff->id]);

    // Elev fără nicio legătură cu profesorul.
    $student = Student::factory()->create();

    $this->actingAs($staff)->get("/cabinet/elev/{$student->id}")->assertForbidden();
});

it('familia care-și vede propriul copil NU intră în jurnalul de acces', function () {
    $student = Student::factory()->create();
    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    config(['audit.console' => true]);

    $this->actingAs($parent)->get("/cabinet/elev/{$student->id}")->assertOk();

    expect(Audit::query()->where('event', 'viewed')->count())->toBe(0);
});

it('relația audits() hidratează modelul APLICAȚIEI, iar jurnalul contextual de pe fișă se randează', function () {
    // Regresie 2026-07-20: `config('audit.implementation')` rămăsese pe modelul PACHETULUI, deci
    // relația morfică întorcea OwenIt\...\Audit, iar jurnalul de pe fișa elevului dădea 500
    // („Argument #1 ($record) must be of type App\Models\Audit").
    config(['audit.console' => true]);

    $student = Student::factory()->create();

    expect($student->audits()->first())->toBeInstanceOf(Audit::class)
        ->and($student->audits()->first()->eventLabel())->not->toBeEmpty();

    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    Livewire::actingAs($director)
        ->test(AuditsRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => ViewStudent::class,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords($student->audits()->get());
});

it('administratorul tehnic NU vede auditul datelor academice (scoping ◐); directorul vede tot', function () {
    config(['audit.console' => true]);

    // Auditul creării elevului (date academice/PII).
    $student = Student::factory()->create();

    $at = User::factory()->create();
    $at->assignRole(UserRole::AdministratorTehnic->value);
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);

    $this->actingAs($director);
    expect(AuditResource::getEloquentQuery()->where('auditable_type', Student::class)->exists())->toBeTrue();

    $this->actingAs($at);
    expect(AuditResource::getEloquentQuery()->where('auditable_type', Student::class)->exists())->toBeFalse();
});
