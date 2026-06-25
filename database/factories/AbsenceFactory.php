<?php

namespace Database\Factories;

use App\Models\Absence;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    protected $model = Absence::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'term_id' => Term::factory(),
            'teacher_id' => Teacher::factory(),
            'occurred_on' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'is_motivated' => false,
        ];
    }
}
