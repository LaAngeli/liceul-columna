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
    // Mesaj de VALIDARE: schimbarea emailului de login deja setat trece prin secretariat (#37).
    'email_change_via_staff' => 'Adresa de e-mail e deja setată. Pentru a o schimba, contactează secretariatul — e identificatorul de conectare al contului.',
    // Mesaj de VALIDARE: anti-duplicat la motivarea absențelor (perioadă suprapusă).
    'motivation_duplicate_pending' => 'Există deja o cerere de motivare în așteptare care acoperă această perioadă.',
    // Mesaj de VALIDARE: motivare depusă pe o perioadă fără nicio absență nemotivată.
    'motivation_no_absences' => 'Nu există absențe nemotivate în perioada aleasă — verifică datele.',
    // Mesaj de VALIDARE: încărcarea justificativului a eșuat (nu se pierde tăcut).
    'motivation_upload_failed' => 'Încărcarea documentului a eșuat. Reîncearcă.',
];
