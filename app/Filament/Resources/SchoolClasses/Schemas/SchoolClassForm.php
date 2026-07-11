<?php

namespace App\Filament\Resources\SchoolClasses\Schemas;

use App\Models\SchoolClass;
use App\Models\Teacher;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                    ->maxLength(4)
                    // Unicitatea (an, treaptă, literă) trăia doar ca index DB — duplicatul se termina
                    // în eroare SQL 500. Indexul vede ȘI rândurile arhivate (fără deleted_at în cheie),
                    // deci recrearea unei clase arhivate pică la fel → mesaj clar + îndrumare spre
                    // restaurare. Ca DB-ul, validăm doar literele completate (NULL nu colizionează).
                    ->rules([
                        static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $section = is_string($value) ? trim($value) : '';
                            $yearId = $get('academic_year_id');
                            $gradeLevel = $get('grade_level');

                            if ($section === '' || ! $yearId || ! $gradeLevel) {
                                return;
                            }

                            $conflict = SchoolClass::withTrashed()
                                ->where('academic_year_id', (int) $yearId)
                                ->where('grade_level', (int) $gradeLevel)
                                ->where('section', $section)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->first();

                            if ($conflict !== null) {
                                $fail($conflict->trashed()
                                    ? __('panel.validation.school_class.archived_duplicate')
                                    : __('panel.validation.school_class.duplicate'));
                            }
                        },
                    ]),
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
