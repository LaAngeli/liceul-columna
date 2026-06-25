<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * După provocarea 2FA, redirecționează tot către pagina de start a rolului
     * (User::homePath()) — altfel personalul cu 2FA ar ajunge în cabinet, nu în panou.
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $target = $user?->homePath() ?? (string) config('fortify.home');

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $target]);
        }

        // Vezi LoginResponse: ținta personalului (/admin) e o pagină Filament, non-Inertia.
        if (($user?->hasAnyRole(UserRole::panelRoleValues()) ?? false) && $request->hasHeader('X-Inertia')) {
            return Inertia::location($target);
        }

        // Home-ul rolului direct, nu „intended" (vezi LoginResponse).
        return redirect()->to($target);
    }
}
