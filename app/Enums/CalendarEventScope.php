<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Audiența unui eveniment de calendar manual: toată școala, o treaptă (toate clasele unei trepte),
 * o clasă anume sau elevi anume (unul sau mai mulți, aleși nominal). Determină vizibilitatea pentru
 * familii — clasă/treaptă/global prin {@see CalendarEvent::scopeVisibleToClass()}, elevii anume prin
 * relația {@see CalendarEvent::students()} + reach-ul {@see CalendarAudienceReach}.
 */
enum CalendarEventScope: string implements HasLabel
{
    case Global = 'global';
    case GradeLevel = 'grade_level';
    case SchoolClass = 'school_class';
    case Students = 'students';

    public function getLabel(): string
    {
        return (string) trans('enums.calendar_event_scope.'.$this->value);
    }

    /** Audiența nominală (elevi aleși individual), spre deosebire de audiențele pe clasă/treaptă/global. */
    public function isNominal(): bool
    {
        return $this === self::Students;
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
