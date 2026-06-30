<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipul unui eveniment de calendar MANUAL (modul Calendar v2). Determină categoria de culoare prin
 * {@see category()}: ședințele/evenimentele/activitățile = „Evenimente", termenele = „Termene-limită".
 */
enum CalendarEventType: string implements HasLabel
{
    case SchoolEvent = 'school_event';
    case Meeting = 'meeting';
    case Extracurricular = 'extracurricular';
    case Deadline = 'deadline';

    public function getLabel(): string
    {
        return (string) trans('enums.calendar_event_type.'.$this->value);
    }

    public function category(): CalendarCategory
    {
        return match ($this) {
            self::Deadline => CalendarCategory::Deadline,
            default => CalendarCategory::Event,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
