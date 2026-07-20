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
                    ->label(__('panel.fields.last_name'))
                    ->maxLength(50),
                TextInput::make('first_name')
                    ->label(__('panel.fields.first_name'))
                    ->required()
                    ->maxLength(50),
                Select::make('sex')
                    ->label(__('panel.fields.sex'))
                    ->options(Sex::class),
                TextInput::make('email')
                    ->label(__('panel.fields.email'))
                    ->email()
                    ->maxLength(255),
                Select::make('user_id')
                    ->label(__('panel.forms.student.account'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText(__('panel.forms.teacher.account_hint')),
            ]);
    }
}
