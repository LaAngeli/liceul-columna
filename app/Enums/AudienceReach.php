<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Reach-ul FAMILIAL al unei audiențe nominale (elevi aleși pe nume): cine, din familia fiecărui
 * elev vizat, primește conținutul — elevul singur (ceva care-l privește direct), doar părinții
 * (o discuție cu adulții) sau ambii. Partajat între modulele cu audiență nominală: evenimentele
 * de calendar ({@see CalendarEventScope::Students}) și anunțurile
 * ({@see AnnouncementAudience::Students}). Null pe audiențele largi (familia întreagă, ca la catalog).
 */
enum AudienceReach: string implements HasLabel
{
    case Student = 'student';
    case Guardians = 'guardians';
    case Both = 'both';

    public function getLabel(): string
    {
        return (string) trans('enums.audience_reach.'.$this->value);
    }

    /** E inclus elevul însuși (contul lui) în acest reach? */
    public function includesStudent(): bool
    {
        return $this === self::Student || $this === self::Both;
    }

    /** Sunt incluși părinții (tutorii) în acest reach? */
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
