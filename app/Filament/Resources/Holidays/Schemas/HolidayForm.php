<?php

namespace App\Filament\Resources\Holidays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Denumire')
                    ->placeholder('Vacanța de iarnă, Ziua Independenței…')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('starts_on')
                    ->label('Începe')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->required(),

                DatePicker::make('ends_on')
                    ->label('Se termină')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->afterOrEqual('starts_on')
                    ->helperText('Lasă gol pentru o singură zi.'),
            ]);
    }
}
