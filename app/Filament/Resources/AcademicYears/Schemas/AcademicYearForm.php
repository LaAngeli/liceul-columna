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
                    ->label(__('panel.forms.academic_year.name'))
                    ->placeholder(__('panel.forms.academic_year.name_placeholder'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label(__('panel.fields.starts_on')),
                DatePicker::make('ends_on')
                    ->label(__('panel.fields.ends_on')),
                Toggle::make('is_current')
                    ->label(__('panel.forms.academic_year.is_current')),
            ]);
    }
}
