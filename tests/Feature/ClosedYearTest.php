<?php

/**
 * Anul ÎNCHIS ca stare aplicată (LOT 9 al restructurării „Configurare").
 *
 * Arhivarea muta mediile în foaia matricolă și se oprea acolo: anul rămânea, pentru bază, la fel de
 * scriibil ca oricare altul. O notă introdusă a doua zi intra fără obiecție, dar nu mai avea cum să
 * ajungă în foaie — catalogul și arhiva începeau să spună lucruri diferite despre același elev, iar
 * discrepanța ieșea la iveală abia la eliberarea unui act.
 */

use App\Actions\ArchiveYearToTranscript;
use App\Enums\UserRole;
use App\Filament\Resources\Absences\Pages\CreateAbsence;
use App\Filament\Resources\AcademicYears\Pages\ListAcademicYears;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Jobs\ArchiveYearJob;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create([
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-05-31',
    ]);
    $this->term = Term::factory()->for($this->year)->create([
        'number' => 2,
        'is_current' => true,
        'starts_on' => '2026-01-15',
        'ends_on' => '2026-05-31',
    ]);
    $this->class = SchoolClass::factory()->for($this->year)->create(['grade_level' => 8]);
    $this->subject = Subject::factory()->create(['min_grade' => 5, 'max_grade' => 12]);
    $this->student = Student::factory()->create();
    Enrollment::factory()->for($this->student)->for($this->class)->for($this->year)->create();

    // Disciplina trebuie să fie PREDATĂ la clasă: formularul de absență acceptă doar disciplinele
    // din alocările clasei, iar fără alocare pica pe „valoare invalidă", înainte de garda testată.
    $this->teacher = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);
});

/**
 * Formularul de notă, completat în ORDINEA reală: clasa întâi, fiindcă alegerea ei recalculează
 * lista de elevi — completat dintr-o singură mișcare, elevul se pierde și pică pe „obligatoriu",
 * fără să se ajungă vreodată la garda testată.
 */
function gradeForm(object $ctx): Testable
{
    return Livewire::test(CreateGrade::class)
        ->fillForm(['school_class_id' => $ctx->class->id, 'subject_id' => $ctx->subject->id])
        ->fillForm([
            'student_id' => $ctx->student->id,
            'value' => 9,
            'graded_on' => '2026-03-10',
        ]);
}

/** Director (autoritate academică deplină) — dacă nici el nu poate scrie, nimeni nu poate. */
function catalogAuthority(): User
{
    $user = User::factory()->create();
    $user->assignRole(UserRole::Director->value);
    actingAs($user);

    return $user;
}

it('anul devine închis abia DUPĂ ce arhivarea reușește, cu autorul consemnat', function () {
    $initiator = catalogAuthority();

    expect($this->year->isClosed())->toBeFalse();

    (new ArchiveYearJob($this->year, $initiator->id))->handle(app(ArchiveYearToTranscript::class));

    $this->year->refresh();

    expect($this->year->isClosed())->toBeTrue()
        ->and($this->year->closed_at)->not->toBeNull()
        // Închiderea e un act cu răspundere: cine a făcut-o rămâne scris.
        ->and($this->year->closed_by_user_id)->toBe($initiator->id);
});

it('nota nu mai intră într-un an închis, nici pentru autoritatea academică', function () {
    catalogAuthority();

    $this->year->forceFill(['closed_at' => now()])->save();

    $component = gradeForm($this)->call('create')->assertHasFormErrors(['graded_on']);

    // Mesajul EXACT, nu doar „are eroare pe câmp": altfel testul ar fi trecut și dacă data pica în
    // alt gard (viitor, semestru inexistent), fără să atingă vreodată închiderea anului.
    expect($component->errors()->all())
        ->toContain(__('panel.validation.closed_year', ['year' => $this->year->name]))
        ->and(Grade::query()->count())->toBe(0);
});

it('absența nu mai intră într-un an închis', function () {
    catalogAuthority();

    $this->year->forceFill(['closed_at' => now()])->save();

    expect(Livewire::test(CreateAbsence::class)
        // Clasa ÎNTÂI: alegerea ei recalculează lista de elevi (ca în interfața reală).
        ->fillForm(['school_class_id' => $this->class->id, 'subject_id' => $this->subject->id])
        ->fillForm(['student_id' => $this->student->id, 'occurred_on' => '2026-03-10'])
        ->call('create')
        ->assertHasFormErrors(['occurred_on'])
        ->errors()->all())
        ->toContain(__('panel.validation.closed_year', ['year' => $this->year->name]));
});

it('anul DESCHIS rămâne neatins: garda nu blochează activitatea curentă', function () {
    // Profesorul care chiar predă disciplina la clasă — alocarea din `beforeEach` primește contul.
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $this->teacher->update(['user_id' => $user->id]);
    actingAs($user);

    gradeForm($this)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Grade::query()->count())->toBe(1);
});

it('cardul anului arată starea și nu mai propune arhivarea a doua oară', function () {
    $ao = User::factory()->create();
    $ao->assignRole(UserRole::AdministratorOperational->value);
    actingAs($ao);

    $deschis = collect(Livewire::test(ListAcademicYears::class)->instance()->yearCards())
        ->firstWhere('id', $this->year->id);

    expect($deschis['closed'])->toBeFalse()
        ->and($deschis['can_archive'])->toBeTrue();

    $this->year->forceFill(['closed_at' => now()])->save();

    $inchis = collect(Livewire::test(ListAcademicYears::class)->instance()->yearCards())
        ->firstWhere('id', $this->year->id);

    expect($inchis['closed'])->toBeTrue()
        ->and($inchis['closed_on'])->toBe(now()->format('d.m.Y'))
        // A doua arhivare ar rescrie foaia matricolă peste ea însăși, fără să adauge nimic.
        ->and($inchis['can_archive'])->toBeFalse();
});

it('corecția aprobată trece și într-un an închis — calea cu urmă rămâne deschisă', function () {
    catalogAuthority();

    $grade = Grade::factory()->create([
        'student_id' => $this->student->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
        'value' => 4,
        'graded_on' => '2026-03-10',
    ]);

    $this->year->forceFill(['closed_at' => now()])->save();

    // Fluxul de corecție scrie direct pe model, în afara formularelor: o greșeală descoperită după
    // închidere trebuie să poată fi îndreptată, dar numai cu cerere și aprobare.
    $grade->update(['value' => 7]);

    expect($grade->refresh()->value)->toBe('7.00');
});
