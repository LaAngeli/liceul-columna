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
        'pick_class_first' => 'Alegeți întâi clasa — disciplinele se filtrează după treapta ei.',
        'help' => 'Gimnaziul folosește ESS, liceul folosește teză (după ciclul clasei). Clasele primare (I–IV) nu au notă sumativă semestrială și nu apar aici.',
        'empty' => 'Nicio disciplină cu sumativă configurată încă.',
        'no_classes' => 'Anul acesta nu are clase de gimnaziu sau liceu',
        'no_classes_hint' => 'Sumativa semestrială există doar de la clasa a V-a în sus. Adaugă întâi clasele anului, din Configurare → Clase.',
        'card_unconfigured' => 'Nicio disciplină desemnată — garda nu blochează nimic aici',
        'coverage' => [
            'title' => ':configured din :total clase au disciplinele desemnate',
            'missing' => '{1}O clasă a rămas neconfigurată:|[2,*]:count clase au rămas neconfigurate:',
        ],
        'bulk' => [
            'label' => 'Desemnare în masă',
            'heading' => 'Desemnează disciplinele cu sumativă',
            'description' => 'Alege clasele și disciplinele deodată: se creează toate perechile care lipsesc. Ce există deja se sare, iar o disciplină care nu se predă la treapta unei clase nu se desemnează acolo.',
            'submit' => 'Desemnează',
            'classes' => 'Clasele',
            'classes_hint' => 'Doar gimnaziu și liceu — primarul nu are notă sumativă semestrială.',
            'subjects' => 'Disciplinele',
            'subjects_hint' => 'Se oferă disciplinele care se predau la cel puțin una dintre treptele alese.',
            'preview' => '{0}Nu e nimic de creat.|{1}Se creează o desemnare.|[2,19]Se creează :count desemnări.|[20,*]Se creează :count de desemnări.',
            'preview_empty' => 'Alege clasele și disciplinele — aici vei vedea exact ce se creează.',
            'preview_existing' => ':count perechi există deja și se sar.',
            'preview_mismatched' => ':count perechi nu se creează: disciplina nu se predă la treapta acelei clase.',
            'nothing' => 'Nu e nimic de creat: perechile alese există deja sau nu se potrivesc pe treaptă.',
            'done' => '{1}O desemnare creată.|[2,19]:count desemnări create.|[20,*]:count de desemnări create.',
        ],
    ],
    'staff' => [
        'section_averages' => 'Medii pe discipline (semestrul curent)',
        'no_averages' => 'Nicio medie calculată pentru semestrul curent.',
        'avg_current' => 'curente',
        'avg_summative' => 'sumativă',
    ],
];
