<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        // Plafonat la 2090: testele folosesc ani hardcodați „în viitorul îndepărtat" (2098–2100,
        // ex. RoleInteractionCoherenceTest) — un an generat aleator peste ei dădea coliziune
        // UNIQUE pe `name` (flaky, 1/100, dependent de secvența faker).
        $start = fake()->unique()->numberBetween(2000, 2090);

        return [
            'name' => $start.'–'.($start + 1),
            'starts_on' => $start.'-09-01',
            'ends_on' => ($start + 1).'-06-30',
            'is_current' => false,
        ];
    }
}
