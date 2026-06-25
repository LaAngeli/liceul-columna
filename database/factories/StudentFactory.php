<?php

namespace Database\Factories;

use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'first_name' => fake()->lastName(),
            'last_name' => fake()->firstName(),
            'sex' => fake()->randomElement(Sex::cases()),
            'register_number' => (string) fake()->unique()->numberBetween(1, 9999),
            'english_group' => fake()->numberBetween(1, 3),
            'second_language' => SecondLanguage::None,
        ];
    }
}
