<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SummativeDesignation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SummativeDesignation>
 */
class SummativeDesignationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'school_class_id' => SchoolClass::factory(),
            'order_reference' => null,
        ];
    }
}
