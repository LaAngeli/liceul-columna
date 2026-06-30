<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Audiența unui eveniment de calendar manual: toată școala, o treaptă (toate clasele unei trepte) sau
 * o clasă anume. Determină vizibilitatea pentru familii (vezi CalendarEvent::scopeVisibleToClass()).
 */
enum CalendarEventScope: string implements HasLabel
{
    case Global = 'global';
    case GradeLevel = 'grade_level';
    case SchoolClass = 'school_class';

    public function getLabel(): string
    {
        return (string) trans('enums.calendar_event_scope.'.$this->value);
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
