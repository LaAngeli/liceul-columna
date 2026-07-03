<?php

/**
 * Шаблоны уведомлений (специф. §5) — заранее подготовленные по языкам, БЕЗ перевода в реальном
 * времени. Каждый получатель получает вариант на выбранном им языке (`User::notificationLocale()`).
 */
return [
    'types' => [
        'new_grade' => 'Новая оценка',
        'new_absence' => 'Новый пропуск',
        'new_homework' => 'Ежедневная сводка заданий',
        'new_message' => 'Новое сообщение',
        'status_change' => 'Изменение статуса',
        'announcement' => 'Объявление руководства',
        'grade_correction_request' => 'Исправление оценки на утверждение',
        'absence_motivation_submitted' => 'Новое заявление об оправдании',
        'document_request_submitted' => 'Новое типовое заявление',
        'admission_request_submitted' => 'Новая заявка на зачисление',
    ],

    'channels' => [
        'cabinet' => 'Кабинет (в приложении)',
        'email' => 'Эл. почта',
        'telegram' => 'Telegram',
        'viber' => 'Viber',
    ],

    // Подпись кнопки действия в письме.
    'open' => 'Открыть',

    'new_grade' => [
        'title' => 'Новая оценка',
        'body' => 'Ученик :student получил новую оценку по предмету :subject.',
    ],
    'new_absence' => [
        'title' => 'Новый пропуск',
        'body' => 'Зарегистрирован новый пропуск у :student.',
    ],
    'new_homework' => [
        'title' => 'Новые задания сегодня',
        'body' => 'Новые задания сегодня для класса :class. Всего: :count.',
    ],
    'new_message' => [
        'title' => 'Новое сообщение',
        'body' => 'Вам пришло сообщение от :sender.',
    ],
    'status_change' => [
        'title' => 'Изменение статуса',
        'body' => 'Статус ученика :student обновлён: :status.',
    ],
    'announcement' => [
        'title' => 'Объявление',
        'body' => 'Руководство лицея опубликовало новое объявление.',
    ],
    'grade_correction_request' => [
        'title' => 'Исправление оценки на утверждение',
        'body' => 'Учитель :teacher запросил исправление оценки для :student. Требуется утверждение.',
    ],
    'absence_motivation_submitted' => [
        'title' => 'Новое заявление об оправдании',
        'body' => 'Подано заявление об оправдании пропусков для :student.',
    ],
    'document_request_submitted' => [
        'title' => 'Новое типовое заявление',
        'body' => 'В секретариат подано новое заявление: :doc_type.',
    ],
    'admission_request_submitted' => [
        'title' => 'Новая заявка на зачисление',
        'body' => 'Получена новая заявка на зачисление для :child.',
    ],

    // Emailul cu codul 2FA (trimis SINCRON la login/activare — vezi TwoFactorCodeNotification).
    'two_factor' => [
        'subject' => 'Ваш код для входа — Liceul Columna',
        'intro' => 'Используйте код ниже, чтобы завершить вход:',
        'expiry' => 'Код действителен :minutes минут. Если вы его не запрашивали, проигнорируйте это письмо и сообщите администрации.',
    ],
];
