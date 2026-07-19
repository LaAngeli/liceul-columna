<?php

/**
 * Secțiunea „Profesori" = registrul ADMINISTRAȚIEI (decizia beneficiarului, 2026-07-15: nu se
 * deschide cadrelor didactice), restructurat pe VEDERI-segmente (2026-07-19): Toți / Diriginți /
 * Fără alocări / Fără cont / Arhivă, cu funcția REALĂ (homeroom-ul anului curent, nu eticheta
 * legacy `position`) și acoperirea desfășurată nominal pe discipline.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    // Anul CURENT — funcția „diriginte" se derivă din homeroom-ul acestui an.
    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->class = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->subject = Subject::factory()->create(['name' => 'Matematică aplicată']);

    // Profesor cu alocare + cont (rândul „complet" al registrului).
    $account = User::factory()->create();
    $this->teacher = Teacher::factory()->create(['email' => 'profesor@columna.internal', 'user_id' => $account->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id,
    ]);
});

it('profesorul NU are acces la secțiunea Profesori (403)', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    Teacher::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    get(TeacherResource::getUrl('index'))->assertForbidden();
});

it('registrul arată funcția REALĂ și acoperirea desfășurată pe discipline', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher])
        // Funcția din homeroom-ul anului curent + disciplina nominal, cu numărul de clase.
        ->assertSee('Diriginte · VII A')
        ->assertSee('Matematică aplicată ×1');

    expect($component->instance()->homeroomOfMap()->get($this->teacher->id))->toBe('VII A');
});

it('diriginția unui an NEcurent nu mai e funcție: profesorul apare simplu, nu în vederea Diriginți', function () {
    $oldYear = AcademicYear::factory()->create(['is_current' => false]);
    $oldClass = SchoolClass::factory()->for($oldYear)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'B']);
    $oldClass->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    Livewire::test(ListTeachers::class)
        ->call('openView', 'diriginti')
        ->assertCanNotSeeTableRecords([$this->teacher]);
});

it('vederile segmentează registrul: diriginți / fără alocări / fără cont / arhivă; vederea străină cade pe „toți"', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    $noAssignments = Teacher::factory()->create(['user_id' => User::factory()->create()->id]);
    $noAccount = Teacher::factory()->create(['user_id' => null]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $noAccount->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id,
    ]);
    $archived = Teacher::factory()->create(['user_id' => User::factory()->create()->id]);
    $archived->delete();

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher, $noAssignments, $noAccount])
        ->assertCanNotSeeTableRecords([$archived]);

    $component->call('openView', 'diriginti')
        ->assertCanSeeTableRecords([$this->teacher])
        ->assertCanNotSeeTableRecords([$noAssignments, $noAccount]);

    $component->call('openView', 'fara-alocari')
        ->assertCanSeeTableRecords([$noAssignments])
        ->assertCanNotSeeTableRecords([$this->teacher, $noAccount]);

    $component->call('openView', 'fara-cont')
        ->assertCanSeeTableRecords([$noAccount])
        ->assertCanNotSeeTableRecords([$this->teacher, $noAssignments]);

    $component->call('openView', 'arhiva')
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$this->teacher]);

    // `?vedere=` străin → validat la citire, cade pe „toți".
    $stray = Livewire::withQueryParams(['vedere' => 'inexistent'])->test(ListTeachers::class);
    expect($stray->instance()->activeView())->toBe('toti');
    $stray->assertCanSeeTableRecords([$this->teacher, $noAssignments, $noAccount]);
});

it('pastilele poartă numărători, iar Arhiva apare doar când există fișe șterse', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $pills = Livewire::test(ListTeachers::class)->instance()->viewPills();
    expect(collect($pills)->pluck('key'))->not->toContain('arhiva')
        ->and(collect($pills)->firstWhere('key', 'toti')['count'])->toBe(1);

    Teacher::factory()->create()->delete();

    $pills = Livewire::test(ListTeachers::class)->instance()->viewPills();
    expect(collect($pills)->firstWhere('key', 'arhiva')['count'])->toBe(1);
});

it('prim-vicedirectorul vede registrul, dar nu poate șterge fișe (policy de configurator)', function () {
    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    Livewire::test(ListTeachers::class)
        ->assertCanSeeTableRecords([$this->teacher]);

    expect($pvd->can('delete', $this->teacher))->toBeFalse();
});
