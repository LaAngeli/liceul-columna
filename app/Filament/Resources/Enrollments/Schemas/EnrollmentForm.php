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
                    ->label(__('panel.fields.student'))
                    ->relationship('student', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->preload()
                    ->required(),
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('enrolled_on')
                    ->label(__('panel.fields.enrolled_on'))
                    ->beforeOrEqual('left_on'),
                // Data de plecare TREBUIE să fie după înmatriculare — altfel intervalul e negativ
                // și calcule de istoric devin incoerente. Pattern aliniat cu Holiday/CalendarEvent.
                DatePicker::make('left_on')
                    ->label(__('panel.fields.left_on'))
                    ->after('enrolled_on'),
            ]);
    }
}
