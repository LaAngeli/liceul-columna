<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\AbsenceMotivation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceMotivation>
 */
class AbsenceMotivationFactory extends Factory
{
    protected $model = AbsenceMotivation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '-1 week');

        return [
            'student_id' => Student::factory(),
            'requested_by_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'period_start' => $start->format('Y-m-d'),
            'period_end' => (clone $start)->modify('+2 days')->format('Y-m-d'),
            'status' => RequestStatus::Pending,
        ];
    }
}
