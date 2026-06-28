<?php

namespace App\Filament\Resources\ExamCommissions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExamCommissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('academic_year_id')
                ->label('An școlar')
                ->relationship('academicYear', 'name')
                ->required(),
            Select::make('subject_id')
                ->label('Disciplina')
                ->relationship('subject', 'name')
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->label('Denumire comisie')
                ->required()
                ->maxLength(255),
            Select::make('president_teacher_id')
                ->label('Președinte')
                ->relationship('president', 'last_name')
                ->searchable(),
            Select::make('members')
                ->label('Membri')
                ->relationship('members', 'last_name')
                ->multiple()
                ->searchable(),
        ]);
    }
}
