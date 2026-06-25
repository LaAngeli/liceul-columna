<?php

namespace App\Filament\Resources\Terms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label('An școlar')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('number')
                    ->label('Numărul semestrului')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4)
                    ->required(),
                TextInput::make('name')
                    ->label('Denumire')
                    ->placeholder('ex: Semestrul I')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label('Începe la'),
                DatePicker::make('ends_on')
                    ->label('Se termină la'),
                Toggle::make('is_current')
                    ->label('Semestru curent'),
            ]);
    }
}
