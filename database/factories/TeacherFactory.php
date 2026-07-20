<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'first_name' => fake()->lastName(),
            'last_name' => fake()->firstName(),
            'sex' => fake()->randomElement(Sex::cases()),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
