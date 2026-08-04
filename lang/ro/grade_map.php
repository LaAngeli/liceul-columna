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

    // Marcajele pistelor din coloana Total — scurte, ca să încapă lângă cifră; sensul complet
    // stă în legendă și în textul citit de cititorul de ecran.
    'below_marker' => '<5',
    'summative_marker' => 'S',

    'switch_to_list' => 'Vezi lista',
    'switch_to_map' => 'Vezi harta',

    'open_record' => 'Deschide fișa',
    'action_denied' => 'Nu ai dreptul să faci această operație pe nota aleasă.',

    'scroll_left' => 'Derulează zilele spre stânga',
    'scroll_right' => 'Derulează zilele spre dreapta',

    'empty_period' => 'Nicio notă în perioada aleasă. Alege altă perioadă din bara de mai sus.',
];
