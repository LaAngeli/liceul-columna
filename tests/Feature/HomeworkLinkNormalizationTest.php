<?php

use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\WebLink;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

/**
 * Linkul temei se scrie ca un OM: „test.md", nu „https://test.md" (cerința beneficiarului,
 * 07.08.2026). Structura de domeniu rămâne obligatorie, schema se completează la salvare.
 */
it('acceptă adrese fără schemă, dar cere structura de domeniu', function (string $value, bool $valid): void {
    expect(WebLink::isValid($value))->toBe($valid);
})->with([
    // Exact cazurile din sesizare — acestea erau respinse înainte.
    ['test.md', true],
    ['test.test', true],
    ['www.exemplu.com/cap4', true],
    ['exemplu.md/manual/cap-4?pagina=12#nota', true],
    ['sub.domeniu.exemplu.md:8080/x', true],
    ['exemplu.md:8080', true],
    ['https://exemplu.md', true],
    ['http://intranet.exemplu.md', true],
    ['școală.md', true],
    // Structura lipsește: fără punct, fără TLD, sau etichete goale.
    ['exemplu', false],
    ['exemplu.', false],
    ['.md', false],
    ['exemplu..md', false],
    ['exemplu.m', false],
    ['-exemplu.md', false],
    ['Manualul digital, cap. 4', false],
    ['', false],
    // Securitate: schemele periculoase NU primesc „https://" în față, deci cad la validare.
    ['javascript:alert(1)', false],
    ['data:text/html,<script>alert(1)</script>', false],
    ['mailto:cineva@exemplu.md', false],
]);

it('nu rescrie o schemă explicită', function (): void {
    // http-ul rămâne http (o adresă internă poate exista DOAR pe http).
    expect(WebLink::normalize('http://intranet.exemplu.md'))->toBe('http://intranet.exemplu.md')
        ->and(WebLink::normalize('https://exemplu.md'))->toBe('https://exemplu.md')
        ->and(WebLink::normalize('exemplu.md'))->toBe('https://exemplu.md')
        ->and(WebLink::normalize('  exemplu.md  '))->toBe('https://exemplu.md')
        ->and(WebLink::normalize('   '))->toBeNull();
});

it('păstrează ordinea și elimină intrările goale dintr-o listă', function (): void {
    expect(WebLink::normalizeAll(['b.md', '', null, 'https://a.md', 42]))
        ->toBe(['https://b.md', 'https://a.md']);
});

it('stochează linkul ABSOLUT când profesorul scrie doar domeniul', function (): void {
    Role::findOrCreate(UserRole::Profesor->value, 'web');

    $year = AcademicYear::factory()->create(['is_current' => true]);
    Term::factory()->for($year)->create([
        'is_current' => true,
        'starts_on' => now()->subMonths(3),
        'ends_on' => now()->addMonth(),
    ]);

    $class = SchoolClass::factory()->for($year)->create(['grade_level' => 7]);
    $subject = Subject::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $teacher = Teacher::factory()->create(['user_id' => $user->id]);

    TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'school_class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    Livewire\Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'class_target' => 'class:'.$class->id,
            'subject_id' => $subject->id,
            'assigned_on' => now()->toDateString(),
            'topic' => 'Fracții',
            'required_task' => 'Ex. 1–5',
            // Repeaterul „simplu" se completează tot cu cheia câmpului intern (gotcha Filament).
            'links' => [['url' => 'test.md'], ['url' => 'www.exemplu.com/cap4']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomeworkAssignment::query()->latest('id')->value('links'))
        ->toBe(['https://test.md', 'https://www.exemplu.com/cap4']);
});
