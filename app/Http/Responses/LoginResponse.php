<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirecționează după logare către pagina de start a rolului (User::homePath()).
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $target = $user?->homePath() ?? (string) config('fortify.home');

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false, 'redirect' => $target]);
        }

        // Personalul aterizează în panoul Filament (/admin), care NU e o pagină Inertia.
        // Formularul de login e o cerere Inertia, iar Inertia nu poate „urmări" un redirect
        // către o pagină non-Inertia → folosim Inertia::location pentru o navigare COMPLETĂ.
        // Altfel URL-ul rămâne blocat la /login și panoul apare doar după refresh.
        if (($user?->hasAnyRole(UserRole::panelRoleValues()) ?? false) && $request->hasHeader('X-Inertia')) {
            return Inertia::location($target);
        }

        // Mergem DIRECT la home-ul rolului, NU la „intended" — altfel, după delogare,
        // un URL intended rămas (ex. /admin) ar trimite un user cu alte drepturi acolo → 403.
        return redirect()->to($target);
    }
}
