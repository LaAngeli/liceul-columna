<?php

namespace Database\Factories;

use App\Enums\SchoolCycle;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        // Numele urmează convenția REALĂ: cifra romană a treptei (garda de model îl generează
        // oricum când lipsește); secția e mereu MAJUSCULĂ (normalizată la salvare).
        $gradeLevel = fake()->numberBetween(1, 12);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_level' => $gradeLevel,
            'name' => SchoolCycle::romanNumeral($gradeLevel),
            'section' => mb_strtoupper(fake()->unique()->bothify('?#')),
            'homeroom_teacher_id' => null,
        ];
    }
}
