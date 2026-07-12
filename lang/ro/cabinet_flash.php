<?php

declare(strict_types=1);

/*
 * Mesaje de confirmare (toast) afișate în cabinetul elev/părinte după acțiuni reușite.
 * Livrate prin Inertia::flash('toast', ...) și afișate de hook-ul `use-flash-toast`.
 */

return [
    'motivation_sent' => 'Cererea de motivare a fost trimisă dirigintelui.',
    'motivation_sent_exception' => 'Cererea (excepție, după termen) a fost transmisă vicedirectorului pe educație.',
    'status_acknowledged' => 'Confirmarea a fost înregistrată.',
    'request_generated' => 'Cererea a fost generată și transmisă secretariatului.',
    // Mesaj de VALIDARE (nu toast): anti-duplicat la depunerea cererilor tipice.
    'request_duplicate_pending' => 'Există deja o cerere de acest tip în așteptare pentru acest elev — secretariatul o va procesa; nu e nevoie să o redepui.',
];
