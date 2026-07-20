<?php

return [
    'annul' => [
        'no_reason' => 'unspecified',
    ],
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
        'pick_class_first' => 'Choose the class first — subjects are filtered by its grade level.',
        'help' => 'Lower secondary uses ESS, upper secondary uses the term paper (by the class cycle). Primary classes (I–IV) have no semester summative and do not appear here.',
        'empty' => 'No summative subjects configured yet.',
    ],
    'staff' => [
        'section_averages' => 'Averages by subject (current term)',
        'no_averages' => 'No averages computed for the current term.',
        'avg_current' => 'current',
        'avg_summative' => 'summative',
    ],
];
