<?php

/**
 * Шаблоны уведомлений (специф. §5) — заранее подготовленные по языкам, БЕЗ перевода в реальном
 * времени. Каждый получатель получает вариант на выбранном им языке (`User::notificationLocale()`).
 */
return [
    'types' => [
        'new_grade' => 'Новая оценка',
        'grade_annulled' => 'Оценка аннулирована',
        'grade_corrected' => 'Оценка исправлена',
        'new_absence' => 'Новый пропуск',
        'new_homework' => 'Ежедневная сводка заданий',
        'new_calendar_event' => 'Новое событие в календаре',
        'calendar_event_cancelled' => 'Событие отменено',
        'contestation_rejected' => 'Результат апелляции',
        'document_request_closed' => 'Ответ на поданное заявление',
        'new_message' => 'Новое сообщение',
        'status_change' => 'Изменение статуса',
        'corigenta_result' => 'Результат переэкзаменовки',
        'announcement' => 'Объявление руководства',
        'grade_correction_request' => 'Исправление оценки на утверждение',
        'grade_correction_rejected' => 'Исправление оценки отклонено',
        'homework_correction_rejected' => 'Исправление задания отклонено',
        'absence_motivation_submitted' => 'Новое заявление об оправдании',
        'absence_motivation_decided' => 'Решение по оправданию пропусков',
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
    'grade_annulled' => [
        'title' => 'Оценка аннулирована',
        'body' => 'Оценка ученика :student по предмету :subject аннулирована. Причина: :reason',
    ],
    'grade_corrected' => [
        'title' => 'Оценка исправлена',
        'body' => 'Оценка ученика :student по предмету :subject была исправлена.',
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
    'corigenta_result' => [
        'title' => 'Результат экзамена по переэкзаменовке',
        'body' => 'Экзамен по переэкзаменовке (:subject) завершён с оценкой :mark.',
    ],
    'status_change' => [
        'title' => 'Изменение статуса',
        'body' => 'Статус ученика :student обновлён: :status.',
    ],
    'new_calendar_event' => [
        'title' => 'Новое событие в календаре',
        'body' => 'Новое событие: :title — :date.',
    ],
    'calendar_event_cancelled' => [
        'title' => 'Событие отменено',
        'body' => 'Событие «:title» от :date отменено.',
    ],
    'contestation_rejected' => [
        'title' => 'Апелляция рассмотрена',
        'body' => 'Апелляция, поданная по :student, рассмотрена повторно: оценка остаётся без изменений.',
    ],
    'announcement' => [
        'title' => 'Объявление',
        'body' => 'Руководство лицея опубликовало новое объявление.',
    ],
    'grade_correction_request' => [
        'title' => 'Исправление оценки на утверждение',
        'body' => 'Учитель :teacher запросил исправление оценки для :student. Требуется утверждение.',
    ],
    'grade_correction_rejected' => [
        'title' => 'Исправление оценки отклонено',
        'body' => 'Запрошенное исправление оценки для :student отклонено. Причину см. в архиве исправлений.',
    ],
    'homework_correction_rejected' => [
        'title' => 'Исправление задания отклонено',
        'body' => 'Запрошенное исправление домашнего задания по предмету :subject отклонено. Причина указана на странице запроса.',
    ],
    'absence_motivation_submitted' => [
        'title' => 'Новое заявление об оправдании',
        'body' => 'Подано заявление об оправдании пропусков для :student.',
    ],
    'absence_motivation_decided' => [
        'title' => 'Решение по оправданию пропусков',
        'body' => 'Заявление об оправдании пропусков для :student (:period) рассмотрено: :status.',
    ],
    'document_request_submitted' => [
        'title' => 'Новое типовое заявление',
        'body' => 'В секретариат подано новое заявление: :doc_type — :student.',
    ],
    'document_request_closed' => [
        'title' => 'Ответ на поданное заявление',
        'body' => 'Заявление «:doc_type» по :student: :status. Подробности — в кабинете, раздел «Заявления».',
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
