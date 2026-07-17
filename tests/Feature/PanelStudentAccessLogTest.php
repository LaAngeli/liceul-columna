<?php

/**
 * Lot L133/PII (§7 — „cine a vizualizat/exportat ce date, nu doar modificările"):
 * până acum, jurnalul de acces acoperea DOAR cabinetul; panoul /admin — calea principală a
 * personalului către dosarul elevului — nu jurnaliza nimic. Acum:
 *  - deschiderea fișei elevului în panou (ViewStudent) → intrare „viewed";
 *  - exportul listei de elevi (ExportBulkAction) → intrare „exported" PER ELEV;
 *  - generarea unui raport per-clasă (pagina Rapoarte) → intrare „exported" per elev inclus.
 */

use App\Enums\StaffReportType;
use App\Enums\UserRole;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    $this->class = SchoolClass::factory()->for($this->year)->create();
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    actingAs($this->director);

    // Auditarea e oprită în consolă implicit; o pornim DUPĂ fixtură (nu auditează created-ul).
    config(['audit.console' => true]);
});

it('deschiderea fișei elevului în panou e jurnalizată ca „viewed"', function () {
    Livewire::test(ViewStudent::class, ['record' => $this->student->id])
        ->assertOk();

    $audit = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $this->student->id)
        ->where('event', 'viewed')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe($this->director->id);
});

it('exportul listei de elevi e jurnalizat ca „exported" per elev', function () {
    // Fake pe Bus/Queue: ne interesează jurnalul (hook-ul before), nu CSV-ul propriu-zis.
    Bus::fake();
    Queue::fake();

    $second = Student::factory()->create();
    Enrollment::factory()->for($second)->for($this->class)->for($this->year)->create();

    Livewire::test(ListStudents::class)
        ->callTableBulkAction('export', [$this->student, $second]);

    $exported = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('event', 'exported')
        ->pluck('auditable_id')
        ->map(fn ($id) => (int) $id);

    expect($exported)->toContain($this->student->id)
        ->and($exported)->toContain($second->id);
});

it('generarea unui raport per-clasă e jurnalizată ca „exported" pentru fiecare elev al clasei', function () {
    Livewire::test(Reports::class)
        ->call('openCategory', 'elevi')
        ->call('openReport', StaffReportType::ClassRoster->value)
        ->set('data.school_class_id', $this->class->id)
        ->call('generate');

    $audit = Audit::query()
        ->where('auditable_type', Student::class)
        ->where('auditable_id', $this->student->id)
        ->where('event', 'exported')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->user_id)->toBe($this->director->id)
        ->and($audit->new_values['detaliu'] ?? '')->toContain('Raport staff');
});

it('profesorul vede în panou fișa elevului din clasa lui, dar nu pe a unuia străin', function () {
    $profUser = User::factory()->create();
    $profUser->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $profUser->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id, 'school_class_id' => $this->class->id,
        'subject_id' => Subject::factory()->create()->id,
    ]);
    $foreignStudent = Student::factory()->create();

    actingAs($profUser);

    // Elevul clasei lui → pagina se deschide.
    Livewire::test(ViewStudent::class, ['record' => $this->student->id])->assertOk();

    // Elev străin → 404: route-binding-ul paginii e deja scopat pe clasele profesorului
    // (StudentResource::getEloquentQuery), deci fișa nici nu se REZOLVĂ — mai bine decât 403,
    // nu divulgă existența. Policy-ul scopat (403) apără căile care nu trec prin binding —
    // cabinetul (vezi AuditTest) și orice Gate::check direct.
    $this->get("/admin/students/{$foreignStudent->id}")->assertNotFound();
});
