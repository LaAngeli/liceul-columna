<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Stările unei cereri de înscriere (admitere): nou → contactat → înmatriculat / refuzat.
 * Fluxul nu trece prin aprobare cu motiv (ca {@see RequestStatus}); secretariatul îl mută
 * direct prin edit la cerere.
 */
enum AdmissionStatus: string implements HasLabel
{
    case Nou = 'nou';
    case Contactat = 'contactat';
    case Inmatriculat = 'inmatriculat';
    case Refuzat = 'refuzat';

    public function label(): string
    {
        return (string) trans('enums.admission_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Nou => 'warning',
            self::Contactat => 'info',
            self::Inmatriculat => 'success',
            self::Refuzat => 'danger',
        };
    }

    /** Cererea e închisă (nu mai are nevoie de procesare). */
    public function isFinal(): bool
    {
        return $this === self::Inmatriculat || $this === self::Refuzat;
    }

    /**
     * Stările din coada „De procesat".
     *
     * @return list<string>
     */
    public static function pendingValues(): array
    {
        return [self::Nou->value, self::Contactat->value];
    }

    /**
     * Stările arhivei (cereri închise).
     *
     * @return list<string>
     */
    public static function finalValues(): array
    {
        return [self::Inmatriculat->value, self::Refuzat->value];
    }
}
