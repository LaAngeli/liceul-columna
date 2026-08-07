<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servirea SIGURĂ a fișierelor încărcate de utilizatori — sursă unică pentru toate punțile de
 * fișiere ale platformei (atașamentele temelor, atașamentele poștei interne, viitoarea predare a
 * temelor de către elev).
 *
 * Modelul de amenințare: un fișier încărcat e conținut NEDEMN DE ÎNCREDERE, indiferent cine l-a
 * urcat — un cont de profesor poate fi compromis, iar dinspre familie puntea există deja (poșta).
 * Un fișier servit INLINE pe originea aplicației devine cod pe acea origine dacă browserul îl
 * interpretează ca HTML/SVG — adică XSS stocat cu sesiunea victimei (elev SAU staff, în ambele
 * direcții). Apărarea, în straturi:
 *
 *   1. INLINE doar pentru o listă ÎNCHISĂ de tipuri pasive (imagini raster + PDF) — tipuri pe
 *      care browserul le RANDEAZĂ, nu le execută. Niciodată HTML, SVG, XML sau text — oricât de
 *      legitim ar părea fișierul.
 *   2. Tipul se RE-VERIFICĂ din CONȚINUT la fiecare servire (finfo, prin `Storage::mimeType()`),
 *      nu din extensie, numele original sau MIME-ul declarat la upload. Un HTML botezat „.png"
 *      pică verificarea și cade pe attachment — descărcare, nu randare.
 *   3. Tot ce nu e în lista inline pleacă `attachment`: browserul îl salvează, nu îl deschide
 *      pe originea noastră.
 *   4. `X-Content-Type-Options: nosniff` pe ORICE răspuns — browserul nu are voie să „ghicească"
 *      alt tip decât cel declarat.
 *
 * Verificarea de ACCES nu trăiește aici, deliberat: fiecare rută își păstrează regula ei
 * (participanții firului, familia clasei) — aici e doar ultima verigă, transportul.
 */
final class SafeFileResponse
{
    /**
     * Tipurile care au voie inline — pasive prin natura lor. Listă ÎNCHISĂ: extinderea ei e o
     * decizie de securitate, nu o conveniență.
     *
     * @var list<string>
     */
    private const INLINE_MIMES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
        'application/pdf',
    ];

    /**
     * Felul de previzualizare pe care fișierul îl SUSȚINE — după conținutul lui real, aceeași
     * regulă ca servirea. `null` = doar descărcare. Interfața decide pe baza asta dacă oferă
     * preview (imagine în pagină / PDF în cadru), fără să poată promite ce servirea ar refuza.
     */
    public static function previewKind(string $disk, string $path): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $mime = self::detectedMime($disk, $path);

        return match (true) {
            $mime === 'application/pdf' => 'pdf',
            $mime !== null && str_starts_with($mime, 'image/') && in_array($mime, self::INLINE_MIMES, true) => 'image',
            default => null,
        };
    }

    /**
     * Răspunsul HTTP pentru un fișier încărcat. `$preferInline` e doar o PREFERINȚĂ a apelantului
     * (ruta de previzualizare) — decizia finală o ia conținutul: tip pasiv verificat → inline,
     * orice altceva → attachment.
     */
    public static function stream(string $disk, string $path, string $name, bool $preferInline = false): StreamedResponse
    {
        $mime = self::detectedMime($disk, $path);
        $inline = $preferInline && $mime !== null && in_array($mime, self::INLINE_MIMES, true);

        return Storage::disk($disk)->response($path, $name, [
            // Tipul DETECTAT, nu cel stocat la upload: ce declarăm browserului trebuie să fie ce
            // conține fișierul azi, pe disc.
            'Content-Type' => $mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            // Cadrul de previzualizare e same-origin; alte origini nu au ce îngloba de aici.
            'X-Frame-Options' => 'SAMEORIGIN',
        ], $inline ? 'inline' : 'attachment');
    }

    /** MIME-ul din CONȚINUTUL fișierului (finfo) — null când nu se poate determina. */
    private static function detectedMime(string $disk, string $path): ?string
    {
        $mime = Storage::disk($disk)->mimeType($path);

        return $mime === false ? null : $mime;
    }
}
