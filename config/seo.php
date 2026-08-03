<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Regim SEO minimal (comutator temporar, complet reversibil)
    |--------------------------------------------------------------------------
    |
    | Când e `true`, site-ul rulează în regim SEO MINIM:
    |   - `<title>` = EXCLUSIV denumirea paginii curente (fără brand/slogan);
    |   - meta description + orice alte semnale SEO (OG, Twitter Cards, JSON-LD,
    |     canonical, hreflang) sunt SUPRIMATE din frontend.
    |
    | Mecanismul e de tip „comment/disable", NU de ștergere: configurația SEO
    | reală rămâne intactă în cod (`<meta name="description">` în pagini) și în
    | traduceri (`lang/{ro,ru,en}/site.php` → `*.meta_description`). Nu se pierde
    | și nu se modifică nimic — doar nu se mai emite spre browser.
    |
    | REVENIRE INTEGRALĂ, o singură comandă:
    |     SEO_MINIMAL=false  în `.env`   →   php artisan config:clear
    | (în producție, unde configul e cache-uit: `php artisan config:cache`)
    |
    | Flag-ul e citit SERVER-SIDE și injectat în pagină de `app.blade.php`, deci
    | comutarea NU cere rebuild de frontend (nu e `import.meta.env`, care s-ar
    | „coace" în bundle la build — vezi incidentul APP_NAME=Laravel din 2026-07).
    |
    | Documentație: `docs/SEO-REGIM-MINIMAL.md`
    |
    */

    'minimal' => (bool) env('SEO_MINIMAL', true),

];
