<?php

/**
 * Constatările MĂRUNTE rămase din maparea nomenclatoarelor + calendarului (#31):
 * realinierea evaluărilor la mutarea granițelor de semestru, temele care urmează clasa
 * redenumită, contul elevului filtrat pe rol, gruparea cabinetului pe subject_id (duplicatele
 * legacy), garda de scope la crearea evenimentelor de către diriginte și săptămânile
 * LUCRĂTOARE în riscul de amânare.
 */

use App\Actions\ComputeDeferralRisk;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Http\Controllers\CabinetController;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Support\Holidays;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
});

/**
 * @return array{year: AcademicYear, term1: Term, term2: Term, class: SchoolClass, subject: Subject, student: Student, teacher: Teacher}
 */
function minorFixesFixture(): array
{
    $year = AcademicYear::factory()->create(['starts_on' => '2025-09-01', 'ends_on' => '2026-05-31']);
    $term1 = Term::factory()->for($year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-31', 'is_current' => false,
    ]);
    $term2 = Term::factory()->for($year)->create([
        'number' => 2, 'starts_on' => '2026-01-10', 'ends_on' => '2026-05-31', 'is_current' => true,
    ]);
    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 5, 'section' => 'A']);
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();

    return [
        'year' => $year, 'term1' => $term1, 'term2' => $term2,
        'class' => $class, 'subject' => $subject, 'student' => $student,
        'teacher' => Teacher::factory()->create(),
    ];
}

// ─── 1. Mutarea granițelor de semestru realiniază evaluările ─────────────────────────────

it('mutarea granițelor semestrului realiniază notele și absențele la semestrul corect', function () {
    $fx = minorFixesFixture();

    $nearBoundary = Grade::factory()->create([
        'student_id' => $fx['student']->id, 'school_class_id' => $fx['class']->id,
        'subject_id' => $fx['subject']->id, 'term_id' => $fx['term1']->id,
        'teacher_id' => $fx['teacher']->id, 'evaluation_type' => EvaluationType::Curenta,
        'graded_on' => '2025-12-20', 'value' => 8, 'calificativ' => null,
    ]);
    $stable = Grade::factory()->create([
        'student_id' => $fx['student']->id, 'school_class_id' => $fx['class']->id,
        'subject_id' => $fx['subject']->id, 'term_id' => $fx['term1']->id,
        'teacher_id' => $fx['teacher']->id, 'evaluation_type' => EvaluationType::Curenta,
        'graded_on' => '2025-10-01', 'value' => 9, 'calificativ' => null,
    ]);
    $absence = Absence::factory()->create([
        'student_id' => $fx['student']->id, 'school_class_id' => $fx['class']->id,
        'subject_id' => $fx['subject']->id, 'term_id' => $fx['term1']->id,
        'occurred_on' => '2025-12-20',
    ]);

    // Sem I se scurtează, dar 20.12 nu cade încă în niciun semestru → rândurile NU se orfanizează.
    $fx['term1']->update(['ends_on' => '2025-12-15']);
    expect($nearBoundary->refresh()->term_id)->toBe($fx['term1']->id);

    // Sem II se extinde peste 20.12 → nota și absența de la graniță trec în Sem II; restul rămân.
    $fx['term2']->update(['starts_on' => '2025-12-16']);

    expect($nearBoundary->refresh()->term_id)->toBe($fx['term2']->id)
        ->and($absence->refresh()->term_id)->toBe($fx['term2']->id)
        ->and($stable->refresh()->term_id)->toBe($fx['term1']->id);
});

// ─── 2. Temele urmează clasa la redenumirea literei ──────────────────────────────────────

it('redenumirea literei clasei poartă temele ei; temele pe treaptă și alte generații rămân', function () {
    $fx = minorFixesFixture();

    $ownHomework = HomeworkAssignment::factory()->create([
        'grade_level' => 5, 'section' => 'A', 'assigned_on' => '2025-10-06',
    ]);
    $wholeLevel = HomeworkAssignment::factory()->create([
        'grade_level' => 5, 'section' => null, 'assigned_on' => '2025-10-06',
    ]);
    $otherGeneration = HomeworkAssignment::factory()->create([
        'grade_level' => 5, 'section' => 'A', 'assigned_on' => '2024-10-06', // în afara anului clasei
    ]);

    $fx['class']->update(['section' => 'B']);

    expect($ownHomework->refresh()->section)->toBe('B')
        ->and($wholeLevel->refresh()->section)->toBeNull()
        ->and($otherGeneration->refresh()->section)->toBe('A');
});

// ─── 3. Contul elevului: doar useri cu rol elev, încă nelegați ───────────────────────────

