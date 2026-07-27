<?php

/**
 * VIZIBILITATEA CALITĂȚII — cele două fețe ale aceleiași probleme (cerință 2026-07-27).
 *
 * Dirigenția e o DESEMNARE pe fișă, nu un rol, deci:
 *   (a) eticheta de rol și funcția reală se pot desincroniza tăcut — navigatorul „Utilizatori"
 *       trebuie s-o semnaleze, ca deriva să nu mai treacă neobservată;
 *   (b) perimetrul se derivă PER CLASĂ — aceeași persoană vede toată clasa unde e diriginte și
 *       doar disciplina ei în rest, iar bara de context trebuie s-o spună la fiecare intrare.
 */

use App\Enums\UserRole;
use App\Filament\Resources\AcademicRecords\Pages\ListAcademicRecords;
use App\Filament\Resources\Grades\Pages\ListGrades;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
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

    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->homeroomClass = SchoolClass::factory()->for($this->year)->create(['name' => 'XI', 'section' => 'R', 'grade_level' => 11]);
    $this->taughtClass = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'section' => '2', 'grade_level' => 7]);
});

// ── (a) Semnalul de derivă rol ↔ dirigenție ────────────────────────────────────────────────────

it('semnalează contul cu ROLUL „diriginte" care nu are nicio clasă în dirigenție', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    Teacher::factory()->create(['user_id' => $user->id]);

    expect(ListUsers::mismatchQuery(UserRole::Diriginte, hasHomeroom: false)->pluck('id'))
        ->toContain($user->id);
});

it('semnalează contul cu ROLUL „profesor" care ESTE diriginte — cazul raportat ca breșă', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    expect(ListUsers::mismatchQuery(UserRole::Profesor, hasHomeroom: true)->pluck('id'))
        ->toContain($user->id);
});

it('nu semnalează conturile în care eticheta și funcția coincid', function () {
    // Diriginte cu clasă.
    $diriginte = User::factory()->create();
    $diriginte->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $diriginte->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    // Profesor fără dirigenție.
    $profesor = User::factory()->create();
    $profesor->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $profesor->id]);

    $flagged = ListUsers::mismatchQuery(UserRole::Diriginte, hasHomeroom: false)->pluck('id')
        ->merge(ListUsers::mismatchQuery(UserRole::Profesor, hasHomeroom: true)->pluck('id'));

    expect($flagged)->not->toContain($diriginte->id)
        ->and($flagged)->not->toContain($profesor->id);
});

it('numără deriva pentru cardurile navigatorului', function () {
    $orfan = User::factory()->create();
    $orfan->assignRole(UserRole::Diriginte->value);
    Teacher::factory()->create(['user_id' => $orfan->id]);

    $ascuns = User::factory()->create();
    $ascuns->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $ascuns->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);
    actingAs($admin);

    $component = Livewire::test(ListUsers::class);
    $counts = $component->instance()->capacityMismatches();

    expect($counts['diriginte_without_class'])->toBe(1)
        ->and($counts['profesor_with_class'])->toBe(1);

    // Semnalul ajunge pe cardurile rolurilor, nu doar în numărătoare.
    $component->assertOk()
        ->assertSee(trans('panel.users_nav.diriginte_without_class', ['count' => 1]))
        ->assertSee(trans('panel.users_nav.profesor_with_class', ['count' => 1]));
});

// ── (b) Calitatea în bara de context a catalogului ─────────────────────────────────────────────

it('spune „ca diriginte" pe clasa proprie și „ca profesor" pe cea unde doar predă', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    // Diriginte la una, doar profesor la cealaltă — exact scenariul din întrebarea beneficiarului.
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);
    $subject = Subject::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $this->taughtClass->id,
        'subject_id' => $subject->id,
    ]);

    actingAs($user->fresh());

    $asHomeroom = Livewire::withQueryParams(['clasa' => (string) $this->homeroomClass->id])
        ->test(ListGrades::class)->instance()->catalogCapacityNotice();

    expect($asHomeroom)->not->toBeNull()
        ->and($asHomeroom['label'])->toBe(trans('panel.catalog_nav.capacity_homeroom'));

    $asTeacher = Livewire::withQueryParams(['clasa' => (string) $this->taughtClass->id])
        ->test(ListGrades::class)->instance()->catalogCapacityNotice();

    expect($asTeacher)->not->toBeNull()
        ->and($asTeacher['label'])->toBe(trans('panel.catalog_nav.capacity_teacher'));
});

it('nu arată calitatea administrației — ea vede tot în virtutea funcției', function () {
    $director = User::factory()->create();
    $director->assignRole(UserRole::Director->value);
    actingAs($director);

    $notice = Livewire::withQueryParams(['clasa' => (string) $this->homeroomClass->id])
        ->test(ListGrades::class)->instance()->catalogCapacityNotice();

    expect($notice)->toBeNull();
});

it('randează indicatorul de calitate în bara de context', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    actingAs($user->fresh());

    Livewire::withQueryParams(['clasa' => (string) $this->homeroomClass->id])
        ->test(ListGrades::class)
        ->assertOk()
        ->assertSee(trans('panel.catalog_nav.capacity_homeroom'))
        ->assertSee(trans('panel.catalog_nav.capacity_homeroom_hint'));
});

it('arată aceeași calitate și în Foaia matricolă, prin sursa unică', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    actingAs($user->fresh());

    $notice = Livewire::withQueryParams(['clasa' => (string) $this->homeroomClass->id])
        ->test(ListAcademicRecords::class)->instance()->capacityNotice();

    expect($notice)->not->toBeNull()
        ->and($notice['label'])->toBe(trans('panel.catalog_nav.capacity_homeroom'));
});

it('nu arată calitatea în afara unui context de clasă', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Diriginte->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);
    $this->homeroomClass->update(['homeroom_teacher_id' => $teacher->id]);

    actingAs($user->fresh());

    expect(Livewire::test(ListGrades::class)->instance()->catalogCapacityNotice())->toBeNull();
});
