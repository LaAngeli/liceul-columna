<?php

namespace Database\Factories;

use App\Enums\EvaluationType;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'term_id' => Term::factory(),
            'teacher_id' => Teacher::factory(),
            'graded_on' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'type' => fake()->numberBetween(1, 6),
            'evaluation_type' => EvaluationType::Curenta,
            'value' => fake()->numberBetween(1, 10),
            'calificativ' => null,
        ];
    }

    public function teza(): static
    {
        return $this->state(fn (): array => ['evaluation_type' => EvaluationType::Teza]);
    }

    public function esi(): static
    {
        return $this->state(fn (): array => ['evaluation_type' => EvaluationType::Esi]);
    }

    public function annulled(): static
    {
        return $this->state(fn (): array => [
            'annulled_at' => now(),
            'annulment_reason' => 'Notă greșită (test)',
        ]);
    }
}
