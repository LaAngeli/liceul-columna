<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                // Anul ÎNAINTEA clasei: clasa aparține unui an, deci opțiunile ei se filtrează pe
                // anul ales — altfel un elev putea fi înmatriculat „în anul X" la o clasă a anului Y.
                Select::make('academic_year_id')
                    ->label(__('panel.fields.academic_year'))
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('school_class_id', null))
                    // Un elev = o singură înmatriculare pe an școlar (unique DB, care vede ȘI rândurile
                    // arhivate). Prindem AMBELE cazuri ca mesaj pe câmp, nu ca eroare SQL 500 (audit M-4):
                    // duplicat activ → mesajul clasic; duplicat ARHIVAT → îndrumare spre restaurare.
                    ->rules([
                        static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $studentId = $get('student_id');

                            if (! $studentId || ! $value) {
                                return;
                            }

                            $conflict = Enrollment::withTrashed()
                                ->where('student_id', $studentId)
                                ->where('academic_year_id', $value)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->first();

                            if ($conflict !== null) {
                                $fail($conflict->trashed()
                                    ? __('panel.validation.enrollment.archived_duplicate')
                                    : __('panel.validation.enrollment.duplicate'));
                            }
                        },
                    ]),
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (Get $get): array => self::classOptions($get))
                    ->searchable()
                    ->required()
                    // Coerența clasă↔an și pe server (POST manipulat sau clasă schimbată de an între timp).
                    ->rules([
                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $yearId = $get('academic_year_id');

                            if (! $value || ! $yearId) {
                                return;
                            }

                            $belongsToYear = SchoolClass::query()
                                ->whereKey((int) $value)
                                ->where('academic_year_id', (int) $yearId)
                                ->exists();

                            if (! $belongsToYear) {
                                $fail(__('panel.validation.enrollment.class_year_mismatch'));
                            }
                        },
                    ]),
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

    /**
     * Clasele selectabile: doar cele ale anului școlar ales (toate, cât timp anul nu e ales —
     * pe Edit valorile existente se afișează oricum corect).
     *
     * @return array<int, string>
     */
    private static function classOptions(Get $get): array
    {
        $yearId = $get('academic_year_id');

        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');

        if ($yearId !== null && $yearId !== '') {
            $query->where('academic_year_id', (int) $yearId);
        }

        $options = [];
        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }
}
