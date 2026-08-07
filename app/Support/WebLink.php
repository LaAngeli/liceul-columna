<?php

namespace App\Support;

/**
 * ADRESA WEB așa cum o scrie un om (cerința beneficiarului, 07.08.2026): „test.md", nu
 * „https://test.md".
 *
 * Regula `url` a Laravel cere schema, iar profesorul care adaugă un link la temă tasta de fiecare
 * dată șapte caractere care nu-i spun nimic. Aici păstrăm ce conta cu adevărat — STRUCTURA de
 * domeniu (etichete despărțite de punct, cu un TLD real) — și completăm noi schema.
 *
 * DE CE normalizăm la SALVARE, nu doar la afișare: linkul ajunge într-un `href` randat brut în
 * cabinet, iar `test.md` fără schemă s-ar rezolva RELATIV la pagina curentă („/cabinet/test.md") —
 * un link rupt, tăcut. În plus, frontendul decide cu `isUrl()` (care cere `https?://`) dacă
 * afișează un link sau un simplu text gri: fără schemă, adresa ar fi coborât la text.
 *
 * ⚠️ SECURITATE: se acceptă DOAR http/https. O valoare ca `javascript:alert(1)` ajunge tot într-un
 * `href`, deci nu i se completează niciodată schema — rămâne cum e și cade la validare. Fără
 * regula asta, „completează schema dacă lipsește" ar fi transformat o valoare periculoasă într-una
 * cu formă validă.
 */
class WebLink
{
    /**
     * Adresa gata de folosit: schema completată când lipsește.
     *
     * O schemă EXPLICITĂ nu se rescrie niciodată — nici http→https (ar sparge adrese interne care
     * chiar merg doar pe http), nici altceva→https (vezi nota de securitate).
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // „exemplu.md:8080/x" NU are schemă — are PORT. Fără verificarea asta, tot ce e înaintea
        // celor două puncte trece drept schemă și adresa rămâne neatinsă (deci respinsă la
        // validare). Rămâne în siguranță: „javascript:1" ar deveni „https://javascript:1", cu
        // gazda „javascript" — fără punct, deci tot cade la validare.
        if (preg_match('#^[^\s:/?\#]+:\d+(?:[/?\#].*)?$#', $value) !== 1
            // Orice schemă explicită („http://", „javascript:", „mailto:") se păstrează neatinsă;
            // dacă nu e http(s), validarea o respinge mai jos.
            && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value) === 1) {
            return $value;
        }

        return 'https://'.$value;
    }

    /**
     * E o adresă web pe care o putem deschide în siguranță?
     *
     * Structura cerută: cel puțin două etichete despărțite de punct, cu TLD de minimum două litere
     * — deci „test.md" și „test.test" trec, iar „exemplu" sau „exemplu." nu. Portul, calea,
     * parametrii și fragmentul rămân opționale.
     */
    public static function isValid(?string $value): bool
    {
        $url = self::normalize($value);

        if ($url === null) {
            return false;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        // Unicode: școala poate folosi domenii cu diacritice; TLD-ul rămâne alfabetic (2+).
        return preg_match('/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]*[\p{L}\p{N}])?\.)+\p{L}{2,}$/u', $host) === 1;
    }

    /**
     * Normalizează o listă de linkuri (repeaterul simplu din formularul de temă), păstrând doar
     * intrările nevide. Ordinea se păstrează — profesorul le-a pus într-o anumită succesiune.
     *
     * @return list<string>
     */
    public static function normalizeAll(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $out = [];

        foreach ($links as $link) {
            $normalized = self::normalize(is_string($link) ? $link : null);

            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }
}
