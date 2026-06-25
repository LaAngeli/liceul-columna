<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        $year = AcademicYear::factory();

        return [
            'student_id' => Student::factory(),
            'school_class_id' => SchoolClass::factory(),
            'academic_year_id' => $year,
            'enrolled_on' => fake()->date(),
            'left_on' => null,
        ];
    }
}
