<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Perioada pentru care e calculată media din foaia matricolă:
 * semestrele I/II și media anuală (legacy `sem` 1/2/3).
 */
enum AcademicRecordPeriod: int implements HasLabel
{
    case SemesterI = 1;
    case SemesterII = 2;
    case Annual = 3;

    public function label(): string
    {
        return match ($this) {
            self::SemesterI => 'Semestrul I',
            self::SemesterII => 'Semestrul II',
            self::Annual => 'Media anuală',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): int => $case->value, self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
