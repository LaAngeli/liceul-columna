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
        //
        // Plafonul singur NU ajunge (07.08.2026): alte teste fixează ani DIN interval („2019–2020",
        // AbsencesNavigatorTest/ConfigNavigatorsTest), iar `unique()` al faker-ului nu știe nimic
        // despre ce s-a scris deja în bază — deci pica exact la fel, doar mai rar. Aici întrebăm
        // baza. `withTrashed`: indexul unic vede și rândurile șterse logic.
        $taken = AcademicYear::query()->withTrashed()->pluck('name')->all();

        do {
            $start = fake()->unique()->numberBetween(2000, 2090);
        } while (in_array($start.'–'.($start + 1), $taken, true));

        return [
            'name' => $start.'–'.($start + 1),
            'starts_on' => $start.'-09-01',
            'ends_on' => ($start + 1).'-06-30',
            'is_current' => false,
        ];
    }
}
