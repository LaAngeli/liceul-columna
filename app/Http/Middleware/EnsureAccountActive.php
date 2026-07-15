<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\ExemptsPublicRoutes;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contul SUSPENDAT (users.suspended_at) nu doar că nu se mai poate autentifica
 * (FortifyServiceProvider) — sesiunile lui EXISTENTE sunt închise la următoarea cerere.
 * Acoperă cabinetul Inertia și panoul Filament; site-ul public rămâne navigabil
 * ({@see ExemptsPublicRoutes}). Rulează ÎNAINTEA lanțului de onboarding (parolă/2FA).
 */
class EnsureAccountActive
{
    use ExemptsPublicRoutes;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user instanceof User && $user->isSuspended() && ! $this->isPublicRoute($request)) {
            auth('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => __('auth.suspended')]);
        }

        return $next($request);
    }
}
