<?php

/*
|--------------------------------------------------------------------------
| Harta absențelor (Absențe → context de clasă) — RO
|--------------------------------------------------------------------------
| Fișier separat de panel.php DELIBERAT (04.08.2026): panel.php era în acel
| moment în lucru într-o sesiune paralelă, iar cheile hărții formează oricum
| un grup de sine stătător, ca la cabinet_calendar.php.
*/

return [
    'title' => 'Harta absențelor',
    'hint_status' => 'Apasă pe o absență ca să îi fixezi statutul — se salvează pe loc.',
    'hint_read' => 'Vezi absențele clasei pe zile; statutul îl fixează dirigintele clasei.',

    'student' => 'Elev',
    'totals' => 'Total',
    'day_count' => '{1} :count absență în această zi|[2,19] :count absențe în această zi|[20,*] :count de absențe în această zi',

    'whole_day' => 'Zi întreagă',

    // Marcajul unic al pastilei — ca „a"-ul din catalogul de hârtie; statutul îl spune culoarea,
    // disciplina rămâne la hover. Același semn în toate limbile, deliberat: e simbol, nu cuvânt.
    'marker' => 'A',

    'switch_to_list' => 'Vezi lista',
    'switch_to_map' => 'Vezi harta',

    'open_record' => 'Deschide fișa',

    'empty_period' => 'Nicio absență în perioada aleasă. Alege altă perioadă din bara de mai sus.',
    'overflow' => 'În perioada aleasă sunt :days zile cu absențe — prea multe pentru coloane. Restrânge perioada (Zi / Săptămână / Lună) ca harta să încapă pe ecran.',

    'status_reset' => 'Absența a revenit la „fără statut".',
    'status_denied' => 'Statutul acestei absențe nu poate fi schimbat de contul tău.',
];
