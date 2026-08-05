<?php

/*
|--------------------------------------------------------------------------
| Harta notelor (elevi × zile) — RO
|--------------------------------------------------------------------------
| Pastila poartă VALOAREA notei; culoarea spune pragul (sub 5 = roșu),
| accentul chihlimbar = sumativa — aceeași convenție ca în cabinet.
*/

return [
    'title' => 'Harta notelor',
    'hint_act' => 'Apasă pe o notă pentru detalii și acțiunile permise — anularea și corecția trec prin fluxurile obișnuite.',
    'hint_read' => 'Vezi notele clasei pe zile; valoarea e pastila, disciplina apare la apăsare.',

    'student' => 'Elev',
    'totals' => 'Total',
    'day_count' => '{1} :count notă în această zi|[2,19] :count note în această zi|[20,*] :count de note în această zi',

    'legend_below' => 'Sub 5',
    'legend_summative' => 'Sumativă',
    'legend_pending' => 'Corecție în așteptare',

    // Etichetele micro-coloanelor din antetul Total (redesign 05.08.2026): fiecare pistă își
    // spune numele, fără marcaje criptice.
    'totals_grades' => 'Note',
    'totals_average' => 'Media',

    // Eticheta filtrului de tip — ACELEAȘI cuvinte ca în borderou (panel.class_register.filters_label):
    // același gest, același nume, pe ambele ecrane.
    'filter_type_label' => 'Vezi notele',

    'switch_to_list' => 'Vezi lista',
    'switch_to_map' => 'Vezi harta',

    'open_record' => 'Deschide fișa',
    'action_denied' => 'Nu ai dreptul să faci această operație pe nota aleasă.',

    'scroll_left' => 'Derulează zilele spre stânga',
    'scroll_right' => 'Derulează zilele spre dreapta',

    'empty_period' => 'Nicio notă de tipul „:type” în perioada aleasă. Schimbă tipul sau perioada din bara de mai sus.',
];
