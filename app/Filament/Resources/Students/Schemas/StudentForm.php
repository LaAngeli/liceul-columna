<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\SecondLanguage;
use App\Enums\Sex;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label(__('panel.fields.last_name'))
                    ->required()
                    ->maxLength(50),
                TextInput::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->required()
                    ->maxLength(50),
                Select::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->options(Sex::class),
                TextInput::make('register_number')
                    ->label(__('panel.fields.register_number'))
                    ->maxLength(10),
                Select::make('second_language')
                    ->label(__('panel.forms.student.second_language'))
                    ->options(SecondLanguage::class)
                    ->default(SecondLanguage::None->value)
                    ->required(),
                TextInput::make('english_group')
                    ->label(__('panel.forms.student.english_group'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3),
                Select::make('user_id')
                    ->label(__('panel.forms.student.account'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText(__('panel.forms.student.account_hint')),
            ]);
    }
}
