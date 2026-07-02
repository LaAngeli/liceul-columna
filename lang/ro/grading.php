<?php

return [
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
];
