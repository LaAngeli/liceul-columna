<?php

namespace App\Support;

/**
 * Reguli comune de notare (§3): pragul de promovare și trunchierea la sutimi. Sursă unică
 * pentru ambele, ca motorul de calcul, statutul elevului și afișarea să folosească aceeași regulă.
 */
final class Grades
{
    /**
     * Nota/medie minimă de promovare (§3): medie și componente ≥ 5,00.
     */
    public const PASS = 5.0;

    /**
     * Trunchiere la 2 zecimale, FĂRĂ rotunjire (8,567 → 8,56). Epsilonul compensează eroarea
     * de reprezentare în virgulă mobilă, fără a afecta granularitatea notelor.
     */
    public static function truncate2(float $value): float
    {
        return floor(($value + 1e-9) * 100) / 100;
    }
}
