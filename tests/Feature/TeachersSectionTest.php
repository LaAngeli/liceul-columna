<?php

/**
 * Secțiunea „Profesori" = registrul ADMINISTRAȚIEI (decizia beneficiarului, 2026-07-15: nu se
 * deschide cadrelor didactice), restructurat pe NAVIGARE PRIN ENTITĂȚI (2026-07-19): vederi-segmente
 * (Toți / Diriginți / Fără alocări / Fără cont / Arhivă) + căutare → CARDURI de profesor → FIȘA
 * profesorului (identitate, alocări desfășurate cu clase-chips, punți în catalog, editare).
 * Funcția e cea REALĂ (homeroom-ul anului curent), nu eticheta legacy `position`.
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

    // Profesor cu alocare + cont (cardul „complet" al registrului).
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

it('cardul poartă funcția REALĂ (homeroom-ul anului curent), disciplinele și contul', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $cards = Livewire::test(ListTeachers::class)->instance()->teacherCards();

    $card = collect($cards)->firstWhere('id', $this->teacher->id);
    expect($card['homeroom'])->toBe('VII A')
        ->and($card['subjects'])->toContain('Matematică aplicată')
        ->and($card['account'])->not->toBeNull()
        ->and($card['archived'])->toBeFalse();
});

it('diriginția unui an NEcurent nu mai e funcție: cardul rămâne „Profesor", vederea Diriginți îl exclude', function () {
    $oldYear = AcademicYear::factory()->create(['is_current' => false]);
    $oldClass = SchoolClass::factory()->for($oldYear)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'B']);
    $oldClass->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class);

    expect(collect($component->instance()->teacherCards())->firstWhere('id', $this->teacher->id)['homeroom'])->toBeNull();

    $component->call('openView', 'diriginti');
    expect(collect($component->instance()->teacherCards())->pluck('id')->all())->not->toContain($this->teacher->id);
});

it('vederile segmentează cardurile: diriginți / fără alocări / fără cont / arhivă; vederea străină cade pe „toți"', function () {
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

    $component = Livewire::test(ListTeachers::class);
    $ids = fn (): array => collect($component->instance()->teacherCards())->pluck('id')->all();

    expect($ids())->toContain($this->teacher->id, $noAssignments->id, $noAccount->id)
        ->and($ids())->not->toContain($archived->id);

    $component->call('openView', 'diriginti');
    expect($ids())->toBe([$this->teacher->id]);

    $component->call('openView', 'fara-alocari');
    expect($ids())->toBe([$noAssignments->id]);

    $component->call('openView', 'fara-cont');
    expect($ids())->toBe([$noAccount->id]);

    $component->call('openView', 'arhiva');
    expect($ids())->toBe([$archived->id]);

    // `?vedere=` străin → validat la citire, cade pe „toți".
    $stray = Livewire::withQueryParams(['vedere' => 'inexistent'])->test(ListTeachers::class);
    expect($stray->instance()->activeView())->toBe('toti');
});

it('fișa profesorului: identitate + alocările desfășurate cu clase-chips pe context + punți; id străin → carduri', function () {
    $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)->call('openTeacher', $this->teacher->id);
    $profile = $component->instance()->teacherProfile();

    expect($profile)->not->toBeNull()
        ->and($profile['homeroom'])->toBe('VII A')
        ->and($profile['email'])->toBe('profesor@columna.internal')
        ->and($profile['assignments'])->toHaveCount(1)
        ->and($profile['assignments'][0]['subject'])->toContain('Matematică aplicată')
        ->and($profile['assignments'][0]['classes'][0]['label'])->toBe('VII A')
        ->and($profile['assignments'][0]['classes'][0]['url'])->toContain('clasa='.$this->class->id)
        // Directorul nu e configurator? Ba da (canConfigureSchool) → butonul de editare există.
        ->and($profile['editUrl'])->not->toBeNull();

    foreach ($profile['links'] as $url) {
        expect($url)->toContain('profesor='.$this->teacher->id);
    }

    // Id inexistent prin URL → fără context, cad pe carduri.
    $stray = Livewire::withQueryParams(['profesor' => '999999'])->test(ListTeachers::class);
    expect($stray->instance()->teacherProfile())->toBeNull();
});

it('căutarea filtrează cardurile după nume', function () {
    Teacher::factory()->create(['last_name' => 'Zugravu', 'first_name' => 'Ana', 'user_id' => null]);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListTeachers::class)->set('search', 'Zugravu');

    expect(collect($component->instance()->teacherCards())->pluck('name')->all())->toBe(['Zugravu Ana']);
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

it('prim-vicedirectorul vede registrul, dar fără editare (policy de configurator)', function () {
    $pvd = User::factory()->create();
    $pvd->assignRole(UserRole::PrimVicedirector->value);
    actingAs($pvd);

    $component = Livewire::test(ListTeachers::class)->call('openTeacher', $this->teacher->id);

    expect($component->instance()->teacherProfile()['editUrl'])->toBeNull()
        ->and($pvd->can('delete', $this->teacher))->toBeFalse();
});
