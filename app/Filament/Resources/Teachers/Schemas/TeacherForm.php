<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Enums\Sex;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label('Nume')
                    ->maxLength(50),
                TextInput::make('first_name')
                    ->label('Prenume')
                    ->required()
                    ->maxLength(50),
                Select::make('sex')
                    ->label('Sex')
                    ->options(Sex::class),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Funcția')
                    ->maxLength(255),
                Select::make('user_id')
                    ->label('Cont utilizator')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Opțional — leagă profesorul de un cont de logare.'),
            ]);
    }
}
