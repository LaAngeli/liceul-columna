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
}
