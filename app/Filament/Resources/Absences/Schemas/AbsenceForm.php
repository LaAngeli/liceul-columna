<?php

namespace App\Filament\Resources\Absences\Schemas;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\ContentTranslator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AbsenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label('Clasa')
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->required(),
                Select::make('subject_id')
                    ->label('Disciplina')
                    ->options(fn (): array => self::subjectOptions())
                    ->searchable(),
                Select::make('student_id')
                    ->label('Elev')
                    ->options(fn (): array => self::studentOptions())
                    ->searchable()
                    ->required(),
                Select::make('term_id')
                    ->label('Semestrul')
                    ->relationship('term', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('occurred_on')
                    ->label('Data')
                    ->required()
                    ->default(now()),
                Toggle::make('is_motivated')
                    ->label('Motivată'),
                Hidden::make('teacher_id')
                    ->default(fn (): ?int => auth()->user()?->teacher?->id),
            ]);
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth()->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /**
     * @return array<int, string>
     */
    private static function classOptions(): array
    {
        $query = SchoolClass::query()->orderBy('grade_level')->orderBy('name');

        if ($teacher = self::currentTeacher()) {
            $query->whereKey($teacher->visibleSchoolClassIds());
        }

        $options = [];
        foreach ($query->get() as $class) {
            $options[$class->id] = trim($class->name.' '.($class->section ?? ''));
        }

        return $options;
    }

    /**
     * Profesorul pur vede doar disciplinele lui; dirigintele (și administrația) toate
     * disciplinele — gestionează absențele întregii clase, indiferent de lecție.
     *
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        $query = Subject::query()->orderBy('name');

        $teacher = self::currentTeacher();
        if ($teacher && $teacher->homeroomSchoolClassIds() === []) {
            $query->whereKey($teacher->taughtSubjectIds());
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private static function studentOptions(): array
    {
        $query = Student::query()->orderBy('last_name')->orderBy('first_name');

        if ($teacher = self::currentTeacher()) {
            $studentIds = Enrollment::query()
                ->whereIn('school_class_id', $teacher->visibleSchoolClassIds())
                ->pluck('student_id');
            $query->whereKey($studentIds);
        }

        $options = [];
        foreach ($query->get() as $student) {
            $options[$student->id] = $student->full_name;
        }

        return $options;
    }
}
