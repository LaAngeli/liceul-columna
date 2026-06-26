<?php

namespace Database\Factories;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ScheduleType::Lessons->value,
            'label' => 'Clasa '.$this->faker->randomLetter(),
            'headers' => ['', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri'],
            'rows' => [
                ['Lecția 1', 'Matematică', 'Limba română', 'Fizică', 'Chimie', 'Biologie'],
                ['Lecția 2', 'Limba română', 'Matematică', 'Geografie', 'Istorie', 'Educație fizică'],
            ],
            'position' => 0,
            'is_public' => true,
        ];
    }

    public function ofType(ScheduleType $type): static
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['is_public' => false]);
    }
}
