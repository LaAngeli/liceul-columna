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
                    ->label(__('panel.forms.school_class.academic_year'))
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('grade_level')
                    ->label(__('panel.forms.school_class.grade_level'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->required(),
                TextInput::make('name')
                    ->label(__('panel.forms.school_class.name'))
                    ->placeholder(__('panel.forms.school_class.name_placeholder'))
                    ->required()
                    ->maxLength(20),
                TextInput::make('section')
                    ->label(__('panel.forms.school_class.section'))
                    ->placeholder(__('panel.forms.school_class.section_placeholder'))
                    ->maxLength(4),
                // Diriginte OBLIGATORIU doar la CREARE — o clasă nouă nu se naște orfană. La EDITARE
                // rămâne opțional: administrația responsabilă (canConfigureSchool) poate schimba SAU
                // retrage dirigintele după necesitate (vacanță). DB-ul e nullable intenționat — import
                // legacy + vacanță prin nullOnDelete. Reziduul e rezolvat în widget-ul „Clase fără
                // diriginte", vizibil doar celor ce pot opera pe clase.
                Select::make('homeroom_teacher_id')
                    ->label(__('panel.tables.school_classes.homeroom'))
                    ->helperText(__('panel.forms.school_class.homeroom_help'))
                    ->relationship('homeroomTeacher', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Teacher $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->preload()
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }
}
