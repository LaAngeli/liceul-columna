<?php

namespace App\Filament\Resources\Schedules\Schemas;

use App\Enums\ScheduleType;
use App\Models\SchoolClass;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipul orarului')
                    ->options(ScheduleType::options())
                    ->native(false)
                    ->required(),
                TextInput::make('label')
                    ->label('Titlu (ex. clasa / grupul)')
                    ->required()
                    ->maxLength(150),
                Select::make('school_class_id')
                    ->label('Clasa (pentru orarul „lecții")')
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    ->searchable()
                    ->preload()
                    ->helperText('Leagă orarul de clasa reală → poate apărea în cabinetul elevilor. Opțional; gol pentru orarele globale (sunete, examene…).'),
                TextInput::make('position')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_public')
                    ->label('Publicat pe site')
                    ->helperText('Dacă e oprit, orarul rămâne în panou (draft) și NU apare pe site-ul public.')
                    ->default(true),
                TextInput::make('headers')
                    ->label('Antet (capete de coloană, separate prin „|")')
                    ->helperText('Ex.:  | Luni | Marți | Miercuri | Joi | Vineri')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(' | ', $state) : (string) $state)
                    ->dehydrateStateUsing(fn ($state): array => array_map('trim', explode('|', (string) $state)))
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('rows')
                    ->label('Rânduri (un rând pe linie; celulele se separă prin „|")')
                    ->helperText('Fiecare linie = un rând. Ex.:  Lecția 1 | Matematică | Limba română | Fizică | Chimie | Biologie')
                    ->rows(14)
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }

                        return implode("\n", array_map(
                            fn ($row): string => is_array($row) ? implode(' | ', $row) : (string) $row,
                            $state,
                        ));
                    })
                    ->dehydrateStateUsing(function ($state): array {
                        $lines = preg_split('/\r\n|\r|\n/', (string) $state) ?: [];
                        $rows = [];
                        foreach ($lines as $line) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $rows[] = array_map('trim', explode('|', $line));
                        }

                        return $rows;
                    })
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
