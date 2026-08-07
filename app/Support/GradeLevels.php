<?php

namespace App\Support;

/**
 * Treptele de școlarizare (clasele 1-12) în notația ROMANĂ folosită de documentele școlare.
 * Treapta e o CLASĂ (I–XII), nu o scară de notare (notele sunt 1–10 — docs/STRUCTURA-CATALOG.md).
 */
final class GradeLevels
{
    /** @var array<int, string> cifrele romane ale treptelor I–XII */
    public const ROMAN = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public static function roman(int $gradeLevel): string
    {
        return self::ROMAN[$gradeLevel] ?? (string) $gradeLevel;
    }

    /** Interval de trepte („I–VIII"; o singură treaptă rămâne simplă: „V"). */
    public static function span(int $from, int $to): string
    {
        return $from === $to ? self::roman($from) : self::roman($from).'–'.self::roman($to);
    }

    /**
     * SET de trepte, compresat pe rulaje consecutive: [1,2,3,4] → „I–IV"; [5,6,9] → „V–VI, IX".
     * Din 07.08.2026 disciplina poartă un set discret (nu un interval), deci golurile sunt
     * legitime și trebuie să se VADĂ — un „V–XII" mincinos peste un set cu pauze ar reintroduce
     * exact ambiguitatea pe care setul o elimină.
     *
     * @param  list<int>  $levels  trepte 1–12, în orice ordine
     */
    public static function list(array $levels): string
    {
        $levels = array_values(array_unique(array_map(intval(...), $levels)));
        sort($levels);

        if ($levels === []) {
            return '';
        }

        $runs = [];
        $start = $prev = $levels[0];

        foreach (array_slice($levels, 1) as $grade) {
            if ($grade === $prev + 1) {
                $prev = $grade;

                continue;
            }

            $runs[] = self::span($start, $prev);
            $start = $prev = $grade;
        }

        $runs[] = self::span($start, $prev);

        return implode(', ', $runs);
    }
}
