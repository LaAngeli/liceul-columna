<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Formularul de contact
    |--------------------------------------------------------------------------
    | Cutia poștală unde ajung mesajele din formularul public de contact și
    | datele instituției folosite în semnătura e-mailurilor (notificare + confirmare).
    */

    'mailbox' => env('CONTACT_MAIL_TO', 'info@columna.org.md'),

    'name' => 'IPL „Liceul Columna"',
    'address' => 'str. Alba Iulia 5/2, Chișinău, Republica Moldova',
    'phone' => '(+373) 22 74 28 52',
    'website' => env('CONTACT_WEBSITE', 'columna.md'),

];
