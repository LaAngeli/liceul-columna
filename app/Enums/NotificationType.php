<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Evenimentele care pot genera o notificare (spec §5). Fiecare familie alege, în setări, pe ce
 * canale primește fiecare tip — vezi {@see NotificationChannel} și `User::channelsFor()`.
 */
enum NotificationType: string implements HasLabel
{
    case NewGrade = 'new_grade';
    case NewAbsence = 'new_absence';
    case NewHomework = 'new_homework';
    case NewMessage = 'new_message';
    case StatusChange = 'status_change';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::NewGrade => 'Notă nouă',
            self::NewAbsence => 'Absență nouă',
            self::NewHomework => 'Temă nouă',
            self::NewMessage => 'Mesaj nou',
            self::StatusChange => 'Schimbare de statut',
            self::Announcement => 'Anunț al conducerii',
        };
    }

    public function getLabel(): string
    {
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
