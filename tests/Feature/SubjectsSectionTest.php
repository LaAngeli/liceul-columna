<?php

/**
 * Secțiunea „Discipline" — navigator cu carduri pentru cadre didactice (2026-07-15, feedback
 * beneficiar): cardurile disciplinelor PREDATE de mine → click → clasele în care EU o predau.
 * Filtrarea e pe disciplină ȘI utilizator: doi profesori de chimie își văd fiecare doar clasele
 * proprii. Administrația păstrează tabelul de nomenclator complet.
 */

use App\Enums\UserRole;
use App\Filament\Resources\Subjects\Pages\ListSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
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

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->year = AcademicYear::factory()->create();
    Term::factory()->for($this->year)->create([
        'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'is_current' => true,
    ]);

    $this->classA = SchoolClass::factory()->for($this->year)->create(['name' => 'VII', 'grade_level' => 7, 'section' => 'A']);
    $this->classB = SchoolClass::factory()->for($this->year)->create(['name' => 'IX', 'grade_level' => 9, 'section' => 'B']);

    $this->chemistry = Subject::factory()->create(['name' => 'SUBJ-Chimie', 'min_grade' => 7, 'max_grade' => 12]);
    $this->unrelated = Subject::factory()->create(['name' => 'SUBJ-Alta', 'min_grade' => 1, 'max_grade' => 4]);

    // Profesorul MEU: predă Chimie DOAR în VII A.
    $this->user = User::factory()->create();
    $this->user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id, 'school_class_id' => $this->classA->id, 'subject_id' => $this->chemistry->id,
    ]);

    // AL DOILEA profesor de Chimie: predă aceeași disciplină în IX B (nu trebuie să apară la mine).
    $this->otherChemist = Teacher::factory()->create();
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->otherChemist->id, 'school_class_id' => $this->classB->id, 'subject_id' => $this->chemistry->id,
    ]);

    actingAs($this->user);
});

it('profesorul primește carduri DOAR cu disciplinele lui', function () {
    $cards = Livewire::test(ListSubjects::class)->instance()->subjectCards();

    expect(collect($cards)->pluck('id')->all())->toBe([$this->chemistry->id]);
});

it('click pe disciplină → DOAR clasele MELE pentru ea (nu și ale celuilalt profesor de chimie)', function () {
    $component = Livewire::test(ListSubjects::class)
        ->call('openSubject', $this->chemistry->id);

    $classCards = $component->instance()->classCards();

    // Filtru disciplină + UTILIZATOR: VII A (a mea) da; IX B (a colegului chimist) NU.
    expect(collect($classCards)->pluck('id')->all())->toBe([$this->classA->id])
        ->and($component->instance()->activeSubject()?->id)->toBe($this->chemistry->id);
});

it('cardul clasei sare direct în Note/Absențe/Teme pe contextul (clasă, disciplină)', function () {
    $component = Livewire::test(ListSubjects::class)->call('openSubject', $this->chemistry->id);

    $links = collect($component->instance()->classCards())->firstWhere('id', $this->classA->id)['links'];

    foreach ($links as $url) {
        expect($url)->toContain('clasa='.$this->classA->id)
            ->toContain('disciplina='.$this->chemistry->id);
    }
});

it('o disciplină pe care N-O predau, venită prin URL, nu deschide context', function () {
    $component = Livewire::withQueryParams(['disciplina' => (string) $this->unrelated->id])
        ->test(ListSubjects::class);

    expect($component->instance()->activeSubject())->toBeNull();
});

it('administrația primește CARDURILE nomenclatorului complet, cu acoperirea instituțională', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListSubjects::class);

    expect($component->instance()->isTeacherView())->toBeFalse();

    $cards = collect($component->instance()->adminSubjectCards());
    expect($cards->pluck('id')->all())->toContain($this->chemistry->id, $this->unrelated->id)
        // Chimia: 2 clase, 2 profesori (al meu + colegul chimist).
        ->and($cards->firstWhere('id', $this->chemistry->id)['coverage'])->toContain('2');
});

it('contextul disciplinei (admin): TOȚI profesorii care o predau, fiecare cu clasele lui + punți', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Director->value);
    actingAs($admin);

    $component = Livewire::test(ListSubjects::class)->call('openSubject', $this->chemistry->id);
    $context = $component->instance()->adminSubjectContext();

    expect($context)->not->toBeNull()
        ->and(collect($context['teachers'])->pluck('name')->all())->toHaveCount(2);

    // Fiecare profesor doar cu clasele LUI la disciplină; chip-ul clasei duce în catalog pe context.
    $mine = collect($context['teachers'])->firstWhere('name', trim($this->teacher->last_name.' '.$this->teacher->first_name));
    expect(collect($mine['classes'])->pluck('label')->all())->toBe(['VII A'])
        ->and($mine['classes'][0]['url'])->toContain('clasa='.$this->classA->id)
        ->and($mine['url'])->toContain('profesor='.$this->teacher->id);

    foreach ($context['links'] as $url) {
        expect($url)->toContain('disciplina='.$this->chemistry->id);
    }

    // Id inexistent prin URL → fără context (cad pe carduri).
    $stray = Livewire::withQueryParams(['disciplina' => '999999'])->test(ListSubjects::class);
    expect($stray->instance()->adminSubjectContext())->toBeNull();
});

it('scoping-ul resursei = strict disciplinele predate (interogarea, nu doar afișarea)', function () {
    expect(SubjectResource::getEloquentQuery()->pluck('id')->all())->toBe([$this->chemistry->id]);
});
