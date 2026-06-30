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
                    ->label(__('panel.forms.holiday.name'))
                    ->placeholder(__('panel.forms.holiday.name_placeholder'))
                    ->required()
                    ->maxLength(255),

                DatePicker::make('starts_on')
                    ->label(__('panel.forms.holiday.starts'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->required(),

                DatePicker::make('ends_on')
                    ->label(__('panel.forms.holiday.ends'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->afterOrEqual('starts_on')
                    ->helperText(__('panel.forms.holiday.ends_hint')),
            ]);
    }
}
