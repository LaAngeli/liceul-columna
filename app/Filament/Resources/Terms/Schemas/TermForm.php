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
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('number')
                    ->label(__('panel.forms.term.number'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4)
                    ->required(),
                TextInput::make('name')
                    ->label(__('panel.forms.term.name'))
                    ->placeholder(__('panel.forms.term.name_placeholder'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label(__('panel.fields.starts_on')),
                DatePicker::make('ends_on')
                    ->label(__('panel.fields.ends_on')),
                Toggle::make('is_current')
                    ->label(__('panel.forms.term.is_current')),
            ]);
    }
}
