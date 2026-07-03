<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blochează elevii/părinții pe pagina notei de informare (luare la cunoștință, Legea 133/2011 §7)
 * până confirmă versiunea curentă. Personalul (panou) nu e vizat — el prelucrează datele pe temei de
 * rol. Rulează DUPĂ schimbarea forțată a parolei (rutele acesteia sunt exceptate).
 */
class EnsurePrivacyAcknowledged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user instanceof User
            && $user->hasAnyRole([UserRole::Elev->value, UserRole::Parinte->value])
            && ! $user->hasAcknowledgedCurrentPrivacyNotice()
            && ! $this->isExempt($request)) {
            return redirect()->route('privacy.consent');
        }

        return $next($request);
    }

    /**
     * Rutele permise chiar fără confirmare (altfel s-ar redirecționa la infinit + lăsăm prioritate
     * schimbării forțate a parolei).
     */
    private function isExempt(Request $request): bool
    {
        return $request->routeIs(
            'privacy.consent',
            'privacy.consent.store',
            'password.change',
            'password.change.update',
            'logout',
        );
    }
}
