<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Carbon;

/**
 * SURSA UNICĂ a „momentului școlar curent".
 *
 * Sistemul avea două adevăruri concurente: `terms.is_current` — DERIVAT automat din intervalele de
 * date (comanda `app:sync-current-term`, zilnic) și citit peste tot — și `academic_years.is_current`,
 * un toggle MANUAL. Cât timp cineva îl bifa corect coincideau; la trecerea în anul nou însă
 * scheduler-ul mută semestrul singur, iar flagul de an nu-l urmează niciodată — deci două ecrane ar
 * fi arătat ani diferiți, fără ca nimic să semnaleze divergența.
 *
 * Regula stabilită: **semestrul e sursa, anul se derivă din el**. Flagul de pe an rămâne doar o
 * oglindă (util pentru raportări SQL directe), scris de aceeași comandă, niciodată de mână.
 *
 * Toate metodele întorc `null` când școala n-are încă structură definită — apelantul decide ce
 * înseamnă asta. Un `(int)` peste null ar da 0, adică „anul cu id 0", care nu există: filtrele ar
 * returna tăcut zero rânduri în loc să semnaleze că lipsește configurarea.
 */
final class SchoolCalendar
{
    /**
     * Fusul orar al ȘCOLII. Aplicația stochează în UTC (convenția corectă pentru timestamps), dar
     * orele din orare — „Lecția 1 08.15 – 09.00" — sunt ore locale de Chișinău. Orice comparație
     * „acum vs ora lecției" făcută în UTC greșește cu 2-3 ore: la 14:39 locală, grila marca drept
     * „Acum" lecția de la 11:15 (prins la verificarea live). Un singur liceu → un singur fus, aici.
     */
    public const TIMEZONE = 'Europe/Chisinau';

    /** Momentul curent în fusul școlii — pentru comparații cu orele din orare. */
    public static function localNow(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function currentTerm(): ?Term
    {
        return Term::query()->where('is_current', true)->first();
    }

    public static function currentTermId(): ?int
    {
        $id = Term::query()->where('is_current', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Anul școlar curent = anul semestrului curent. NU se citește `academic_years.is_current`
     * (oglindă, poate rămâne în urmă dacă cineva rulează comanda parțial sau editează manual).
     */
    public static function currentYearId(): ?int
    {
        $id = Term::query()->where('is_current', true)->value('academic_year_id');

        return $id === null ? null : (int) $id;
    }

    public static function currentYear(): ?AcademicYear
    {
        $id = self::currentYearId();

        return $id === null ? null : AcademicYear::query()->find($id);
    }

    /**
     * Intervalul calendaristic al unui an școlar: datele configurate sau, în lipsa lor,
     * 1 septembrie – 31 august deduse din denumire („2025-2026"). Folosit de planificatorul
     * zilelor libere și de generatorul de sărbători legale — aceeași definiție în ambele.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function yearSpan(AcademicYear $year): array
    {
        if ($year->starts_on !== null && $year->ends_on !== null) {
            return [Carbon::parse($year->starts_on), Carbon::parse($year->ends_on)];
        }

        preg_match('/(\d{4})/', $year->name, $matches);
        $start = (int) ($matches[1] ?? self::localNow()->year);

        return [
            $year->starts_on !== null ? Carbon::parse($year->starts_on) : Carbon::create($start, 9, 1),
            $year->ends_on !== null ? Carbon::parse($year->ends_on) : Carbon::create($start + 1, 8, 31),
        ];
    }
}
