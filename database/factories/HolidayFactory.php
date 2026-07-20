<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(HolidayType::cases()),
            'starts_on' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'ends_on' => null,
        ];
    }
}
