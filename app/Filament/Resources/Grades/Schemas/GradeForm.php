<?php

namespace App\Filament\Resources\Grades\Schemas;

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Support\ContentTranslator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label(__('panel.fields.class'))
                    ->options(fn (): array => self::classOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('student_id', null)),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options(fn (): array => self::subjectOptions())
                    ->searchable()
                    ->required()
                    // La schimbarea disciplinei, câmpul de notare se adaptează (numeric vs calificativ)
                    // → curățăm valorile vechi ca să nu rămână una nepotrivită ascunsă în payload.
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('value', null);
                        $set('calificativ', null);
                    }),
                Select::make('student_id')
                    ->label(__('panel.fields.student'))
                    ->options(fn (Get $get): array => self::studentOptions(
                        ($classId = $get('school_class_id')) !== null ? (int) $classId : null,
                    ))
                    ->searchable()
                    ->required(),
                // Semestrul NU se alege manual: se derivă din `graded_on` pe server (EnforcesGradeScope).
                Select::make('evaluation_type')
                    ->label(__('panel.fields.evaluation_type'))
                    ->options(EvaluationType::options())
                    ->default(EvaluationType::Curenta->value)
                    ->native(false)
                    ->required(),
                // O notă nu poate fi în viitor; data determină și semestrul (derivat pe server).
                DatePicker::make('graded_on')
                    ->label(__('panel.fields.date'))
                    ->required()
                    ->default(now())
                    ->maxDate(now()),
                // Câmpul de NOTĂ NUMERICĂ: vizibil + obligatoriu DOAR pentru disciplinele numerice
                // (sau cât timp disciplina nu e aleasă). Intervalul min/max vine din Subject.
                // Vizibilitatea pe grading_type asigură structural că NU pot coexista notă și calificativ
                // (rezolvă regula „notă SAU calificativ"): pentru o disciplină cunoscută, doar UN câmp e vizibil.
                TextInput::make('value')
                    ->label(__('panel.fields.value'))
                    ->numeric()
                    ->minValue(fn (Get $get): int => self::bounds($get)[0])
                    ->maxValue(fn (Get $get): int => self::bounds($get)[1])
                    ->helperText(fn (Get $get): string => self::valueHelper($get))
                    ->visible(fn (Get $get): bool => self::showsValue($get))
                    ->required(fn (Get $get): bool => self::gradingType($get) === GradingType::Numeric),
                // Câmpul de CALIFICATIV: vizibil + obligatoriu DOAR pentru disciplinele pe calificativ/descriptiv.
                TextInput::make('calificativ')
                    ->label(__('panel.fields.calificativ'))
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => self::showsCalificativ($get))
                    ->required(fn (Get $get): bool => self::gradingType($get) !== null
                        && self::gradingType($get) !== GradingType::Numeric),
                // Autorul = profesorul logat (la administratori rămâne gol).
                Hidden::make('teacher_id')
                    ->default(fn (): ?int => auth('web')->user()?->teacher?->id),
            ]);
    }

    private static function currentTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /** Disciplina aleasă în formular (sau null cât timp nu e selectată). */
    private static function subjectFor(Get $get): ?Subject
    {
        $id = $get('subject_id');

        return $id !== null && $id !== '' ? Subject::find((int) $id) : null;
    }

    /** Modul de notare al disciplinei alese (null = încă neselectată). */
    private static function gradingType(Get $get): ?GradingType
    {
        return self::subjectFor($get)?->grading_type;
    }

    /** Câmpul de notă numerică e vizibil pentru disciplinele numerice — sau cât timp nu s-a ales una. */
    private static function showsValue(Get $get): bool
    {
        $type = self::gradingType($get);

        return $type === null || $type === GradingType::Numeric;
    }

    /** Câmpul de calificativ e vizibil pentru disciplinele pe calificativ/descriptiv — sau dacă nu s-a ales una. */
    private static function showsCalificativ(Get $get): bool
    {
        $type = self::gradingType($get);

        return $type === null || in_array($type, [
            GradingType::Calificativ,
            GradingType::CalificativDescriptiv,
            GradingType::Descriptiv,
        ], true);
    }

    /**
     * Intervalul [min, max] al disciplinei alese (sau [1, 10] implicit cât timp nu e aleasă).
     *
     * @return array{0: int, 1: int}
     */
    private static function bounds(Get $get): array
    {
        $subject = self::subjectFor($get);

        if ($subject === null) {
            return [1, 10];
        }

        return [$subject->min_grade ?? 1, $subject->max_grade ?? 10];
    }

    /** Helper-ul câmpului de notă: intervalul disciplinei dacă e aleasă, altfel îndemnul de a alege disciplina. */
    private static function valueHelper(Get $get): string
    {
        $subject = self::subjectFor($get);

        if ($subject === null) {
            return __('panel.forms.grade.helper_pick_subject');
        }

        [$min, $max] = self::bounds($get);

        return __('panel.forms.grade.helper_value_range', ['min' => $min, 'max' => $max]);
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
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        $query = Subject::query()->orderBy('name');

        if ($teacher = self::currentTeacher()) {
            $query->whereKey($teacher->taughtSubjectIds());
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * Elevii selectabili. Dacă o clasă e aleasă în formular, lista se restrânge la elevii ei
     * (cascadă). Profesorul rămâne mereu limitat la clasele lui — chiar dacă forțează în formular
     * o clasă din afara scope-ului, intersecția dă listă goală.
     *
     * @return array<int, string>
     */
    private static function studentOptions(?int $schoolClassId = null): array
    {
        $query = Student::query()->orderBy('last_name')->orderBy('first_name');

        $teacher = self::currentTeacher();
        $classIds = null;

        if ($schoolClassId !== null) {
            $classIds = $teacher !== null
                ? array_values(array_intersect([$schoolClassId], $teacher->visibleSchoolClassIds()))
                : [$schoolClassId];
        } elseif ($teacher !== null) {
            $classIds = $teacher->visibleSchoolClassIds();
        }

        if ($classIds !== null) {
            $studentIds = Enrollment::query()
                ->whereIn('school_class_id', $classIds)
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
