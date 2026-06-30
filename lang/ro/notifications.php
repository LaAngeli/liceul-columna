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
        'new_absence' => 'Absență nouă',
        'new_homework' => 'Rezumat zilnic de teme',
        'new_message' => 'Mesaj nou',
        'status_change' => 'Schimbare de statut',
        'announcement' => 'Anunț al conducerii',
        'grade_correction_request' => 'Corecție de notă de aprobat',
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
    'status_change' => [
        'title' => 'Schimbare de statut',
        'body' => 'Statutul elevului :student a fost actualizat: :status.',
    ],
    'announcement' => [
        'title' => 'Anunț',
        'body' => 'Conducerea liceului a publicat un anunț nou.',
    ],
    'grade_correction_request' => [
        'title' => 'Corecție de notă de aprobat',
        'body' => 'Profesorul :teacher a solicitat o corecție de notă pentru :student. Necesită aprobare.',
    ],
    'absence_motivation_submitted' => [
        'title' => 'Cerere de motivare nouă',
        'body' => 'S-a depus o cerere de motivare a absențelor pentru :student.',
    ],
    'document_request_submitted' => [
        'title' => 'Cerere tipică nouă',
        'body' => 'A fost depusă o cerere nouă la secretariat: :doc_type.',
    ],
    'admission_request_submitted' => [
        'title' => 'Cerere de înscriere nouă',
        'body' => 'O nouă cerere de înscriere a fost primită pentru :child.',
    ],
];
