<?php

namespace App\Support;

use App\Actions\ComputeTermAverage;
use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;

/**
 * Reguli comune de notare (§3): pragul de promovare, trunchierea la sutimi, tendința și formula
 * mediei semestriale. Sursă unică pentru toate, ca motorul de calcul, statutul elevului și
 * afișarea din cabinet să folosească aceeași regulă.
 */
final class Grades
{
    /**
     * Nota/medie minimă de promovare (§3): medie și componente ≥ 5,00.
     */
    public const PASS = 5.0;

    /**
     * Diferența de medie (puncte) de la care o schimbare contează ca TENDINȚĂ, nu ca zgomot.
     */
    public const TREND_THRESHOLD = 0.25;

    /**
     * Trunchiere la 2 zecimale, FĂRĂ rotunjire (8,567 → 8,56). Epsilonul compensează eroarea
     * de reprezentare în virgulă mobilă, fără a afecta granularitatea notelor.
     */
    public static function truncate2(float $value): float
    {
        return floor(($value + 1e-9) * 100) / 100;
    }

    /**
     * Tendința dintre două medii: `up` / `down` / `stable`. Sursă UNICĂ a pragului — dinamica
     * multi-an (foaia matricolă) și catalogul semestrial trebuie să numească la fel aceeași
     * diferență, altfel aceeași evoluție ar apărea „creștere" într-un ecran și „stabil" în altul.
     */
    public static function trend(float $previous, float $current): string
    {
        $delta = $current - $previous;

        if ($delta > self::TREND_THRESHOLD) {
            return 'up';
        }

        return $delta < -self::TREND_THRESHOLD ? 'down' : 'stable';
    }

    /**
     * Formula mediei semestriale (§1.3) — SURSĂ UNICĂ.
     *
     * Primar: MS = MC (nu există sumativă). Gimnaziu/liceu: MS = MC·(1−pondere) + sumativă·pondere
     * (0,50 → (MC+sumativă)/2) când există ambele; altfel componenta prezentă. Rezultatul e
     * trunchiat la sutimi, ca peste tot (§2.4).
     *
     * Trăiește aici, nu în {@see ComputeTermAverage}, fiindcă are DOI consumatori:
     * motorul care persistă media și graficul de evoluție din cabinet, care o recalculează după
     * fiecare notă. Două implementări ar diverge tăcut, iar ultimul punct al graficului n-ar mai
     * coincide cu media afișată lângă el — exact tipul de contradicție pe care familia o vede.
     */
    public static function semesterAverage(SchoolCycle $cycle, ?float $mc, ?float $summative): ?float
    {
        if ($cycle === SchoolCycle::Primar) {
            return $mc;
        }

        if ($mc !== null && $summative !== null) {
            $weight = EvaluationType::Teza->weight() ?? 0.5;

            return self::truncate2($mc * (1 - $weight) + $summative * $weight);
        }

        return $summative ?? $mc;
    }
}
