<?php

namespace App\Actions;

use App\Models\TwoFactorEmailCode;
use App\Models\User;

/**
 * Verifică un cod OTP pe email: neexpirat, sub limita de încercări, hash egal. La succes codul
 * se ȘTERGE (single-use) și se întoarce eventualul email în curs de verificare (de comis pe cont).
 */
class VerifyTwoFactorEmailCode
{
    /** Încercări greșite permise per cod (anti brute-force, pe lângă rate-limiter). */
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array{ok: bool, pendingEmail: string|null}
     */
    public function execute(User $user, string $code): array
    {
        $row = TwoFactorEmailCode::query()->where('user_id', $user->id)->first();

        if ($row === null || $row->expires_at->isPast() || $row->attempts >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'pendingEmail' => null];
        }

        if (! hash_equals($row->code_hash, hash('sha256', $code))) {
            $row->increment('attempts');

            return ['ok' => false, 'pendingEmail' => null];
        }

        $pendingEmail = $row->pending_email;
        $row->delete();

        return ['ok' => true, 'pendingEmail' => $pendingEmail];
    }
}
