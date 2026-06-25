<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Denumire')
                    ->placeholder('ex: 2025–2026')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label('Începe la'),
                DatePicker::make('ends_on')
                    ->label('Se termină la'),
                Toggle::make('is_current')
                    ->label('An curent'),
            ]);
    }
}
