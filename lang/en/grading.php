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
        'no_classes' => 'This year has no lower or upper secondary classes',
        'no_classes_hint' => 'The semester summative exists only from grade V upwards. Add the year classes first, from Configuration - Classes.',
        'card_unconfigured' => 'No subject designated - the guard blocks nothing here',
        'coverage' => [
            'title' => ':configured of :total classes have their subjects designated',
            'missing' => '{1}One class is still unconfigured:|[2,*]:count classes are still unconfigured:',
        ],
        'bulk' => [
            'label' => 'Bulk designation',
            'heading' => 'Designate the subjects with a summative',
            'description' => 'Pick classes and subjects at once: every missing pair is created. Existing ones are skipped, and a subject not taught at a class grade is not designated there.',
            'submit' => 'Designate',
            'classes' => 'Classes',
            'classes_hint' => 'Lower and upper secondary only - primary has no semester summative.',
            'subjects' => 'Subjects',
            'subjects_hint' => 'The subjects taught at least at one of the selected grades are offered.',
            'preview' => '{0}Nothing to create.|{1}One designation will be created.|[2,*]:count designations will be created.',
            'preview_empty' => 'Pick classes and subjects - this will show exactly what gets created.',
            'preview_existing' => ':count pairs already exist and are skipped.',
            'preview_mismatched' => ':count pairs are not created: the subject is not taught at that class grade.',
            'nothing' => 'Nothing to create: the chosen pairs already exist or do not fit the grade.',
            'done' => '{1}One designation created.|[2,*]:count designations created.',
        ],
    ],
    'staff' => [
        'section_averages' => 'Averages by subject (current term)',
        'no_averages' => 'No averages computed for the current term.',
        'avg_current' => 'current',
        'avg_summative' => 'summative',
    ],
];
