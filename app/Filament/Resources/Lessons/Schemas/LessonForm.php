<?php

namespace App\Filament\Resources\Lessons\Schemas;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label('An școlar')
                    ->relationship('academicYear', 'name')
                    ->default(fn (): ?int => AcademicYear::query()->latest('id')->value('id'))
                    ->required(),
                Select::make('school_class_id')
                    ->label('Clasa')
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn (SchoolClass $record): string => trim($record->name.' '.($record->section ?? '')))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('subject_id')
                    ->label('Disciplina')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('teacher_id')
                    ->label('Profesor')
                    ->relationship('teacher', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => $record->full_name)
                    ->searchable()
                    ->preload(),
                Select::make('day_of_week')
                    ->label('Ziua')
                    ->options(Weekday::class)
                    ->required(),
                Select::make('lesson_number')
                    ->label('Lecția nr.')
                    ->options(array_combine(range(1, 8), array_map(fn (int $n): string => (string) $n, range(1, 8))))
                    ->required(),
                TextInput::make('room')
                    ->label('Sala')
                    ->maxLength(20),
            ]);
    }
}
