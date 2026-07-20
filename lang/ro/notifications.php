<?php

use App\Notifications\CatalogNotification;

/**
 * Șabloane de notificări (spec §5) — predefinite pe limbă, FĂRĂ traducere în timp real. Fiecare
 * destinatar primește varianta în limba aleasă de el (`User::notificationLocale()`). Cheile sub
 * fiecare tip sunt rezolvate de {@see CatalogNotification}; placeholderele
 * (`:student`, `:subject`...) vin din `params` la declanșare. „types"/„channels" etichetează
 * matricea din Setări → Notificări.
 */
return [
    'types' => [
        'new_grade' => 'Notă nouă',
        'grade_annulled' => 'Notă anulată',
        'grade_corrected' => 'Notă corectată',
        'new_absence' => 'Absență nouă',
        'new_homework' => 'Rezumat zilnic de teme',
        'new_calendar_event' => 'Eveniment nou în calendar',
        'calendar_event_cancelled' => 'Eveniment anulat',
        'contestation_rejected' => 'Rezultatul contestației',
        'document_request_closed' => 'Răspuns la cererea depusă',
        'new_message' => 'Mesaj nou',
        'status_change' => 'Schimbare de statut',
        'corigenta_result' => 'Rezultat corigență',
        'announcement' => 'Anunț al conducerii',
        'grade_correction_request' => 'Corecție de notă de aprobat',
        'grade_correction_rejected' => 'Corecție de notă respinsă',
        'absence_motivation_submitted' => 'Cerere de motivare nouă',
        'document_request_submitted' => 'Cerere tipică nouă',
        'admission_request_submitted' => 'Cerere de înscriere nouă',
    ],

    'channels' => [
        'cabinet' => 'Cabinet (în aplicație)',
        'email' => 'E-mail',
        'telegram' => 'Telegram',
        'viber' => 'Viber',
    ],

    // Eticheta butonului de acțiune din e-mail.
    'open' => 'Deschide',

    'new_grade' => [
        'title' => 'Notă nouă',
        'body' => 'Elevul :student a primit o notă nouă la :subject.',
    ],
    'grade_annulled' => [
        'title' => 'Notă anulată',
        'body' => 'O notă a elevului :student la :subject a fost anulată. Motiv: :reason',
    ],
    'grade_corrected' => [
        'title' => 'Notă corectată',
        'body' => 'O notă a elevului :student la :subject a fost corectată.',
    ],
    'new_absence' => [
        'title' => 'Absență nouă',
        'body' => 'A fost înregistrată o absență nouă pentru :student.',
    ],
    'new_homework' => [
        // Digest zilnic (nu per-temă): un singur rezumat/zi/clasă. „Total: :count" evită acordul
        // de plural în toate limbile.
        'title' => 'Teme noi azi',
        'body' => 'Teme noi azi pentru clasa :class. Total: :count.',
    ],
    'new_message' => [
        'title' => 'Mesaj nou',
        'body' => 'Ai primit un mesaj de la :sender.',
    ],
    'corigenta_result' => [
        'title' => 'Rezultatul examenului de corigență',
        'body' => 'Examenul de corigență la :subject s-a încheiat cu nota :mark.',
    ],
    'status_change' => [
        'title' => 'Schimbare de statut',
        'body' => 'Statutul elevului :student a fost actualizat: :status.',
    ],
    'new_calendar_event' => [
        'title' => 'Eveniment nou în calendar',
        'body' => 'Eveniment nou: :title — :date.',
    ],
    'calendar_event_cancelled' => [
        'title' => 'Eveniment anulat',
        'body' => 'Evenimentul „:title" din :date a fost anulat.',
    ],
    'contestation_rejected' => [
        'title' => 'Contestație reexaminată',
        'body' => 'Contestația depusă pentru :student a fost reexaminată: nota rămâne neschimbată.',
    ],
    'announcement' => [
        'title' => 'Anunț',
        'body' => 'Conducerea liceului a publicat un anunț nou.',
    ],
    'grade_correction_request' => [
        'title' => 'Corecție de notă de aprobat',
        'body' => 'Profesorul :teacher a solicitat o corecție de notă pentru :student. Necesită aprobare.',
    ],
    'grade_correction_rejected' => [
        'title' => 'Corecție de notă respinsă',
        'body' => 'Corecția de notă solicitată pentru :student a fost respinsă. Vezi motivul în arhiva corecțiilor.',
    ],
    'absence_motivation_submitted' => [
        'title' => 'Cerere de motivare nouă',
        'body' => 'S-a depus o cerere de motivare a absențelor pentru :student.',
    ],
    'document_request_submitted' => [
        'title' => 'Cerere tipică nouă',
        'body' => 'A fost depusă o cerere nouă la secretariat: :doc_type — :student.',
    ],
    'document_request_closed' => [
        'title' => 'Răspuns la cererea depusă',
        'body' => 'Cererea „:doc_type" pentru :student a fost :status. Detaliile sunt în cabinet, la secțiunea Cereri.',
    ],
    'admission_request_submitted' => [
        'title' => 'Cerere de înscriere nouă',
        'body' => 'O nouă cerere de înscriere a fost primită pentru :child.',
    ],

    // Emailul cu codul 2FA (trimis SINCRON la login/activare — vezi TwoFactorCodeNotification).
    'two_factor' => [
        'subject' => 'Codul tău de autentificare — Liceul Columna',
        'intro' => 'Folosește codul de mai jos ca să îți finalizezi autentificarea:',
        'expiry' => 'Codul e valabil :minutes minute. Dacă nu tu ai cerut acest cod, ignoră mesajul și anunță administrația.',
    ],
];
