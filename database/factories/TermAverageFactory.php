<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermAverage>
 */
class TermAverageFactory extends Factory
{
    protected $model = TermAverage::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'term_id' => Term::factory(),
            'type' => 4,
            'value' => fake()->numberBetween(1, 10),
            'calificativ' => null,
        ];
    }
}
