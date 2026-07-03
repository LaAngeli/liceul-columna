<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autentificare în doi pași (2FA) — obligativitate la logare
    |--------------------------------------------------------------------------
    |
    | Rollout FAZAT (strategia 2026-07-03): personalul e obligat primul (puțini,
    | manipulează PII de minori); cabinetul (elev/părinte) devine obligatoriu
    | după fereastra de anunț a școlii — se comută din .env, fără modificări de
    | cod. Gate-ul e aplicat de middleware-ul EnsureTwoFactorEnrolled.
    |
    */

    'two_factor' => [
        'required_staff' => (bool) env('SECURITY_2FA_STAFF', true),
        'required_cabinet' => (bool) env('SECURITY_2FA_CABINET', false),
    ],

];
