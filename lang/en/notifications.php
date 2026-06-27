<?php

/**
 * Notification templates (spec §5) — predefined per language, NO real-time translation. Each
 * recipient gets the variant in the language they chose (`User::notificationLocale()`).
 */
return [
    'types' => [
        'new_grade' => 'New grade',
        'new_absence' => 'New absence',
        'new_homework' => 'New homework',
        'new_message' => 'New message',
        'status_change' => 'Status change',
        'announcement' => 'Management announcement',
        'grade_correction_request' => 'Grade correction to approve',
        'absence_motivation_submitted' => 'New absence-excuse request',
        'document_request_submitted' => 'New document request',
        'admission_request_submitted' => 'New admission request',
    ],

    'channels' => [
        'cabinet' => 'Cabinet (in-app)',
        'email' => 'E-mail',
        'telegram' => 'Telegram',
        'viber' => 'Viber',
        'messenger' => 'Messenger',
        'whatsapp' => 'WhatsApp',
    ],

    // Action button label in the e-mail.
    'open' => 'Open',

    'new_grade' => [
        'title' => 'New grade',
        'body' => 'Student :student received a new grade in :subject.',
    ],
    'new_absence' => [
        'title' => 'New absence',
        'body' => 'A new absence was recorded for :student.',
    ],
    'new_homework' => [
        'title' => 'New homework today',
        'body' => 'New homework today for class :class. Total: :count.',
    ],
    'new_message' => [
        'title' => 'New message',
        'body' => 'You received a message from :sender.',
    ],
    'status_change' => [
        'title' => 'Status change',
        'body' => 'The status of :student was updated: :status.',
    ],
    'announcement' => [
        'title' => 'Announcement',
        'body' => 'The school management published a new announcement.',
    ],
    'grade_correction_request' => [
        'title' => 'Grade correction to approve',
        'body' => 'Teacher :teacher requested a grade correction for :student. Approval required.',
    ],
    'absence_motivation_submitted' => [
        'title' => 'New absence-excuse request',
        'body' => 'An absence-excuse request was submitted for :student.',
    ],
    'document_request_submitted' => [
        'title' => 'New document request',
        'body' => 'A new request was submitted to the office: :doc_type.',
    ],
    'admission_request_submitted' => [
        'title' => 'New admission request',
        'body' => 'A new admission request was received for :child.',
    ],
];
