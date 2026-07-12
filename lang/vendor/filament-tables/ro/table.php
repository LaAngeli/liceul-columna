<?php

/*
 * Override RO pentru traducerile pachetului Filament Tables (namespace `filament-tables`).
 * Laravel merge-uiește RECURSIV acest fișier peste cel din pachet (array_replace_recursive), deci e
 * suficient să redefinim DOAR cheile pe care le corectăm — restul rămân din pachet.
 *
 * Motiv: heading-ul implicit de empty-state era „Niciun :model", unde `:model` e eticheta la PLURAL
 * a resursei → text negramatical în română („Niciun Anunțuri", „Niciun Comisii de examen", „Niciun
 * Orare"). Îl înlocuim cu o formulare neutră ca număr/gen, corectă pentru orice resursă (descoperit
 * LIVE, #37). Resursele care au deja un emptyStateHeading propriu nu sunt afectate.
 */

return [
    'empty' => [
        'heading' => 'Nu există înregistrări',
    ],
];
