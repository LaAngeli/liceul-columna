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
    ],
    'student_status' => [
        'promovat' => 'Promovat',
        'corigent' => 'Corigent',
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
];
