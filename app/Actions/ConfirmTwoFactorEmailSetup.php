<?php

namespace App\Actions;

use App\Models\User;

/**
 * Finalizează ACTIVAREA 2FA pe email după verificarea codului: comite adresa în așteptare
 * (dacă a existat) ca email verificat al contului și pornește metoda. Sursă unică pentru
 * fluxul din cabinet (controller) și cel din profilul staff (Filament).
 */
class ConfirmTwoFactorEmailSetup
{
    public function __construct(
        private readonly VerifyTwoFactorEmailCode $verifier,
    ) {}

    /**
     * @return 'ok'|'invalid_code'|'email_taken'
     */
    public function execute(User $user, string $code): string
    {
        $result = $this->verifier->execute($user, $code);

        if (! $result['ok']) {
            return 'invalid_code';
        }

        if ($result['pendingEmail'] !== null) {
            // Recheck de unicitate la comitere (fereastra dintre trimitere și confirmare).
            $taken = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($result['pendingEmail'])])
                ->whereKeyNot($user->id)
                ->exists();

            if ($taken) {
                return 'email_taken';
            }

            $user->forceFill([
                'email' => $result['pendingEmail'],
                'email_verified_at' => now(),
            ])->save();
        } elseif ($user->email_verified_at === null) {
            // Codul primit pe emailul contului îl dovedește ca funcțional → considerat verificat.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->forceFill(['two_factor_email_enabled_at' => now()])->save();

        return 'ok';
    }
}
