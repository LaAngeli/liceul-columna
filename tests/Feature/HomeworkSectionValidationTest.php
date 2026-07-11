<?php

/**
 * Teme (audit-teme #6): litera clasei e text liber. Dacă profesorul tastează o literă inexistentă
 * (ex. „Z"), tema nu ajunge la nicio clasă — un eșec TĂCUT. Formularul o respinge acum cu mesaj clar,
 * dar acceptă literele reale ȘI litera goală (= toată treapta).
 */

use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Models\AcademicYear;
use App\Models\HomeworkAssignment;
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

    $year = AcademicYear::factory()->create();
    // Clasă reală: treapta 7, litera „1". „Z" nu există la treapta 7.
    $this->class = SchoolClass::factory()->for($year)->create(['grade_level' => 7, 'section' => '1']);
    $this->subject = Subject::factory()->create(['grading_type' => GradingType::Numeric]);

    $user = User::factory()->create();
    $user->assignRole(UserRole::Profesor->value);
    $this->teacher = Teacher::factory()->create(['user_id' => $user->id]);
    TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'school_class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
    ]);

    actingAs($user);
});

it('respinge o temă cu litera inexistentă la treapta aleasă, cu mesaj clar', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'subject_id' => $this->subject->id,
            'grade_level' => 7,
            'section' => 'Z',
            'assigned_on' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['section' => __('panel.forms.homework.section_not_found', ['section' => 'Z'])]);

    expect(HomeworkAssignment::query()->count())->toBe(0);
});

it('acceptă o literă reală la treapta aleasă', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'subject_id' => $this->subject->id,
            'grade_level' => 7,
            'section' => '1',
            'assigned_on' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomeworkAssignment::query()->where('section', '1')->count())->toBe(1);
});

it('acceptă litera goală (temă pentru toată treapta)', function () {
    Livewire::test(CreateHomeworkAssignment::class)
        ->fillForm([
            'subject_id' => $this->subject->id,
            'grade_level' => 7,
            'section' => '',
            'assigned_on' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HomeworkAssignment::query()->count())->toBe(1);
});
