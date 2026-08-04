<?php

namespace App\Support;

use App\Models\Term;

/**
 * Semestrele oferite în FILTRE — sursă unică pentru catalogul curent și pentru arhivă.
 *
 * Dilema (cerința beneficiarului, 04.08.2026): un dropdown cu toate semestrele din istorie e, în
 * secțiunile de lucru, o listă care crește cu fiecare an — și în care „Semestrul I" apare de N ori,
 * identic, fiindcă numele semestrului nu spune din ce an e. În catalogul curent nu ai ce alege
 * dintre ele; în arhiva unui elev, dimpotrivă, exact anul face diferența.
 *
 * De aceea: {@see current()} pentru paginile de catalog (doar anul activ, etichete scurte),
 * {@see all()} pentru fișa elevului și orice vedere istorică (toate, cu anul lipit de etichetă ca
 * să nu mai existe două opțiuni la fel).
 */
final class TermOptions
{
    /**
     * Semestrele anului ACTIV. Fără an curent definit (rollover neefectuat) cade pe {@see all()}:
     * mai bine un filtru lung decât unul gol.
     *
     * @return array<int, string>
     */
    public static function current(): array
    {
        $yearId = SchoolCalendar::currentYearId();

        if ($yearId === null) {
            return self::all();
        }

        return Term::query()
            ->where('academic_year_id', $yearId)
            ->orderBy('starts_on')
            ->get()
            ->mapWithKeys(fn (Term $term): array => [
                (int) $term->getKey() => ContentTranslator::term((string) $term->name),
            ])
            ->all();
    }

    /**
     * TOATE semestrele, cu anul în etichetă („Semestrul I · 2019–2020") — fără el, două opțiuni
     * din ani diferiți arată identic și alegerea devine ghicit.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return Term::query()
            ->with('academicYear')
            ->orderByDesc('starts_on')
            ->get()
            ->mapWithKeys(fn (Term $term): array => [
                (int) $term->getKey() => ContentTranslator::term((string) $term->name)
                    .($term->academicYear !== null ? ' · '.$term->academicYear->name : ''),
            ])
            ->all();
    }
}
