<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SURSA UNICĂ a inițialelor afișate în avatare (panou + cabinet).
 *
 * Înainte existau PATRU reguli diferite pentru același om: providerul implicit Filament trimitea
 * prima literă a FIECĂRUI cuvânt către ui-avatars.com (care afișa prima+ultima → „[V"),
 * WelcomeWidget lua primele două cuvinte cu litere (→ „DU", cu „D" din marcajul [DEMO]), fișa
 * elevului compunea din last_name+first_name, iar hook-ul React lua primul+ultimul cuvânt.
 * Același director apărea cu „IV" în bara de sus și „DU" pe panoul de control.
 *
 * Regula standard (oglindită 1:1 în `resources/js/hooks/use-initials.tsx`):
 *  1. marcajele în paranteze drepte („[DEMO]") NU fac parte din nume și se elimină;
 *  2. se păstrează doar cuvintele care conțin măcar o literă (sar „-", „•", numere);
 *  3. se iau primele litere ale PRIMELOR DOUĂ cuvinte rămase — convenția catalogului e
 *     „Nume Prenume", deci primele două cuvinte sunt exact numele de familie + prenumele
 *     (prima+ultima ar da „BM" pentru „Bujor-Cobili Carolina Maria", în loc de „BC").
 */
final class Initials
{
    public static function for(?string $name): string
    {
        $clean = preg_replace('/\[[^\]]*\]/u', ' ', (string) $name) ?? '';

        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($clean)) ?: [],
            static fn (string $word): bool => preg_match('/\p{L}/u', $word) === 1,
        ));

        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            if (preg_match('/\p{L}/u', $word, $match) === 1) {
                $initials .= mb_strtoupper($match[0]);
            }
        }

        return $initials;
    }
}
