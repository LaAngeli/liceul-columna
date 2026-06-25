<?php

namespace Database\Factories;

use App\Enums\GradingType;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'abbreviation' => fake()->lexify('????'),
            'min_grade' => 5,
            'max_grade' => 12,
            'grading_type' => GradingType::Numeric,
        ];
    }
}
