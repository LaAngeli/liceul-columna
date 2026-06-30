<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Coerență vârstă ↔ clasă, validată pe SERVER (oglindă a verificării din UI):
 * Clasa I ≈ 7 ani → vârsta nominală = N + 6, toleranță ±2 ani. Blochează „3 ani + Clasa IX".
 */
trait ValidatesChildAgeClass
{
    /** @var list<string> */
    private array $romanClasses = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    protected function validateAgeClassCoherence(Validator $validator): void
    {
        $age = $this->input('child_age');
        $class = $this->input('desired_class');

        if ($age === null || $age === '' || ! is_numeric($age) || ! is_string($class) || $class === '') {
            return;
        }

        $roman = trim(str_replace('Clasa', '', $class));
        $index = array_search($roman, $this->romanClasses, true);

        if ($index === false) {
            return;
        }

        $nominal = $index + 1 + 6;
        $min = $nominal - 2;
        $max = $nominal + 2;
        $ageInt = (int) $age;

        if ($ageInt < $min || $ageInt > $max) {
            $validator->errors()->add('child_age', (string) trans('site.admission.age_class_mismatch', ['min' => $min, 'max' => $max]));
        }
    }
}
