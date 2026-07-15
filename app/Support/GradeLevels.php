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
}