it('legarea unui cont ORFAN oferă doar conturi de ELEV nelegate — și doar pe o fișă fără cont', function () {
    $fx = minorFixesFixture();

    $free = User::factory()->create(['name' => 'Elev Liber']);
    $free->assignRole(UserRole::Elev->value);

    $taken = User::factory()->create(['name' => 'Elev Legat']);
    $taken->assignRole(UserRole::Elev->value);
    Student::factory()->create(['user_id' => $taken->id]);

    $director = User::factory()->create(['name' => 'Director']);
    $director->assignRole(UserRole::Director->value);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    // Fișa e FĂRĂ cont → supapa de legare e disponibilă, dar numai către conturi de elev libere.
    $fx['student']->update(['user_id' => null]);

    Livewire::actingAs($admin)
        ->test(EditStudent::class, ['record' => $fx['student']->getRouteKey()])
        ->assertFormFieldExists('user_id', function (Select $field) use ($free, $taken, $director): bool {
            $options = array_keys($field->getOptions());

            return in_array($free->id, $options, false)
                && ! in_array($taken->id, $options, false)
                && ! in_array($director->id, $options, false);
        });

    // Fișa CU cont nu mai oferă re-legarea (2026-07-24): contul se administrează din secțiunea
    // Utilizatori, iar un select mereu deschis permitea repointarea cabinetului unui minor.
    $own = User::factory()->create(['name' => 'Elev Propriu']);
    $own->assignRole(UserRole::Elev->value);
    $fx['student']->update(['user_id' => $own->id]);

    Livewire::actingAs($admin)
        ->test(EditStudent::class, ['record' => $fx['student']->getRouteKey()])
        ->assertFormFieldHidden('user_id');
});

// ─── 4. Gruparea cabinetului pe subject_id (duplicatele legacy nu se contopesc) ──────────

it('cabinetul grupează notele pe subject_id — disciplinele omonime legacy rămân rânduri separate', function () {
    $fx = minorFixesFixture();

    $twin = Subject::factory()->create([
        'name' => $fx['subject']->name, 'grading_type' => GradingType::Numeric,
    ]);

    foreach ([$fx['subject'], $twin] as $subject) {
        Grade::factory()->create([
            'student_id' => $fx['student']->id, 'school_class_id' => $fx['class']->id,
            'subject_id' => $subject->id, 'term_id' => $fx['term2']->id,
            'teacher_id' => $fx['teacher']->id, 'evaluation_type' => EvaluationType::Curenta,
            'graded_on' => '2026-02-10', 'value' => 7, 'calificativ' => null,
        ]);
    }

    $method = new ReflectionMethod(CabinetController::class, 'gradesBySubject');
    $rows = $method->invoke(app(CabinetController::class), $fx['student']->refresh());

    expect($rows)->toHaveCount(2);
});

// ─── 5. Dirigintele nu poate crea evenimente în afara clasei lui (garda pe SERVER) ───────

it('dirigintele NU poate crea eveniment global sau pentru altă clasă (validare pe server)', function () {
    $fx = minorFixesFixture();

    $dirigUser = User::factory()->create();
    $dirigUser->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $dirigUser->id]);
    $fx['class']->update(['homeroom_teacher_id' => $teacher->id]);

    $foreignClass = SchoolClass::factory()->for($fx['year'])->create(['grade_level' => 6, 'section' => 'A']);

    // Scope global — opțiunea nici nu există pentru diriginte → validarea `in` o respinge.
    Livewire::actingAs($dirigUser)
        ->test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => CalendarEventScope::Global->value,
            'title' => 'Eveniment global neautorizat',
            'starts_on' => now()->addWeek()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['visibility_scope']);

    // Clasa altcuiva — lista de clase e restrânsă la clasele lui → respins.
    Livewire::actingAs($dirigUser)
        ->test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEventType::SchoolEvent->value,
            'visibility_scope' => CalendarEventScope::SchoolClass->value,
            'school_class_id' => $foreignClass->id,
            'title' => 'Eveniment pe clasa altuia',
            'starts_on' => now()->addWeek()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['school_class_id']);

    expect(CalendarEvent::query()->count())->toBe(0);
});

// ─── 6. Riscul de amânare numără doar săptămânile LUCRĂTOARE ─────────────────────────────

it('săptămânile riscului de amânare exclud vacanțele din holidays', function () {
    $fx = minorFixesFixture();

    // Semestrul II: 10.01–31.05. Fără vacanțe → ~20 săptămâni.
    $method = new ReflectionMethod(ComputeDeferralRisk::class, 'workingWeeks');
    $before = $method->invoke(null, $fx['term2']);

    // O vacanță de 4 săptămâni în interiorul semestrului reduce numitorul.
    Holiday::factory()->create(['starts_on' => '2026-02-01', 'ends_on' => '2026-02-28']);
    Holidays::flush();
    $after = $method->invoke(null, $fx['term2']);

    expect($after)->toBeLessThan($before)
        ->and($before - $after)->toBeGreaterThanOrEqual(3);
});
