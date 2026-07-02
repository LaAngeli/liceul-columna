<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul evaluării unei note (§1 / §2 „tip_nota" din anexa de notare). Tipul e obligatoriu și
 * predefinit; sumativa semestrială (ESS la gimnaziu, teză la liceu) NU se amestecă cu notele
 * curente — e ponderată separat. Regulile de tratare (intră-în-MC / ponderată / pondere) sunt
 * definite aici, ca sursă unică — motorul de calcul le citește, nu conține reguli codate rigid.
 */
enum EvaluationType: string implements HasLabel
{
    case Curenta = 'curenta';
    case Esi = 'esi';
    case Teza = 'teza';

    /**
     * Ponderea sumativei semestriale (ESS/teză) în media semestrială. O schimbare de pondere
     * se face DOAR aici (tip_nota §2), fără a atinge motorul de calcul.
     */
    private const SUMMATIVE_WEIGHT = 0.50;

    public function label(): string
    {
        return (string) trans('enums.evaluation_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * tip_nota §2 (intra_in_MC): contribuie la media notelor curente (MC)? Curenta și ESI → da;
     * sumativa semestrială (ESS/teză) → nu (e ponderată separat).
     */
    public function countsAsCurrent(): bool
    {
        return match ($this) {
            self::Curenta, self::Esi => true,
            self::Teza => false,
        };
    }

    /**
     * tip_nota §2 (ponderata): nota e ponderată separat în media semestrială (nu intră în MC)?
     */
    public function isWeighted(): bool
    {
        return ! $this->countsAsCurrent();
    }

    /**
     * tip_nota §2 (pondere): ponderea sumativei semestriale în MS; null dacă neponderată.
     */
    public function weight(): ?float
    {
        return $this->isWeighted() ? self::SUMMATIVE_WEIGHT : null;
    }

    /**
     * Eticheta sumativei semestriale diferă pe ciclu (un singur cod, etichetă pe ciclu):
     * gimnaziu → „ESS", liceu → „Teză". Restul tipurilor păstrează eticheta standard.
     */
    public function labelForCycle(?SchoolCycle $cycle): string
    {
        if ($this === self::Teza && $cycle === SchoolCycle::Gimnaziu) {
            return (string) trans('enums.evaluation_type.ess');
        }

        return $this->label();
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
