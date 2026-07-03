<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Userii migrați (must_change_password=true) sunt blocați pe pagina de schimbare a parolei
 * până când și-o schimbă. Acoperă atât cabinetul Inertia, cât și panoul Filament.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user !== null && $user->must_change_password && ! $this->isExempt($request)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }

    /**
     * Rutele permise chiar și cu parola neschimbată (altfel s-ar redirecționa la infinit).
     */
    private function isExempt(Request $request): bool
    {
        return $request->routeIs('password.change', 'password.change.update', 'logout');
    }
}
