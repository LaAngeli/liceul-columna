<?php

namespace App\Support;

use App\Enums\ScheduleType;
use App\Filament\Widgets\SchedulesToComplete;
use App\Models\Schedule;

/**
 * Ce înseamnă „orar neconfigurat" — DEFINIȚIE UNICĂ.
 *
 * Existau două, iar docblock-ul lui ListSchedules le declara identice: widget-ul
 * {@see SchedulesToComplete} număra tipurile fără rânduri PUBLICATE, iar
 * navigatorul de orare marca „fără date" doar tipurile fără NICIUN rând. Un tip cu tabele scrise
 * dar nepublicate apărea deci „complet" într-un ecran și „lipsă" în celălalt.
 *
 * Definiția oficială: **fără rânduri publicate**. Ăsta e sensul promis în eticheta afișată și
 * obligația administratorului operațional din §3.2 („publică orarul") — un orar scris dar
 * nepublicat nu ajunge la nimeni, deci sarcina nu e încheiată.
 */
final class ScheduleCoverage
{
    /**
     * Tipurile de orar (din cele 9) care nu au niciun tabel PUBLICAT.
     *
     * @return list<ScheduleType>
     */
    public static function missingTypes(): array
    {
        // pluck aplică cast-ul → normalizăm la valorile string ale enum-ului.
        $published = Schedule::query()
            ->where('is_public', true)
            ->pluck('type')
            ->map(static fn ($type): string => $type instanceof ScheduleType ? $type->value : (string) $type)
            ->all();

        return array_values(array_filter(
            ScheduleType::cases(),
            static fn (ScheduleType $type): bool => ! in_array($type->value, $published, true),
        ));
    }
}
