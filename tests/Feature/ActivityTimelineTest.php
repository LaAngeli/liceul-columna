<?php

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * Modulul „Cronologie" (cabinet): notele și absențele ÎMPREUNĂ, în ordinea producerii —
 * cerința părinților care urmăresc evoluția copilului calendaristic, nu pe tipuri.
 * Pinuiește: fuziunea + ordinea (zile desc, notele înaintea absențelor în aceeași zi),
 * excluderea notelor anulate (§1) și scoping-ul pe familie al comutatorului de copil.
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->withoutVite();
});

/**
 * Părinte cu un copil înscris într-o clasă, într-un an cu semestru curent.
 * (Nume diferit de `catalogFamily` — Pest încarcă toate fișierele în același proces.)
 *
 * @return array{0: User, 1: Student, 2: Term, 3: SchoolClass}
 */
function timelineFamily(): array
{
    $year = AcademicYear::factory()->create();
    $class = SchoolClass::factory()->for($year)->create();
    $student = Student::factory()->create();
    Enrollment::factory()->for($student)->for($class)->for($year)->create();
    $term = Term::factory()->for($year)->create(['number' => 1, 'is_current' => true]);

    $parent = User::factory()->create();
    $parent->assignRole(UserRole::Parinte->value);
    $parent->students()->attach($student->id);

    return [$parent, $student, $term, $class];
}

it('modulul Cronologie se randează pentru părinte cu datele DOAR ale modulului', function () {
    [$parent, $student] = timelineFamily();

    $this->actingAs($parent)->get(route('cabinet.timeline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cabinet/cronologie')
            ->where('module.currentId', $student->id)
            ->has('module.students', 1)
            ->has('timeline.entries')
            ->has('timeline.terms', 1)
            ->where('timeline.currentTerm', 1)
            // Modulul încarcă DOAR datele lui — nimic din celelalte module.
            ->missing('gradebook')
            ->missing('overview')
            ->missing('homework'));
});

it('fuzionează notele și absențele cronologic: zile descrescător, notele înaintea absențelor', function () {
    [$parent, $student, $term, $class] = timelineFamily();
    $subject = Subject::factory()->create(['name' => 'Matematica']);

    // Ziua VECHE: doar o notă; ziua NOUĂ: absență + notă (nota trebuie să stea prima).
    $oldGrade = Grade::factory()->for($student)->for($class)->for($term)->create([
        'subject_id' => $subject->id,
        'graded_on' => '2026-03-10',
        'value' => 9,
    ]);
    $newAbsence = Absence::factory()->for($student)->for($class)->for($term)->create([
        'subject_id' => $subject->id,
        'occurred_on' => '2026-03-12',
        'is_motivated' => false,
    ]);
    $newGrade = Grade::factory()->for($student)->for($class)->for($term)->create([
        'subject_id' => $subject->id,
        'graded_on' => '2026-03-12',
        'value' => 10,
    ]);

    $this->actingAs($parent)->get(route('cabinet.timeline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeline.entries', 3)
            // Ziua nouă întâi; în interiorul ei nota stă înaintea absenței.
            ->where('timeline.entries.0.key', 'g-'.$newGrade->id)
            ->where('timeline.entries.0.kind', 'grade')
            ->where('timeline.entries.0.date', '12.03.2026')
            ->where('timeline.entries.0.label', '10')
            ->where('timeline.entries.1.key', 'a-'.$newAbsence->id)
            ->where('timeline.entries.1.kind', 'absence')
            ->where('timeline.entries.1.motivated', false)
            ->where('timeline.entries.2.key', 'g-'.$oldGrade->id)
            ->where('timeline.entries.2.date', '10.03.2026'));
});

it('nota ANULATĂ nu apare în cronologie (§1: rămâne doar în istoricul de audit)', function () {
    [$parent, $student, $term, $class] = timelineFamily();
    $subject = Subject::factory()->create();

    Grade::factory()->for($student)->for($class)->for($term)->create([
        'subject_id' => $subject->id,
        'graded_on' => '2026-03-11',
        'value' => 3,
        'annulled_at' => now(),
        'annulment_reason' => 'Consemnată la elevul greșit.',
    ]);

    $this->actingAs($parent)->get(route('cabinet.timeline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('timeline.entries', 0));
});

it('absența pe ZI ÎNTREAGĂ (fără disciplină) primește eticheta dedicată, nu un gol', function () {
    [$parent, $student, $term, $class] = timelineFamily();

    Absence::factory()->for($student)->for($class)->for($term)->create([
        'subject_id' => null,
        'occurred_on' => '2026-03-11',
        'is_motivated' => true,
    ]);

    $this->actingAs($parent)->get(route('cabinet.timeline'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('timeline.entries', 1)
            ->where('timeline.entries.0.subject', __('site.cabinet.whole_day_absence'))
            ->where('timeline.entries.0.motivated', true));
});

it('un ?copil= din afara familiei este refuzat cu 403, nu mascat', function () {
    [$parent] = timelineFamily();
    $foreign = Student::factory()->create();

    $this->actingAs($parent)
        ->get(route('cabinet.timeline', ['copil' => $foreign->id]))
        ->assertForbidden();
});
