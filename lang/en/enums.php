<?php

/*
|--------------------------------------------------------------------------
| Enum labels (HasLabel) — EN
|--------------------------------------------------------------------------
| Same keys as lang/ro/enums.php. Missing key → RO fallback.
*/

return [
    'request_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'correction_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'student_status' => [
        'promovat' => 'Promoted',
        'corigent' => 'Make-up exam',
        'repetent' => 'Held back',
        'amanat' => 'Deferred',
    ],
    'admission_status' => [
        'nou' => 'New',
        'contactat' => 'Contacted',
        'inmatriculat' => 'Enrolled',
        'refuzat' => 'Declined',
    ],
    'admission_type' => [
        'visit' => 'Visit booking',
        'enrollment' => 'Enrollment request',
    ],
    'document_request_type' => [
        'invoire' => 'Leave request / planned absence',
        'adeverinta' => 'Student certificate request',
        'transfer' => 'Transfer / withdrawal request',
        'contestatie' => 'Grade re-examination / appeal request',
        'sedinta' => 'Meeting scheduling request',
    ],
    'message_type' => [
        'direct' => 'Message',
        'audience' => 'Audience request',
    ],
    'evaluation_type' => [
        'curenta' => 'Current',
        'esi' => 'ISA (intra-semester summative)',
        'teza' => 'Term paper',
        'ess' => 'ESS (semester summative)',
    ],
    'sex' => [
        'f' => 'Female',
        'm' => 'Male',
    ],
    'second_language' => [
        'fr' => 'French',
        'gm' => 'German',
        'nu' => 'None',
    ],
    'grading_type' => [
        'n' => 'Numeric grade',
        'c' => 'Mark',
        'cd' => 'Descriptive mark',
        'd' => 'Descriptive',
    ],
    'weekday' => [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
    ],
    'calendar_category' => [
        'homework' => 'Homework',
        'assessment' => 'Assessments and exams',
        'absence' => 'Absences',
        'deadline' => 'Deadlines',
        'event' => 'Events and meetings',
        'schedule' => 'Schedule',
        'structure' => 'Structure (terms, holidays)',
        'communication' => 'Communications',
    ],
    'calendar_event_type' => [
        'school_event' => 'School event',
        'meeting' => 'Meeting',
        'extracurricular' => 'Extracurricular activity',
        'deadline' => 'Deadline',
    ],
    'calendar_event_scope' => [
        'global' => 'Whole school',
        'grade_level' => 'One grade level',
        'school_class' => 'One class',
    ],
    'audience_domain' => [
        'instruire' => 'Instruction',
        'educatie' => 'Education',
    ],
    'corigenta_season' => [
        'iarna' => 'Winter',
        'vara' => 'Summer',
    ],
    'corigenta_session_type' => [
        'baza' => 'Base session',
        'repetata' => 'Repeat session',
    ],
    'corigenta_session_status' => [
        'draft' => 'Proposed (draft)',
        'approved' => 'Approved (order)',
        'published' => 'Published',
    ],
    'schedule_type' => [
        'orarul-lectiilor' => 'Lesson timetable',
        'orarul-sunetelor' => 'Bell schedule',
        'orarul-examenelor' => 'Exam timetable',
        'orarul-ess' => 'ESS timetable (term papers)',
        'orarul-pretestarilor' => 'Pre-testing timetable',
        'cursuri-de-pregatire-pentru-examene' => 'Exam preparation',
        'orarul-cpae' => 'CPAE timetable',
        'orar-recuperari' => 'Make-up timetable',
        'sedintele-cu-parintii' => 'Parent meetings',
    ],
    'academic_record_period' => [
        '1' => 'Term I',
        '2' => 'Term II',
        '3' => 'Annual average',
    ],
    'document_category' => [
        'reports' => 'Reports',
        'requests' => 'Requests',
        'notices' => 'Notices',
        'forms' => 'Forms',
        'useful' => 'Useful',
    ],
    'document_access_level' => [
        'public' => 'Public',
        'role_specific' => 'Role-specific',
        'individual' => 'Individual',
    ],
    'document_source' => [
        'static' => 'Static',
        'generated' => 'Generated',
    ],
    'generated_document_type' => [
        'transcript' => [
            'label' => 'Academic transcript',
            'description' => 'History of yearly averages by grade (term I / II / annual).',
        ],
        'term_situation' => [
            'label' => 'Term situation',
            'description' => 'Subject averages and absences for the current term.',
        ],
    ],
    'staff_report_type' => [
        'class_roster' => [
            'label' => 'Class roster',
            'description' => 'List of actively enrolled students.',
        ],
        'class_subject_situation' => [
            'label' => 'Class situation by subject',
            'description' => 'Each student\'s term average for a subject.',
        ],
        'class_full_situation' => [
            'label' => 'Full class situation',
            'description' => 'Each student\'s overall average and preliminary status.',
        ],
    ],
];
