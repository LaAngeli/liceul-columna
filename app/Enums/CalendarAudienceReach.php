<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pentru un eveniment adresat unor elevi ANUME ({@see CalendarEventScope::Students}): cine, din
 * familia fiecărui elev vizat, îl vede în calendar. Elevul singur (o temă/o programare care-l
 * privește direct), doar părinții (o ședință cu adulții) sau ambii. Se aplică DOAR când audiența
 * e „elevi anume"; pentru clasă/treaptă/global câmpul e null (familia întreagă vede, ca la catalog).
 */
enum CalendarAudienceReach: string implements HasLabel
{
    case Student = 'student';
    case Guardians = 'guardians';
    case Both = 'both';

    public function getLabel(): string
    {
        return (string) trans('enums.calendar_audience_reach.'.$this->value);
    }

    /** Vede elevul însuși (contul de elev) evenimentul cu acest reach? */
    public function includesStudent(): bool
    {
        return $this === self::Student || $this === self::Both;
    }

    /** Văd părinții (tutorii) evenimentul cu acest reach? */
    public function includesGuardians(): bool
    {
        return $this === self::Guardians || $this === self::Both;
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
