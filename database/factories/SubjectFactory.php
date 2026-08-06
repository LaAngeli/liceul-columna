<?php

namespace Database\Factories;

use App\Enums\GradingType;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'abbreviation' => fake()->lexify('????'),
            // Interval COMPLET (I–XII), nu 5–12: `SchoolClassFactory` alege o treaptă ALEATORIE
            // 1–12, deci implicitul vechi împerechea o treime din rulări cu o clasă de primar.
            // Nu deranja pe nimeni până a apărut o regulă care chiar verifică potrivirea — atunci
            // testul a început să pice pe zaruri. Intervalul se DĂ (nu se lasă null), fiindcă
            // formularul de disciplină îl cere: o fișă fără trepte nu e o stare pe care aplicația
            // o poate produce. Testele care au nevoie de un ciclu anume îl dau explicit.
            'min_grade' => 1,
            'max_grade' => 12,
            'grading_type' => GradingType::Numeric,
        ];
    }
}
