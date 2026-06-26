<?php

namespace App\Support;

/**
 * Limbile site-ului. RO e implicită (la root, fără prefix, ca să păstrăm URL-urile
 * migrate); RU și EN primesc prefix de URL (/ru, /en) pe partea publică.
 */
final class Locale
{
    /**
     * Limbile suportate: cod → denumire nativă.
     *
     * @return array<string, string>
     */
    public static function supported(): array
    {
        return [
            'ro' => 'Română',
            'ru' => 'Русский',
            'en' => 'English',
        ];
    }

    public static function default(): string
    {
        return 'ro';
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::supported());
    }

    /**
     * Limbile care primesc prefix de URL (toate în afară de cea implicită).
     *
     * @return list<string>
     */
    public static function prefixed(): array
    {
        return array_values(array_filter(
            array_keys(self::supported()),
            static fn (string $locale): bool => $locale !== self::default(),
        ));
    }
}
