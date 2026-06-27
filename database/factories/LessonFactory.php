<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'school_class_id' => SchoolClass::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'day_of_week' => fake()->randomElement(Weekday::cases()),
            'lesson_number' => fake()->numberBetween(1, 7),
            'room' => (string) fake()->numberBetween(1, 40),
        ];
    }
}
