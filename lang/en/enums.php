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
        'expired' => 'Void',
        'withdrawn' => 'Withdrawn',
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
        'behavioral' => 'Behavior report',
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
        'global' => 'Whole school — all students and parents',
        'grade_level' => 'One year of study — all parallel classes',
        'school_class' => 'A single class',
        'students' => 'Specific students — chosen by name',
    ],
    'audience_reach' => [
        'student' => 'Student only',
        'guardians' => 'Parents only',
        'both' => 'Student and parents',
    ],
    'announcement_audience' => [
        'families' => 'All families — parents and students',
        'school' => 'Whole institution — families + staff',
        'classes' => 'Selected classes',
        'students' => 'Specific students — chosen by name',
        'subject_teachers' => 'Teachers of a subject',
        'users' => 'Specific accounts — chosen directly',
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
        'student_file' => [
            'label' => 'Student file',
            'description' => 'Combined document: current term situation and year-over-year average dynamics.',
        ],
        'absence_report' => [
            'label' => 'Absence report',
            'description' => 'All absences of the current school year, by date, with motivation status.',
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
        'student_ranking' => [
            'label' => 'Student ranking',
            'description' => 'The class ordered by the overall average of the current term.',
        ],
        'grade_distribution' => [
            'label' => 'Grade distribution',
            'description' => 'Histogram of active grades in a subject, with their mean.',
        ],
        'averages_evolution' => [
            'label' => 'Averages evolution',
            'description' => 'Class averages per subject, compared across the current year terms.',
        ],
        'subject_averages' => [
            'label' => 'Subject situation',
            'description' => 'The class average in every subject, with comparative bars.',
        ],
        'absence_statistics' => [
            'label' => 'Absence statistics',
            'description' => 'Class attendance: totals, motivated/unmotivated, monthly and per-student breakdown.',
        ],
        'promotion_rate' => [
            'label' => 'Class promotion rate',
            'description' => 'Promoted, failing and deferred students + subjects with the most failing grades.',
        ],
        'teacher_activity' => [
            'label' => 'Teacher activity',
            'description' => 'Grades and absences recorded in the current term, assignments and homeroom duty.',
        ],
        'school_overview' => [
            'label' => 'School overview',
            'description' => 'Current-year classes: headcount, class average and failing students — at a glance.',
        ],
    ],

    'holiday_type' => [
        'legal' => 'Legal holiday',
        'vacation' => 'School vacation',
        'institutional' => 'Institutional day',
        'other' => 'Other free day',
    ],
];
