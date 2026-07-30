<?php

namespace App\Support;

use App\Http\Controllers\Dev\DemoLoginController;
use App\Models\User;

/**
 * Trecerea de securitate pentru conturile DEMO — sursă UNICĂ (2026-07-31).
 *
 * Conturile de probă trebuie să intre fără să exerseze securitatea reală: parola nu se schimbă,
 * 2FA e formal configurat (email) ca să satisfacă gate-ul de obligativitate, iar nota de informare
 * e confirmată. Până acum logica trăia DOAR în `DemoRoleAccountsSeeder::passSecurity`, deci un cont
 * demo creat pe altă cale (ex. `DemoParentAccountsSeeder`) sau un cont căruia i s-a resetat starea
 * ajungea pe provocarea de cod — plus, dacă `SECURITY_2FA_STAFF` era cache-uit pe `true`, gate-ul
 * se declanșa oricum. Recurent, raportat de beneficiar de mai multe ori.
 *
 * Acum e apelată ȘI de login-ul de dev ({@see DemoLoginController}), chiar
 * ÎNAINTE de autentificare: login-ul demo se AUTO-VINDECĂ — indiferent de starea contului sau de
 * configul cache-uit, contul trece de toate gate-urile. Idempotentă; strict pentru conturi [DEMO].
 */
class DemoSecurity
{
    public static function pass(User $user): void
    {
        $user->forceFill([
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
            // hasTwoFactorConfigured() cere ȘI email — conturile demo îl au; fără el, gate-ul de
            // 2FA nu poate fi satisfăcut prin metoda email (nu inventăm o adresă aici).
            'two_factor_email_enabled_at' => $user->two_factor_email_enabled_at ?? now(),
            'privacy_acknowledged_version' => (string) config('privacy.notice_version'),
            'privacy_acknowledged_at' => $user->privacy_acknowledged_at ?? now(),
        ])->save();
    }
}
