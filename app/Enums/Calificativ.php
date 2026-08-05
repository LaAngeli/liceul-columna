<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Simbolurile de notare NEnumerică — singura sursă de adevăr pentru ce poate sta în
 * `grades.calificativ` (și, prin el, în propunerile de corecție).
 *
 * DE CE există: câmpul era text liber „cel mult 10 caractere", așa că în catalog putea intra
 * orice („test Calif" — sesizat de beneficiar pe 05.08.2026). Un simbol inventat nu se poate
 * agrega, nu se poate traduce și nu se poate compara între elevi — strică rapoartele tăcut.
 *
 * DE CE exact aceste șase: `docs/NOTARE-TIPURI-SI-SCALA.md` §3 dă ambele scale de la primar —
 * **calificative** (foarte bine / bine / suficient) și **descriptori** (independent / ghidat /
 * cu sprijin) — iar datele reale ale școlii le confirmă simbolurile: din 25.238 de calificative
 * importate, 25.236 sunt exact acestea; restul de 2 („R", „7") sunt greșeli de tastare din
 * sistemul vechi.
 *
 * DE CE NU se împart pe `GradingType`: la prima vedere „descriptiv ⇒ doar descriptori", dar
 * datele spun altceva — pe disciplinele marcate `d` școala scrie FB/B/S de 8.628 de ori, de trei
 * ori mai des decât descriptorii. O regulă per-subtip ar bloca practica reală a liceului, deci
 * restricția e la nivel de SIMBOL, nu de subtip. Ce rămâne exclus e doar textul inventat.
 */
enum Calificativ: string implements HasLabel
{
    // Calificative (Regulament: foarte bine / bine / suficient).
    case FoarteBine = 'FB';
    case Bine = 'B';
    case Suficient = 'S';

    // Descriptori (evaluare criterială prin descriptori, cl. I–IV).
    case Independent = 'i';
    case Ghidat = 'g';
    case CuSprijin = 'sp';

    public function label(): string
    {
        return (string) trans('enums.calificativ.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Simbolurile, grupate pe cele două scale — forma cerută de `Select::options()`.
     *
     * Grupurile sunt DOAR o etichetă de listă: ambele scale rămân alegibile la orice disciplină
     * ne-numerică, fiindcă școala le folosește amestecat (vezi nota de clasă).
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            (string) trans('enums.calificativ.group.calificative') => self::optionsFor(self::calificative()),
            (string) trans('enums.calificativ.group.descriptori') => self::optionsFor(self::descriptori()),
        ];
    }

    /**
     * Toate simbolurile valide, ca listă plată — pentru reguli `in:` și verificări de server.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Citește un simbol tastat de om: taie spațiile și iartă majusculele („fb" → FB, „SP" → sp).
     *
     * Necesar doar pe calea de introducere rapidă din borderou, unde profesorul TASTEAZĂ direct
     * în celulă; acolo, a respinge „fb" ar fi pedanterie, nu protecție. Formularele cu `Select`
     * trimit deja valoarea canonică. Întoarce null pentru orice nu e simbol cunoscut.
     */
    public static function normalize(string $raw): ?self
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === mb_strtolower($trimmed)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<self>
     */
    private static function calificative(): array
    {
        return [self::FoarteBine, self::Bine, self::Suficient];
    }

    /**
     * @return list<self>
     */
    private static function descriptori(): array
    {
        return [self::Independent, self::Ghidat, self::CuSprijin];
    }

    /**
     * @param  list<self>  $cases
     * @return array<string, string>
     */
    private static function optionsFor(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
