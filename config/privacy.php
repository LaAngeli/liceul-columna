<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Versiunea notei de informare (Legea 133/2011, spec §7)
    |--------------------------------------------------------------------------
    | La schimbarea conținutului notei de informare, bumpează versiunea aici →
    | elevii/părinții vor fi rugați să o reconfirme (luare la cunoștință) la
    | următoarea logare. Confirmările vechi rămân în istoric (dovadă).
    */
    'notice_version' => env('PRIVACY_NOTICE_VERSION', '2026-06-28'),
];
