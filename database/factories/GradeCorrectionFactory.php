<?php

namespace Database\Factories;

use App\Enums\CorrectionStatus;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeCorrection>
 */
class GradeCorrectionFactory extends Factory
{
    protected $model = GradeCorrection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_id' => Grade::factory(),
            'requested_by_user_id' => User::factory(),
            'old_value' => fake()->numberBetween(4, 9),
            'new_value' => fake()->numberBetween(5, 10),
            'old_calificativ' => null,
            'new_calificativ' => null,
            'reason' => fake()->sentence(),
            'status' => CorrectionStatus::Pending,
        ];
    }
}
