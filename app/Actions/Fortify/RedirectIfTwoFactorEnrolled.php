<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;

/**
 * Pasul de pipeline care trimite la challenge-ul 2FA. Extinde varianta Fortify (care se uită
 * DOAR la TOTP: secret + confirmat) ca să provoace și utilizatorii cu 2FA pe EMAIL — altfel
 * aceștia ar intra direct, fără al doilea pas. Folosit prin Fortify::authenticateThrough().
 */
class RedirectIfTwoFactorEnrolled extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * Semnătura oglindește părintele (fără tipuri pe parametri — PHP interzice îngustarea lor
     * la suprascriere).
     *
     * @param  Request  $request
     * @param  callable  $next
     */
    public function handle($request, $next): mixed
    {
        $user = $this->validateCredentials($request);

        if ($user instanceof User && $user->hasTwoFactorConfigured()) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }
}
