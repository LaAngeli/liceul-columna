<?php

/**
 * Notification templates (spec §5) — predefined per language, NO real-time translation. Each
 * recipient gets the variant in the language they chose (`User::notificationLocale()`).
 */
return [
    'types' => [
        'new_grade' => 'New grade',
        'grade_annulled' => 'Grade annulled',
        'grade_corrected' => 'Grade corrected',
        'new_absence' => 'New absence',
        'new_homework' => 'Daily homework summary',
        'new_calendar_event' => 'New calendar event',
        'calendar_event_cancelled' => 'Event cancelled',
        'contestation_rejected' => 'Contestation outcome',
        'new_message' => 'New message',
        'status_change' => 'Status change',
        'announcement' => 'Management announcement',
        'grade_correction_request' => 'Grade correction to approve',
        'grade_correction_rejected' => 'Grade correction rejected',
        'absence_motivation_submitted' => 'New absence-excuse request',
        'document_request_submitted' => 'New document request',
        'admission_request_submitted' => 'New admission request',
    ],

    'channels' => [
        'cabinet' => 'Cabinet (in-app)',
        'email' => 'E-mail',
        'telegram' => 'Telegram',
        'viber' => 'Viber',
    ],

    // Action button label in the e-mail.
    'open' => 'Open',

    'new_grade' => [
        'title' => 'New grade',
        'body' => 'Student :student received a new grade in :subject.',
    ],
    'grade_annulled' => [
        'title' => 'Grade annulled',
        'body' => 'A grade of :student in :subject was annulled. Reason: :reason',
    ],
    'grade_corrected' => [
        'title' => 'Grade corrected',
        'body' => 'A grade of :student in :subject was corrected.',
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
    'new_calendar_event' => [
        'title' => 'New calendar event',
        'body' => 'New event: :title — :date.',
    ],
    'calendar_event_cancelled' => [
        'title' => 'Event cancelled',
        'body' => 'The event ":title" on :date was cancelled.',
    ],
    'contestation_rejected' => [
        'title' => 'Contestation reviewed',
        'body' => 'The contestation filed for :student was re-examined: the grade remains unchanged.',
    ],
    'announcement' => [
        'title' => 'Announcement',
        'body' => 'The school management published a new announcement.',
    ],
    'grade_correction_request' => [
        'title' => 'Grade correction to approve',
        'body' => 'Teacher :teacher requested a grade correction for :student. Approval required.',
    ],
    'grade_correction_rejected' => [
        'title' => 'Grade correction rejected',
        'body' => 'The grade correction requested for :student was rejected. See the reason in the corrections archive.',
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

    // Emailul cu codul 2FA (trimis SINCRON la login/activare — vezi TwoFactorCodeNotification).
    'two_factor' => [
        'subject' => 'Your sign-in code — Liceul Columna',
        'intro' => 'Use the code below to finish signing in:',
        'expiry' => 'The code is valid for :minutes minutes. If you did not request it, ignore this message and notify the administration.',
    ],
];
