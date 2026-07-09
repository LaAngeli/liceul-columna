<?php

namespace App\Http\Middleware\Concerns;

use App\Http\Middleware\SetPublicLocale;
use Illuminate\Http\Request;

/**
 * Exceptează rutele site-ului PUBLIC de la gate-urile de onboarding (parolă forțată / consimțământ /
 * 2FA). Site-ul public și cabinetul/dashboard-ul sunt entități separate: un utilizator logat trebuie
 * să poată naviga liber pe site (homepage, pagini publice, logo → home) chiar dacă nu și-a finalizat
 * onboarding-ul — obligativitatea se aplică doar când accesează zona autentificată.
 *
 * Marcajul de „rută publică" e middleware-ul {@see SetPublicLocale}, prezent pe TOATE paginile publice
 * (montate la root + fiecare prefix de limbă). Comutatorul de limbă `set-locale` e și el navigare
 * publică (redirect stateless spre pagina de proveniență), deci intră în excepție.
 */
trait ExemptsPublicRoutes
{
    protected function isPublicRoute(Request $request): bool
    {
        $route = $request->route();

        if ($route === null) {
            return false;
        }

        return $request->routeIs('set-locale')
            || in_array(SetPublicLocale::class, $route->gatherMiddleware(), true);
    }
}
