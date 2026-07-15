<?php

namespace Database\Factories;

use App\Enums\CorrectionStatus;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeworkCorrection>
 */
class HomeworkCorrectionFactory extends Factory
{
    protected $model = HomeworkCorrection::class;

    public function definition(): array
    {
        return [
            'homework_assignment_id' => HomeworkAssignment::factory(),
            'requested_by_user_id' => User::factory(),
            'new_required_task' => fake()->sentence(),
            'reason' => fake()->sentence(),
            'status' => CorrectionStatus::Pending,
        ];
    }
}
