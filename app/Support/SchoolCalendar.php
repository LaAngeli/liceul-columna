<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Term;

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
}
