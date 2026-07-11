<?php

return [
    'annul' => [
        // Fallback pentru corpul notificării de anulare, când nota a fost anulată pe o cale fără
        // motiv (API/import) — la anularea din panou motivul e obligatoriu, deci rar folosit.
        'no_reason' => 'nespecificat',
    ],
    'summative' => [
        'not_designated' => 'La această clasă, nota sumativă semestrială se pune doar la disciplinele stabilite prin ordin — disciplina aleasă nu e printre ele.',
    ],
    'designation' => [
        'nav' => 'Discipline cu sumativă',
        'single' => 'Disciplină cu sumativă',
        'plural' => 'Discipline cu sumativă',
        'fields' => [
            'class' => 'Clasa',
            'subject' => 'Disciplina',
            'order_reference' => 'Referință ordin',
            'summative_type' => 'Tip sumativă',
        ],
        'help' => 'Gimnaziul folosește ESS, liceul folosește teză (după ciclul clasei). Clasele primare (I–IV) nu au notă sumativă semestrială și nu apar aici.',
        'empty' => 'Nicio disciplină cu sumativă configurată încă.',
    ],
    'staff' => [
        'section_averages' => 'Medii pe discipline (semestrul curent)',
        'no_averages' => 'Nicio medie calculată pentru semestrul curent.',
        'avg_current' => 'curente',
        'avg_summative' => 'sumativă',
    ],
];
