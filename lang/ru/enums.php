<?php

/*
|--------------------------------------------------------------------------
| Etichete enum (HasLabel) — RU
|--------------------------------------------------------------------------
| Aceleași chei ca în lang/ro/enums.php. Cheie lipsă → fallback RO.
*/

return [
    'request_status' => [
        'pending' => 'В ожидании',
        'approved' => 'Одобрено',
        'rejected' => 'Отклонено',
    ],
    'correction_status' => [
        'pending' => 'В ожидании',
        'approved' => 'Одобрено',
        'rejected' => 'Отклонено',
        'expired' => 'Утратило силу',
        'withdrawn' => 'Отозвано',
    ],
    'student_status' => [
        'promovat' => 'Переведён',
        'corigent' => 'На переэкзаменовке',
        'repetent' => 'Оставлен на второй год',
        'amanat' => 'Отложен',
    ],
    'admission_status' => [
        'nou' => 'Новая',
        'contactat' => 'Связались',
        'inmatriculat' => 'Зачислен',
        'refuzat' => 'Отклонена',
    ],
    'admission_type' => [
        'visit' => 'Запись на визит',
        'enrollment' => 'Заявка на зачисление',
    ],
    'document_request_type' => [
        'invoire' => 'Заявление на отгул / запланированный пропуск',
        'adeverinta' => 'Заявление на справку об обучении',
        'transfer' => 'Заявление о переводе / отчислении',
        'contestatie' => 'Заявление о пересмотре / апелляции оценки',
        'sedinta' => 'Заявление о назначении встречи',
    ],
    'message_type' => [
        'direct' => 'Сообщение',
        'audience' => 'Запрос на приём',
    ],
    'evaluation_type' => [
        'curenta' => 'Текущая',
        'esi' => 'ВСО (внутрисеместровая суммативная)',
        'teza' => 'Контрольная',
        'ess' => 'СОС (семестровая суммативная)',
    ],
    'sex' => [
        'f' => 'Женский',
        'm' => 'Мужской',
    ],
    'second_language' => [
        'fr' => 'Французский',
        'gm' => 'Немецкий',
        'nu' => 'Нет',
    ],
    'grading_type' => [
        'n' => 'Числовая оценка',
        'c' => 'Балл',
        'cd' => 'Описательный балл',
        'd' => 'Описательная',
    ],
    'weekday' => [
        '1' => 'Понедельник',
        '2' => 'Вторник',
        '3' => 'Среда',
        '4' => 'Четверг',
        '5' => 'Пятница',
        '6' => 'Суббота',
    ],
    'calendar_category' => [
        'homework' => 'Домашние задания',
        'assessment' => 'Оценивания и экзамены',
        'absence' => 'Пропуски',
        'deadline' => 'Сроки',
        'event' => 'События и собрания',
        'schedule' => 'Расписание',
        'structure' => 'Структура (семестры, каникулы)',
        'communication' => 'Сообщения',
    ],
    'calendar_event_type' => [
        'school_event' => 'Школьное событие',
        'meeting' => 'Собрание',
        'extracurricular' => 'Внеклассное мероприятие',
        'deadline' => 'Срок',
    ],
    'calendar_event_scope' => [
        'global' => 'Вся школа',
        'grade_level' => 'Одна ступень',
        'school_class' => 'Один класс',
    ],
    'audience_domain' => [
        'instruire' => 'Обучение',
        'educatie' => 'Воспитание',
    ],
    'corigenta_season' => [
        'iarna' => 'Зима',
        'vara' => 'Лето',
    ],
    'corigenta_session_type' => [
        'baza' => 'Основная сессия',
        'repetata' => 'Повторная сессия',
    ],
    'corigenta_session_status' => [
        'draft' => 'Предложена (черновик)',
        'approved' => 'Одобрена (приказ)',
        'published' => 'Опубликована',
    ],
    'schedule_type' => [
        'orarul-lectiilor' => 'Расписание уроков',
        'orarul-sunetelor' => 'Расписание звонков',
        'orarul-examenelor' => 'Расписание экзаменов',
        'orarul-ess' => 'Расписание СОС (контрольных)',
        'orarul-pretestarilor' => 'Расписание предтестирований',
        'cursuri-de-pregatire-pentru-examene' => 'Подготовка к экзаменам',
        'orarul-cpae' => 'Расписание CPAE',
        'orar-recuperari' => 'Расписание отработок',
        'sedintele-cu-parintii' => 'Родительские собрания',
    ],
    'academic_record_period' => [
        '1' => 'Семестр I',
        '2' => 'Семестр II',
        '3' => 'Годовая средняя',
    ],
    'document_category' => [
        'reports' => 'Отчёты',
        'requests' => 'Заявления',
        'notices' => 'Извещения',
        'forms' => 'Бланки',
        'useful' => 'Полезное',
    ],
    'document_access_level' => [
        'public' => 'Публичный',
        'role_specific' => 'По роли',
        'individual' => 'Индивидуальный',
    ],
    'document_source' => [
        'static' => 'Статический',
        'generated' => 'Генерируемый',
    ],
    'generated_document_type' => [
        'transcript' => [
            'label' => 'Табель успеваемости',
            'description' => 'История годовых средних по классам (сем. I / II / годовая).',
        ],
        'term_situation' => [
            'label' => 'Учебная ситуация',
            'description' => 'Средние по предметам и пропуски за текущий семестр.',
        ],
    ],
    'staff_report_type' => [
        'class_roster' => [
            'label' => 'Список класса',
            'description' => 'Список активно зачисленных учеников.',
        ],
        'class_subject_situation' => [
            'label' => 'Ситуация класса по предмету',
            'description' => 'Семестровая средняя каждого ученика по предмету.',
        ],
        'class_full_situation' => [
            'label' => 'Полная ситуация класса',
            'description' => 'Общая средняя и предварительный статус каждого ученика.',
        ],
    ],
];
