<?php

namespace App\Enums;

use App\Models\Enrollment;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * DE CE a plecat elevul din școală — explicația datei `left_on` de pe înmatriculare.
 *
 * Registrul consemna PLECAREA ({@see Enrollment::$left_on}) ca dată goală de sens: o absolvire, un
 * transfer și o exmatriculare arătau identic. Motivul le separă fără să introducă o a doua sursă de
 * adevăr — data rămâne singurul reper, iar tot ce filtrează deja pe `left_on` (anunțuri, rapoarte,
 * calendar, catalog, promovare, retenție) funcționează neschimbat.
 *
 * ⚠️ Nu e un status pe ELEV. Un status pe elev ar trebui resincronizat la fiecare transfer între
 * clase și ar intra în contradicție cu registrul la prima divergență; motivul stă pe rândul care
 * descrie efectiv ieșirea.
 */
enum DepartureReason: string implements HasColor, HasLabel
{
    case Absolvire = 'absolvire';
    case Transfer = 'transfer';
    case Retragere = 'retragere';
    case Exmatriculare = 'exmatriculare';

    public function label(): string
    {
        return (string) trans('enums.departure_reason.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Absolvire => 'success',
            self::Transfer => 'info',
            self::Retragere => 'warning',
            self::Exmatriculare => 'danger',
        };
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /**
     * Ieșirea a fost una NORMALĂ, la capătul studiilor. Singura care deschide accesul de absolvent
     * (arhiva proprie + adeverințe): cine a fost transferat își cere actele de la școala nouă, iar
     * o exmatriculare nu e o absolvire.
     */
    public function isGraduation(): bool
    {
        return $this === self::Absolvire;
    }

    /**
     * @return array<string, string>
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
