<?php

namespace App\Enums;

/**
 * Categoriile secțiunii „Generare rapoarte" (cerința beneficiarului, 2026-07-17): rapoartele nu
 * mai stau într-o singură listă, ci pe categorii logice — utilizatorul navighează pe carduri și
 * vede DOAR categoriile în care rolul lui poate genera ceva.
 */
enum ReportCategory: string
{
    case Elevi = 'elevi';
    case Evaluare = 'evaluare';
    case Frecventa = 'frecventa';
    case Clase = 'clase';
    case Profesori = 'profesori';
    case Administrative = 'administrative';

    public function label(): string
    {
        return (string) trans('panel.reports_nav.categories.'.$this->value);
    }

    public function description(): string
    {
        return (string) trans('panel.reports_nav.category_descriptions.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Elevi => 'heroicon-o-academic-cap',
            self::Evaluare => 'heroicon-o-chart-bar',
            self::Frecventa => 'heroicon-o-calendar-days',
            self::Clase => 'heroicon-o-rectangle-group',
            self::Profesori => 'heroicon-o-briefcase',
            self::Administrative => 'heroicon-o-building-library',
        };
    }
}
