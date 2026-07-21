<?php

namespace App\Enums;

/**
 * Ciclul de școlarizare, după treaptă (clasa 1-12). Regulile de calcul al mediilor
 * și statutul elevului diferă pe ciclu (§2.4 / §2.5 din specificație).
 */
enum SchoolCycle: string
{
    case Primar = 'primar';      // I–IV
    case Gimnaziu = 'gimnaziu';  // V–IX
    case Liceu = 'liceu';        // X–XII

    public static function fromGradeLevel(int $gradeLevel): self
    {
        return match (true) {
            $gradeLevel <= 4 => self::Primar,
            $gradeLevel <= 9 => self::Gimnaziu,
            default => self::Liceu,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Primar => 'Primar',
            self::Gimnaziu => 'Gimnaziu',
            self::Liceu => 'Liceu',
        };
    }

    /** Treapta minimă / maximă VALIDĂ în structura școlii (clasa I–XII) — sursă unică. */
    public const MIN_GRADE_LEVEL = 1;

    public const MAX_GRADE_LEVEL = 12;

    /**
     * Cifra romană a unei trepte (I–XII) — convenția de afișare a claselor în tot panoul.
     */
    public static function romanNumeral(int $gradeLevel): string
    {
        return match ($gradeLevel) {
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            default => (string) $gradeLevel,
        };
    }

    /**
     * Opțiunile de treaptă pentru selectoare (formulare standardizate — cerința 2026-07-21:
     * clasele se ALEG, nu se tastează): „Clasa V — Gimnaziu" etc., traduse în limba interfeței.
     *
     * @return array<int, string>
     */
    public static function gradeLevelOptions(): array
    {
        $options = [];

        foreach (range(self::MIN_GRADE_LEVEL, self::MAX_GRADE_LEVEL) as $grade) {
            $options[$grade] = (string) __('panel.forms.subject.grade_option', [
                'roman' => self::romanNumeral($grade),
                'cycle' => self::fromGradeLevel($grade)->label(),
            ]);
        }

        return $options;
    }
}
