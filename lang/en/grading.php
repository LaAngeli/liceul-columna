<?php

return [
    'summative' => [
        'not_designated' => 'For this class, the semester summative grade may only be entered for subjects set by order — the chosen subject is not one of them.',
    ],
    'designation' => [
        'nav' => 'Summative subjects',
        'single' => 'Summative subject',
        'plural' => 'Summative subjects',
        'fields' => [
            'class' => 'Class',
            'subject' => 'Subject',
            'order_reference' => 'Order reference',
            'summative_type' => 'Summative type',
        ],
        'help' => 'Lower secondary uses ESS, upper secondary uses the term paper (by the class cycle). Primary classes (I–IV) have no semester summative and do not appear here.',
        'empty' => 'No summative subjects configured yet.',
    ],
];
