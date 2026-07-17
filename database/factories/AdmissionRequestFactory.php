<?php

namespace Database\Factories;

use App\Enums\AdmissionRequestType;
use App\Enums\AdmissionStatus;
use App\Models\AdmissionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionRequest>
 */
class AdmissionRequestFactory extends Factory
{
    protected $model = AdmissionRequest::class;

    public function definition(): array
    {
        return [
            'type' => AdmissionRequestType::Enrollment,
            'parent_name' => fake()->name(),
            'phone' => '+373 69 '.fake()->numerify('### ###'),
            'email' => fake()->optional()->safeEmail(),
            'child_name' => fake()->name(),
            'child_age' => fake()->numberBetween(6, 18),
            'desired_class' => (string) fake()->numberBetween(1, 12),
            'preferred_time' => null,
            'status' => AdmissionStatus::Nou,
        ];
    }

    public function visit(): static
    {
        return $this->state(fn (): array => [
            'type' => AdmissionRequestType::Visit,
            'child_age' => null,
            'desired_class' => null,
            'preferred_time' => now()->addWeek()->setTime(10, 0)->format('Y-m-d H:i'),
        ]);
    }

    public function contacted(): static
    {
        return $this->state(fn (): array => [
            'status' => AdmissionStatus::Contactat,
            'contacted_at' => now()->subDay(),
        ]);
    }

    public function enrolled(): static
    {
        return $this->state(fn (): array => [
            'status' => AdmissionStatus::Inmatriculat,
            'contacted_at' => now()->subDays(2),
            'processed_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => AdmissionStatus::Refuzat,
            'contacted_at' => now()->subDays(2),
            'processed_at' => now()->subDay(),
            'staff_note' => 'Locuri epuizate la clasa cerută.',
        ]);
    }
}
