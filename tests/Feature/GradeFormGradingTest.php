<?php

use App\Enums\GradingType;
use App\Enums\UserRole;
use App\Filament\Resources\Grades\Pages\CreateGrade;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Lot 8.C / audit #6+#7: formularul de notă se adaptează la modul de notare al disciplinei
 * (grading_type) — câmpul numeric DOAR pentru discipline numerice, calificativul DOAR pentru
 * cele pe calificativ/descriptiv. Vizibilitatea pe tip garantează structural „notă SAU calificativ".
 */
beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }
    $this->director = User::factory()->create();
    $this->director->assignRole(UserRole::Director->value);
    $this->actingAs($this->director);
});

it('la disciplină numerică afișează nota și ascunde calificativul', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Numeric, 'min_grade' => 1, 'max_grade' => 10]);

    Livewire::test(CreateGrade::class)
        ->fillForm(['subject_id' => $subject->id])
        ->assertFormFieldIsVisible('value')
        ->assertFormFieldIsHidden('calificativ');
});

it('la disciplină pe calificativ afișează calificativul și ascunde nota', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Calificativ]);

    Livewire::test(CreateGrade::class)
        ->fillForm(['subject_id' => $subject->id])
        ->assertFormFieldIsHidden('value')
        ->assertFormFieldIsVisible('calificativ');
});

it('la disciplină descriptivă afișează calificativul (descriptor) și ascunde nota', function () {
    $subject = Subject::factory()->create(['grading_type' => GradingType::Descriptiv]);

    Livewire::test(CreateGrade::class)
        ->fillForm(['subject_id' => $subject->id])
        ->assertFormFieldIsHidden('value')
        ->assertFormFieldIsVisible('calificativ');
});

it('cât timp disciplina nu e aleasă, ambele câmpuri sunt vizibile (formular neutru)', function () {
    Livewire::test(CreateGrade::class)
        ->assertFormFieldIsVisible('value')
        ->assertFormFieldIsVisible('calificativ');
});
