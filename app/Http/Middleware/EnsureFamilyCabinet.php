<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabinetul (Inertia) e exclusiv al familiei (elev/părinte). Tot personalul — inclusiv administratorul
 * tehnic — folosește panoul Filament, deci îl redirecționăm la pagina lui de start. Aplicat pe grupul de
 * rute de cabinet ca gating UNIFORM (înainte, doar unele pagini blocau staff-ul: dashboard/profil da,
 * mesaje/notificări nu — audit M-8 / #23 / #39 / #24). Rutele accesibile ȘI personalului (vizualizarea
 * profilului unui elev + descărcările de PII, care au propriul gating familie-sau-administrație) se
 * exceptează explicit cu withoutMiddleware.
 */
class EnsureFamilyCabinet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user instanceof User && $user->hasAnyRole(UserRole::panelRoleValues())) {
            return redirect()->to($user->homePath());
        }

        return $next($request);
    }
}
