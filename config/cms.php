<?php

// Configurarea panoului „Studio de conținut" (/studio) — sursă unică pentru contul de
// administrare și (ulterior) pentru regulile de uniformitate media/text.

return [

    /*
    |--------------------------------------------------------------------------
    | Contul unic de administrare a conținutului
    |--------------------------------------------------------------------------
    |
    | Panoul /studio are UN singur cont, izolat de utilizatorii academici. Credențialele
    | stau în .env (niciodată în git) și se materializează cu `php artisan app:cms-admin`.
    |
    */

    'admin' => [
        'email' => env('CMS_ADMIN_EMAIL'),
        'name' => env('CMS_ADMIN_NAME', 'Administrator conținut'),
        'password' => env('CMS_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Securitate
    |--------------------------------------------------------------------------
    |
    | MFA (TOTP) e OBLIGATORIU implicit — la prima logare contul e forțat să-l configureze.
    | Dezactivat automat în teste (vezi phpunit.xml) ca să nu blocheze fluxul de request.
    |
    */

    'require_mfa' => env('CMS_REQUIRE_MFA', true),

    /*
    |--------------------------------------------------------------------------
    | Reguli de uniformitate — „șablonul" de conținut
    |--------------------------------------------------------------------------
    |
    | Sursă UNICĂ pentru limitele de text și dimensiunile de imagine. Toate resursele Studio
    | referă aceste valori → carduri/articole cu gabarit identic, indiferent de sursă.
    |
    */

    'articles' => [
        'title' => ['min' => 10, 'max' => 120],
        'excerpt' => ['max' => 200],
        'image' => ['width' => 1600, 'height' => 900, 'aspect' => '16:9'],
    ],

    'media' => [
        'disk' => 'public',
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'image_max_kb' => 6144,
        'webp_quality' => 82,
    ],

    'gallery' => [
        'title' => ['min' => 3, 'max' => 120],
        // Imaginile de galerie sunt decupate la aspect UNIFORM la upload, ca miniaturile din grid să
        // apară identic pe site (fără trunchiere/gap-uri). Editorul poate ajusta zona vizibilă în
        // editorul de imagine ÎNAINTE de salvare. Aceeași imagine se folosește și în lightbox.
        'image' => ['width' => 1500, 'height' => 1000, 'aspect' => '3:2'],
    ],

];
