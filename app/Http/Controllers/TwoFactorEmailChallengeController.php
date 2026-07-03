<?php

namespace App\Http\Controllers;

use App\Actions\SendTwoFactorEmailCode;
use App\Actions\VerifyTwoFactorEmailCode;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Challenge-ul 2FA pe EMAIL la login — grefat pe ACELAȘI handshake de sesiune ca challenge-ul
 * TOTP al Fortify (`login.id`/`login.remember`, setate de RedirectIfTwoFactorEnrolled după
 * validarea credențialelor): utilizatorul NU e autentificat până nu trece codul. Rutele stau
 * sub rate-limiter-ul `two-factor` (per login.id), iar codul are propriul contor de încercări.
 */
class TwoFactorEmailChallengeController extends Controller
{
    /**
     * Trimite (sau retrimite, după cooldown) codul către emailul contului provocat.
     */
    public function send(Request $request, SendTwoFactorEmailCode $sender): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if (! $sender->execute($user)) {
            return back()->withErrors(['code' => __('site.auth.twofa_email_cooldown')]);
        }

        return back()->with('status', 'two-factor-email-code-sent');
    }

    /**
     * Verifică codul și finalizează autentificarea — exact ca Fortify la challenge-ul TOTP:
     * login pe guard + regenerarea sesiunii + răspunsul contract (redirect pe rol).
     */
    public function verify(Request $request, VerifyTwoFactorEmailCode $verifier): Response
    {
        $user = $this->challengedUser($request);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! $verifier->execute($user, (string) $validated['code'])['ok']) {
            return back()->withErrors(['code' => __('site.auth.twofa_email_invalid_code')]);
        }

        Auth::guard('web')->login($user, (bool) $request->session()->pull('login.remember', false));

        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class)->toResponse($request);
    }

    /**
     * Utilizatorul aflat la jumătatea autentificării (credențiale valide, 2FA nevalidat încă).
     * Fără handshake valid → înapoi la login (ca TwoFactorLoginRequest al Fortify).
     */
    private function challengedUser(Request $request): User
    {
        $userId = $request->session()->get('login.id');
        $user = $userId !== null ? User::query()->find((int) $userId) : null;

        if ($user === null || ! $user->usesEmailTwoFactor()) {
            throw new HttpResponseException(redirect()->route('login'));
        }

        return $user;
    }
}
