<?php

/**
 * Override RO pentru heading-ul de empty-state Filament (#37, descoperit LIVE): implicitul pachetului
 * era „Niciun :model" cu eticheta la PLURAL → negramatical („Niciun Anunțuri"). Override-ul îl face
 * neutru ca număr/gen, iar merge-ul recursiv Laravel păstrează restul cheilor din pachet.
 */

it('heading-ul de empty-state e neutru gramatical (nu „Niciun {plural}")', function () {
    $heading = trans('filament-tables::table.empty.heading', [], 'ro');

    expect($heading)->toBe('Nu există înregistrări')
        ->and($heading)->not->toContain('Niciun');
});

it('override-ul NU șterge celelalte chei din pachet (merge recursiv)', function () {
    // O cheie-soră din pachet trebuie să rămână disponibilă după override.
    expect(trans('filament-tables::table.filters.heading', [], 'ro'))->toBe('Filtre');
});
