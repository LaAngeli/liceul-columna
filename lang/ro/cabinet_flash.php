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
    // Mesaje de VALIDARE (nu toast) la depunerea contestației: nota se ALEGE (nu se descrie), iar
    // motivul explică DE CE — contextul (disciplină, valoare, dată, profesor) vine din notă.
    'contestation_details_required' => 'Contestația are nevoie de motiv: explică de ce consideri nota incorectă.',
    'contestation_grade_required' => 'Alege nota pe care o contești din listă.',
    'contestation_grade_invalid' => 'Nota aleasă nu există sau nu aparține acestui elev.',
    'contestation_grade_pending' => 'Nota aleasă are deja o corecție în așteptare — conducerea o va judeca; nu e nevoie de o contestație nouă.',
    'request_duplicate_pending' => 'Există deja o cerere de acest tip în așteptare pentru acest elev — secretariatul o va procesa; nu e nevoie să o redepui.',
    // Cererile fără conținut sunt neprocesabile — detaliile sunt obligatorii la toate tipurile.
    'request_details_required' => 'Cererea are nevoie de detalii ca să poată fi procesată (motivul, destinația sau tema — vezi indicațiile din formular).',
    // Învoirea e prospectivă; pentru absențe deja petrecute există motivarea absențelor.
    'invoire_past_period' => 'Învoirea se cere pentru zile viitoare. Pentru absențe deja petrecute folosește „Motivarea absențelor" din tabul Situație.',
    'attachment_upload_failed' => 'Încărcarea justificativului a eșuat. Reîncearcă.',
    'request_withdrawn' => 'Cererea a fost retrasă. Poți depune una nouă oricând.',
    // Mesaj de VALIDARE: schimbarea emailului de login deja setat trece prin secretariat (#37).
    'email_change_via_staff' => 'Adresa de e-mail e deja setată. Pentru a o schimba, contactează secretariatul — e identificatorul de conectare al contului.',
    // Mesaj de VALIDARE: anti-duplicat la motivarea absențelor (perioadă suprapusă).
    'motivation_duplicate_pending' => 'Există deja o cerere de motivare în așteptare care acoperă această perioadă.',
    // Mesaj de VALIDARE: motivare depusă pe o perioadă fără nicio absență nemotivată.
    'motivation_no_absences' => 'Nu există absențe nemotivate în perioada aleasă — verifică datele.',
    // Mesaj de VALIDARE: încărcarea justificativului a eșuat (nu se pierde tăcut).
    'motivation_upload_failed' => 'Încărcarea documentului a eșuat. Reîncearcă.',
];
