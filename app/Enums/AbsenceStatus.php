<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Statutul unei absențe, derivat din `is_motivated` (nullable): profesorul doar CONSEMNEAZĂ
 * absența (fără statut), dirigintele îi FIXEAZĂ statutul pe parcursul zilei — motivată sau
 * nemotivată. Vezi migrarea `2026_08_04_120000_make_absence_motivation_tristate`.
 *
 * Enum de AFIȘARE, nu de stocare: baza rămâne pe boolean nullable (istoricul și toate
 * interogările `where('is_motivated', …)` neschimbate), iar aici trăiește vocabularul unic
 * pentru badge-uri, filtre și export.
 */
enum AbsenceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Motivated = 'motivated';
    case Unmotivated = 'unmotivated';

    public static function fromMotivated(?bool $isMotivated): self
    {
        return match ($isMotivated) {
            null => self::Pending,
            true => self::Motivated,
            false => self::Unmotivated,
        };
    }

    /** Valoarea de stocat în `is_motivated` pentru acest statut. */
    public function motivatedValue(): ?bool
    {
        return match ($this) {
            self::Pending => null,
            self::Motivated => true,
            self::Unmotivated => false,
        };
    }

    public function label(): string
    {
        return (string) trans('enums.absence_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Motivated => 'success',
            self::Unmotivated => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Motivated => 'heroicon-o-check-circle',
            self::Unmotivated => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function getIcon(): string
    {
        return $this->icon();
    }
}
