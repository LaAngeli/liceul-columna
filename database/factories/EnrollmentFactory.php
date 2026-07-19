<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        $year = AcademicYear::factory();

        return [
            'student_id' => Student::factory(),
            'school_class_id' => SchoolClass::factory(),
            'academic_year_id' => $year,
            // Mereu în trecutul cert (nu până la „azi"): fake()->date() putea cădea aleator DUPĂ
            // ferestrele verificate în teste, iar garda de transfer (wasEnrolledOn) tăia atunci
            // proiecțiile — flaky rar (~0,2%), dependent de deriva secvenței faker.
            'enrolled_on' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'left_on' => null,
        ];
    }
}
