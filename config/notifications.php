<?php

/**
 * Politica de RETENȚIE a notificărilor in-app (cerința beneficiarului, 2026-07-21).
 *
 * Notificările nu se șterg manual de niciun rol: cele CITITE părăsesc lista principală doar prin
 * ARHIVARE automată (măturarea zilnică `app:archive-notifications`), iar arhiva rămâne accesibilă
 * oricând din inbox (cabinet + panou). Necititele nu se arhivează niciodată, indiferent de vechime.
 *
 * Valorile se schimbă din `.env`, fără intervenții în cod.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Arhivare automată
    |--------------------------------------------------------------------------
    | După câte zile o notificare CITITĂ e mutată automat în arhivă.
    */
    'archive_after_days' => (int) env('NOTIFICATIONS_ARCHIVE_AFTER_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Ștergere definitivă (politică viitoare — implicit OPRITĂ)
    |--------------------------------------------------------------------------
    | După câți ANI de la arhivare o notificare poate fi ștearsă definitiv.
    | null = niciodată (implicit). Se activează DOAR printr-o decizie explicită
    | de politică (setarea variabilei de mediu) — structura există deja, codul
    | nu trebuie atins.
    */
    'purge_archived_after_years' => env('NOTIFICATIONS_PURGE_ARCHIVED_AFTER_YEARS'),

];
