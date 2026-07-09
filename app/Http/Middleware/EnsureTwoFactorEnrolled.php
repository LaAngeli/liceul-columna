<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Http\Middleware\Concerns\ExemptsPublicRoutes;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obligativitatea 2FA (rollout FAZAT, config/security.php): segmentul vizat (staff întâi,
 * cabinetul după anunțul școlii) e blocat pe pagina de configurare 2FA până alege o metodă
 * (aplicație TOTP sau cod pe email). Tiparul EnsurePasswordChanged: înregistrat și pe grupul
 * web, și în authMiddleware-ul panoului Filament. Lanț: login → challenge → parolă forțată →
 * consimțământ → ACEST gate. Site-ul public e exceptat ({@see ExemptsPublicRoutes}).
 */
class EnsureTwoFactorEnrolled
{
    use ExemptsPublicRoutes;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user instanceof User
            && $this->isRequiredFor($user)
            && ! $user->hasTwoFactorConfigured()
            && ! $this->isExempt($request)) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }

    /**
     * Segmentare rollout: personalul (roluri de panou) vs. cabinet (elev/părinte).
     */
    private function isRequiredFor(User $user): bool
    {
        return $user->hasAnyRole(UserRole::panelRoleValues())
            ? (bool) config('security.two_factor.required_staff')
            : (bool) config('security.two_factor.required_cabinet');
    }

    /**
     * Rutele permise fără 2FA configurat: pagina de configurare + endpoint-urile de activare
     * (ambele metode), confirmarea parolei (cerută de activare), fluxurile obligatorii
     * anterioare din lanț și logout-ul (altfel — redirect la infinit).
     */
    private function isExempt(Request $request): bool
    {
        return $this->isPublicRoute($request)
            || $request->routeIs(
                'two-factor.setup',
                // Activare TOTP (Fortify).
                'two-factor.enable',
                'two-factor.confirm',
                'two-factor.qr-code',
                'two-factor.secret-key',
                'two-factor.recovery-codes',
                // Activare cod pe email.
                'two-factor-email.send',
                'two-factor-email.confirm',
                // Confirmarea parolei (protejează endpoint-urile de activare).
                'password.confirm',
                'password.confirm.store',
                'password.confirmation',
                // Pașii obligatorii anteriori + ieșirea.
                'password.change',
                'password.change.update',
                'privacy.consent',
                'privacy.consent.store',
                'logout',
            );
    }
}
