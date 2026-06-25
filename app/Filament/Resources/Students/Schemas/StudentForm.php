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
                    ->label('Nume')
                    ->required()
                    ->maxLength(50),
                TextInput::make('first_name')
                    ->label('Prenume')
                    ->required()
                    ->maxLength(50),
                Select::make('sex')
                    ->label('Sex')
                    ->options(Sex::class),
                TextInput::make('register_number')
                    ->label('Nr. matricol')
                    ->maxLength(10),
                Select::make('second_language')
                    ->label('Limba străină 2')
                    ->options(SecondLanguage::class)
                    ->default(SecondLanguage::None->value)
                    ->required(),
                TextInput::make('english_group')
                    ->label('Grupa de engleză')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(3),
                Select::make('user_id')
                    ->label('Cont utilizator')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Opțional — leagă elevul de un cont de logare.'),
            ]);
    }
}
