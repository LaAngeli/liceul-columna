<?php

/*
|--------------------------------------------------------------------------
| Etichete enum (HasLabel) — RO
|--------------------------------------------------------------------------
| Cheie = <enum_snake>.<case_value>. Fiecare enum HasLabel își ia eticheta
| prin trans('enums.<grup>.'.$this->value), cu fallback automat la RO.
| Valorile RO sunt EXACT cele istorice (extrase din match-urile vechi) — RO
| nu se schimbă pentru niciun utilizator. (UserRole se traduce prin site.roles.)
*/

return [
    'request_status' => [
        'pending' => 'În așteptare',
        'approved' => 'Aprobată',
        'rejected' => 'Respinsă',
    ],
    'correction_status' => [
        'pending' => 'În așteptare',
        'approved' => 'Aprobată',
        'rejected' => 'Respinsă',
        'expired' => 'Caducă',
        'withdrawn' => 'Retrasă',
    ],
    'student_status' => [
        'promovat' => 'Promovat',
        'corigent' => 'Corigent',
        'repetent' => 'Repetent',
        'amanat' => 'Amânat',
    ],
    'admission_status' => [
        'nou' => 'Nou',
        'contactat' => 'Contactat',
        'inmatriculat' => 'Înmatriculat',
        'refuzat' => 'Refuzat',
    ],
    'admission_type' => [
        'visit' => 'Programare vizită',
        'enrollment' => 'Cerere de înmatriculare',
    ],
    'document_request_type' => [
        'invoire' => 'Cerere de învoire / absență planificată',
        'adeverinta' => 'Cerere de adeverință de elev',
        'transfer' => 'Cerere de transfer / retragere',
        'contestatie' => 'Cerere de reexaminare / contestație a unei note',
        'sedinta' => 'Cerere de programare a unei ședințe',
    ],
    'message_type' => [
        'direct' => 'Mesaj',
        'audience' => 'Solicitare audiență',
    ],
    'evaluation_type' => [
        'curenta' => 'Curentă',
        'esi' => 'ESI (sumativă intrasemestrială)',
        'teza' => 'Teză',
        'ess' => 'ESS (sumativă semestrială)',
    ],
    'sex' => [
        'f' => 'Feminin',
        'm' => 'Masculin',
    ],
    'second_language' => [
        'fr' => 'Franceză',
        'gm' => 'Germană',
        'nu' => 'Fără',
    ],
    'grading_type' => [
        'n' => 'Notă numerică',
        'c' => 'Calificativ',
        'cd' => 'Calificativ descriptiv',
        'd' => 'Descriptiv',
    ],
    'weekday' => [
        '1' => 'Luni',
        '2' => 'Marți',
        '3' => 'Miercuri',
        '4' => 'Joi',
        '5' => 'Vineri',
        '6' => 'Sâmbătă',
    ],
    'calendar_category' => [
        'homework' => 'Teme',
        'assessment' => 'Evaluări și examene',
        'absence' => 'Absențe',
        'deadline' => 'Termene-limită',
        'event' => 'Evenimente și ședințe',
        'schedule' => 'Orar',
        'structure' => 'Structură (semestre, vacanțe)',
        'communication' => 'Comunicări',
    ],
    'calendar_event_type' => [
        'school_event' => 'Eveniment școlar',
        'meeting' => 'Ședință',
        'extracurricular' => 'Activitate extracurriculară',
        'deadline' => 'Termen-limită',
    ],
    'calendar_event_scope' => [
        'global' => 'Toată școala',
        'grade_level' => 'O treaptă',
        'school_class' => 'O clasă',
    ],
    'audience_domain' => [
        'instruire' => 'Instruire',
        'educatie' => 'Educație',
    ],
    'corigenta_season' => [
        'iarna' => 'Iarnă',
        'vara' => 'Vară',
    ],
    'corigenta_session_type' => [
        'baza' => 'Sesiune de bază',
        'repetata' => 'Sesiune repetată',
    ],
    'corigenta_session_status' => [
        'draft' => 'Propusă (ciornă)',
        'approved' => 'Aprobată (ordin)',
        'published' => 'Publicată',
    ],
    'schedule_type' => [
        'orarul-lectiilor' => 'Orarul lecțiilor',
        'orarul-sunetelor' => 'Orarul sunetelor',
        'orarul-examenelor' => 'Orarul examenelor',
        'orarul-ess' => 'Orarul ESS (teze)',
        'orarul-pretestarilor' => 'Orarul pretestărilor',
        'cursuri-de-pregatire-pentru-examene' => 'Pregătire pentru examene',
        'orarul-cpae' => 'Orarul CPAE',
        'orar-recuperari' => 'Orar recuperări',
        'sedintele-cu-parintii' => 'Ședințele cu părinții',
    ],
    'academic_record_period' => [
        '1' => 'Semestrul I',
        '2' => 'Semestrul II',
        '3' => 'Media anuală',
    ],
    'document_category' => [
        'reports' => 'Rapoarte',
        'requests' => 'Cereri',
        'notices' => 'Înștiințări',
        'forms' => 'Formulare',
        'useful' => 'Utile',
    ],
    'document_access_level' => [
        'public' => 'Public',
        'role_specific' => 'Rol-specific',
        'individual' => 'Individual',
    ],
    'document_source' => [
        'static' => 'Static',
        'generated' => 'Generat',
    ],
    'generated_document_type' => [
        'transcript' => [
            'label' => 'Foaia matricolă',
            'description' => 'Istoricul mediilor pe trepte (sem. I / II / anuală).',
        ],
        'term_situation' => [
            'label' => 'Situația școlară',
            'description' => 'Mediile pe discipline și absențele din semestrul curent.',
        ],
    ],
    'staff_report_type' => [
        'class_roster' => [
            'label' => 'Lista de clasă',
            'description' => 'Lista elevilor înmatriculați activ.',
        ],
        'class_subject_situation' => [
            'label' => 'Situația clasei la disciplină',
            'description' => 'Media semestrială a fiecărui elev la o disciplină.',
        ],
        'class_full_situation' => [
            'label' => 'Situația completă a clasei',
            'description' => 'Media generală și statutul preliminar al fiecărui elev.',
        ],
        'student_ranking' => [
            'label' => 'Clasamentul elevilor',
            'description' => 'Elevii clasei ordonați după media generală a semestrului curent.',
        ],
        'grade_distribution' => [
            'label' => 'Distribuția notelor',
            'description' => 'Histograma notelor active la o disciplină, cu media lor.',
        ],
        'averages_evolution' => [
            'label' => 'Evoluția mediilor',
            'description' => 'Media clasei pe discipline, comparată între semestrele anului curent.',
        ],
        'subject_averages' => [
            'label' => 'Situația disciplinelor',
            'description' => 'Media clasei la fiecare disciplină, cu bare comparative.',
        ],
        'absence_statistics' => [
            'label' => 'Statistica absențelor',
            'description' => 'Frecvența clasei: totaluri, motivate/nemotivate, defalcare pe luni și pe elevi.',
        ],
        'promotion_rate' => [
            'label' => 'Promovabilitatea clasei',
            'description' => 'Promovați, corigenți și amânați + disciplinele cu cele mai multe restanțe.',
        ],
        'teacher_activity' => [
            'label' => 'Activitatea profesorilor',
            'description' => 'Note și absențe consemnate în semestrul curent, alocările și diriginția fiecărui cadru.',
        ],
        'school_overview' => [
            'label' => 'Sinteza școlii',
            'description' => 'Clasele anului curent: efective, media clasei și corigenți — dintr-o privire.',
        ],
    ],
];
