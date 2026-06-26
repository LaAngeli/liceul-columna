<?php

namespace Database\Factories;

use App\Models\HomeworkAssignment;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeworkAssignment>
 */
class HomeworkAssignmentFactory extends Factory
{
    protected $model = HomeworkAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'subject_name' => fake()->randomElement(['Matematică', 'Limba și literatura română', 'Fizică']),
            'author_name' => fake()->lastName(),
            'grade_level' => fake()->numberBetween(1, 12),
            'section' => fake()->randomElement(['1', '2', 'A', 'B', null]),
            'assigned_on' => fake()->dateTimeBetween('-2 months')->format('Y-m-d'),
            'topic' => fake()->sentence(),
            'required_task' => fake()->sentence(),
            'optional_task' => fake()->optional()->sentence(),
            'links' => fake()->optional()->passthrough([fake()->url()]),
        ];
    }
}
