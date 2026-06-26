<?php

namespace Database\Factories;

use App\Enums\AcademicRecordPeriod;
use App\Models\AcademicRecord;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicRecord>
 */
class AcademicRecordFactory extends Factory
{
    protected $model = AcademicRecord::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'grade_level' => fake()->numberBetween(1, 12),
            'period' => fake()->randomElement(AcademicRecordPeriod::cases()),
            'value' => fake()->numberBetween(5, 10),
            'calificativ' => null,
        ];
    }
}
