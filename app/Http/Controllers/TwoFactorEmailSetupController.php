<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmTwoFactorEmailSetup;
use App\Actions\SendTwoFactorEmailCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Activarea/dezactivarea 2FA pe email (autentificat; rutele sunt sub `password.confirm`, la fel
 * ca endpoint-urile 2FA ale Fortify). Fluxul acoperă și conturile FĂRĂ email (majoritatea celor
 * migrate): adresa nouă se trimite ca `pending_email`, iar verificarea codului o comite pe cont
 * (email + email_verified_at) — codul dovedește că adresa e a utilizatorului.
 */
class TwoFactorEmailSetupController extends Controller
{
    /**
     * Trimite codul de verificare pentru activare (către emailul contului sau către o adresă nouă).
     */
    public function send(Request $request, SendTwoFactorEmailCode $sender): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'email' => [
                $user->email === null ? 'required' : 'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $pendingEmail = $validated['email'] ?? null;

        // Aceeași adresă ca a contului nu e „în așteptare" — codul merge pe emailul existent.
        if ($pendingEmail !== null && $user->email !== null && strcasecmp($pendingEmail, $user->email) === 0) {
            $pendingEmail = null;
        }

        if (! $sender->execute($user, $pendingEmail)) {
            return back()->withErrors(['email' => __('site.settings.twofa_email_cooldown')]);
        }

        return back()->with('status', 'two-factor-email-code-sent');
    }

    /**
     * Confirmă codul primit: activează 2FA pe email (și comite adresa nouă, dacă a existat).
     */
    public function confirm(Request $request, ConfirmTwoFactorEmailSetup $confirmer): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        return match ($confirmer->execute($user, (string) $validated['code'])) {
            'invalid_code' => back()->withErrors(['code' => __('site.settings.twofa_email_invalid_code')]),
            'email_taken' => back()->withErrors(['email' => __('validation.unique', ['attribute' => 'email'])]),
            default => back()->with('status', 'two-factor-email-enabled'),
        };
    }

    /**
     * Dezactivează 2FA pe email (emailul contului rămâne neatins).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $user->forceFill(['two_factor_email_enabled_at' => null])->save();
        $user->twoFactorEmailCode()->delete();

        return back()->with('status', 'two-factor-email-disabled');
    }

    private function user(Request $request): User
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
