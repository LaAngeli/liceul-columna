<?php

namespace App\Support;

use App\Http\Controllers\Dev\DemoLoginController;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Models\TwoFactorEmailCode;
use App\Models\User;

/**
 * Trecerea de securitate pentru conturile DEMO — sursă UNICĂ (2026-07-31).
 *
 * Conturile de probă trebuie să intre fără să exerseze securitatea reală: parola nu se schimbă,
 * nota de informare e confirmată, iar 2FA nu le stă în cale. Apelată de seedere ȘI de login-ul de
 * dev ({@see DemoLoginController}), chiar înainte de autentificare — deci contul se auto-vindecă
 * indiferent de starea în care a fost lăsat. Idempotentă; strict pentru conturi [DEMO].
 *
 * ⚠️ CE A GREȘIT PRIMA VERSIUNE (reparat 2026-07-30, după ce beneficiarul a raportat a treia oară
 * „îmi cere iar cod"): seta `two_factor_email_enabled_at` ca să satisfacă gate-ul de
 * OBLIGATIVITATE ({@see EnsureTwoFactorEnrolled}) — dar înrolarea e exact ce
 * declanșează PROVOCAREA la login: `usesEmailTwoFactor()` → `twoFactorChallengeMethod() = 'email'`
 * → `RedirectIfTwoFactorEnrolled` cere codul. Cum funcția rulează la FIECARE login demo, curățarea
 * făcută de `app:demo-accounts` era anulată câteva secunde mai târziu — de aici impresia că
 * problema „revine singură". Cele două mecanisme sunt independente:
 *
 *   obligativitate („trebuie să AI 2FA")  ← comandată de `config/security.php`
 *   provocare      („dovedește că ești")  ← comandată de STAREA contului
 *
 * Prima nu se poate satisface prin înrolare fără a o declanșa pe a doua. Deci 2FA-ul contului demo
 * URMEAZĂ configul: gate stins → curățăm complet (login doar cu utilizator + parolă, exact ce se
 * cere la testare); gate aprins → înrolăm, fiindcă altfel contul rămâne blocat pe pagina de
 * configurare, iar provocarea de atunci e intenționată, nu accident.
 */
class DemoSecurity
{
    public static function pass(User $user): void
    {
        $user->forceFill([
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'privacy_acknowledged_version' => (string) config('privacy.notice_version'),
            'privacy_acknowledged_at' => $user->privacy_acknowledged_at ?? now(),
        ])->save();

        $user->requiresTwoFactorEnrollment()
            ? self::enrollTwoFactor($user)
            : self::clearTwoFactor($user);
    }

    /**
     * Gate STINS: contul n-are de ce să fie înrolat, deci nu e provocat la login. Se șterg ambele
     * metode ȘI codul efemer rămas în așteptare — altfel pagina de challenge ar avea încă un cod
     * valid de verificat.
     */
    private static function clearTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_email_enabled_at' => null,
        ])->save();

        TwoFactorEmailCode::query()->where('user_id', $user->getKey())->delete();
    }

    /**
     * Gate APRINS: fără o metodă configurată, contul demo ar rămâne blocat pe `two-factor.setup`.
     * Se alege emailul, nu TOTP — acesta din urmă ar cere un secret pe care testerul nu-l poate
     * scana. ⚠️ Presupune `MAIL_MAILER` funcțional; altfel codul nu ajunge nicăieri.
     */
    private static function enrollTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_email_enabled_at' => $user->two_factor_email_enabled_at ?? now(),
        ])->save();
    }
}
