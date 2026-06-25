<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Elev')
                    ->relationship('student', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->preload()
                    ->required(),
                Select::make('school_class_id')
                    ->label('Clasa')
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('academic_year_id')
                    ->label('An școlar')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('enrolled_on')
                    ->label('Înmatriculat la'),
                DatePicker::make('left_on')
                    ->label('A plecat la'),
            ]);
    }
}
