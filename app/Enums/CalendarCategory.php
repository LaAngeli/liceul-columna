<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Taxonomia evenimentelor de calendar (modul Calendar). Fiecare categorie are o etichetă RO și o
 * cheie de culoare semantică, mapată în frontend pe paleta de brand (navy/verde + accente).
 */
enum CalendarCategory: string implements HasLabel
{
    case Homework = 'homework';
    case Assessment = 'assessment';
    case Absence = 'absence';
    case Deadline = 'deadline';
    case Event = 'event';
    case Schedule = 'schedule';
    case Structure = 'structure';
    case Communication = 'communication';

    public function getLabel(): string
    {
        return match ($this) {
            self::Homework => 'Teme',
            self::Assessment => 'Evaluări și examene',
            self::Absence => 'Absențe',
            self::Deadline => 'Termene-limită',
            self::Event => 'Evenimente și ședințe',
            self::Schedule => 'Orar',
            self::Structure => 'Structură (semestre, vacanțe)',
            self::Communication => 'Comunicări',
        };
    }

    /**
     * Cheie de culoare semantică (NU un hex) — frontend-ul o mapează pe tokenii de brand, ca să
     * rămână consistentă cu tema (light/dark) și cu paleta din `app.css`.
     */
    public function color(): string
    {
        return match ($this) {
            self::Homework => 'success',
            self::Assessment => 'accent',
            self::Absence => 'danger',
            self::Deadline => 'warning',
            self::Event => 'event',
            self::Schedule => 'neutral',
            self::Structure => 'muted',
            self::Communication => 'info',
        };
    }
}
