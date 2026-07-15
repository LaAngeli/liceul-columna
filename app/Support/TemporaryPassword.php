<?php

namespace App\Support;

/**
 * Parole temporare pentru conturile create/resetate din panou: 12 caractere dintr-un alfabet
 * FĂRĂ semne ambigue (0/O, 1/l/I) — se dictează și se tastează ușor, inclusiv de elevi.
 * Utilizatorul e obligat oricum să o schimbe la prima autentificare (must_change_password).
 */
final class TemporaryPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 12): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
