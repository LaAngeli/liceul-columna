<?php

namespace App\Filament\Resources\SchoolClasses\Schemas;

use App\Models\Teacher;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label('An școlar')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('grade_level')
                    ->label('Treapta (clasa)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->required(),
                TextInput::make('name')
                    ->label('Denumire')
                    ->placeholder('ex: VIII')
                    ->required()
                    ->maxLength(20),
                TextInput::make('section')
                    ->label('Litera/secția')
                    ->placeholder('ex: A / 1')
                    ->maxLength(4),
                Select::make('homeroom_teacher_id')
                    ->label('Diriginte')
                    ->relationship('homeroomTeacher', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->preload(),
            ]);
    }
}
