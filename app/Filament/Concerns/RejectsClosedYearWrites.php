<?php

namespace App\Filament\Concerns;

use App\Models\Term;
use Illuminate\Validation\ValidationException;

/**
 * Într-un an ÎNCHIS nu se mai scrie în catalog.
 *
 * Arhivarea mută mediile în foaia matricolă — actul oficial al școlarizării acelui an. O notă sau o
 * absență introdusă după acel moment intra până acum fără nicio obiecție, dar nu mai avea cum să
 * ajungă în foaie: catalogul și arhiva începeau să spună lucruri diferite despre același elev, iar
 * discrepanța se descoperea abia la eliberarea unui act. Refuzul e la SCRIERE, unde e ieftin de
 * explicat, nu la citire.
 *
 * EXCEPȚIA e fluxul de CORECȚIE (cerere → aprobare), care aplică valoarea direct pe model, în afara
 * formularelor — deliberat: o greșeală descoperită după închidere trebuie să poată fi îndreptată,
 * dar numai pe calea cu urmă și cu aprobare, nu prin editare tăcută.
 */
trait RejectsClosedYearWrites
{
    /**
     * @param  string  $field  câmpul pe care se agață mesajul (data faptei — acolo se uită operatorul)
     */
    protected function rejectClosedYear(mixed $termId, string $field): void
    {
        if (! is_numeric($termId)) {
            return;
        }

        $year = Term::query()
            ->whereKey((int) $termId)
            ->with('academicYear')
            ->first()
            ?->academicYear;

        if ($year === null || ! $year->isClosed()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('panel.validation.closed_year', ['year' => $year->name]),
        ]);
    }
}
