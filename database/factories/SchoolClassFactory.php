<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_level' => fake()->numberBetween(1, 12),
            'name' => fake()->randomElement(['V', 'VI', 'VII', 'VIII', 'IX']),
            'section' => fake()->unique()->bothify('?#'),
            'homeroom_teacher_id' => null,
        ];
    }
}
