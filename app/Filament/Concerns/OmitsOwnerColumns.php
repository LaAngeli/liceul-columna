<?php

namespace App\Filament\Concerns;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

/**
 * Tabelele din relation manager-ele unei fișe refolosesc tabelul GLOBAL al resursei (aceleași
 * coloane, filtre, acțiuni) — util pentru consistență, dar aduce cu ele coloana proprietarului:
 * pe fișa lui Bolotnicov Oleg, fiecare rând repetă „Bolotnicov Oleg". Redundant oriunde, dar pe
 * mobil e și scump: coloana ocupa 122 din cele 343 de puncte disponibile și împingea tabelul în
 * scroll orizontal, deși informația vizibilă încăpea (raportat de beneficiar, 2026-07-21).
 *
 * Coloanele rămase pot fi lăsate să se ÎNFĂȘOARE — Filament le ține pe un singur rând
 * (`whitespace-nowrap`), iar o denumire lungă („Limba și literatura română") lățea singură tabelul.
 */
trait OmitsOwnerColumns
{
    /**
     * Scoate coloanele redundante (proprietarul fișei) din tabelul deja configurat.
     *
     * @param  array<int, string>  $names
     */
    protected function withoutColumns(Table $table, array $names): Table
    {
        return $table->columns(array_values(Arr::except($table->getColumns(), $names)));
    }

    /**
     * Permite înfășurarea textului pe coloanele lungi, ca tabelul să se poată strânge sub lățimea
     * ecranului în loc să forțeze scroll orizontal.
     *
     * @param  array<int, string>  $names
     */
    protected function wrapColumns(Table $table, array $names): Table
    {
        $columns = $table->getColumns();

        foreach ($names as $name) {
            $column = $columns[$name] ?? null;

            // Doar coloanele de text se pot înfășura; restul (iconițe, badge-uri) n-au ce.
            if ($column instanceof TextColumn) {
                $column->wrap();
            }
        }

        return $table;
    }
}
