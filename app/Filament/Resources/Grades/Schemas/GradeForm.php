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
                    // Venind din navigatorul de catalog, contextul (clasa/disciplina) sosește în
                    // query string și pre-completează formularul — DOAR dacă e printre opțiunile
                    // permise rolului (un id străin e ignorat, nu preluat orbește).
                    ->default(fn (): ?int => self::requestedContextId('clasa', self::classOptions()))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('student_id', null)),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    ->options(fn (): array => self::subjectOptions())
                    ->default(fn (): ?int => self::requestedContextId('disciplina', self::subjectOptions()))
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
                    ->maxDate(now())
                    ->validationMessages(['before_or_equal' => __('validation.not_future_date')]),
                // Câmpul de NOTĂ NUMERICĂ: vizibil + obligatoriu DOAR pentru disciplinele numerice
                // (sau cât timp disciplina nu e aleasă). Intervalul e FIX 1–10 (scala oficială, §3) —
                // Subject::min_grade/max_grade NU sunt limitele notei: sunt „De la clasă / Până la
                // clasă" (treapta la care se predă disciplina, ex. Chimie 7–12 = clasele VII-XII), un
                // concept diferit. Vizibilitatea pe grading_type asigură structural că NU pot coexista
                // notă și calificativ (rezolvă regula „notă SAU calificativ").
                TextInput::make('value')
                    ->label(__('panel.fields.value'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
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

    /**
     * Id-ul de context primit în query string (din navigatorul de catalog), acceptat DOAR dacă
     * face parte din opțiunile permise rolului — altfel null (fără pre-completare).
     *
     * @param  array<int, string>  $options
     */
    private static function requestedContextId(string $parameter, array $options): ?int
    {
        $raw = request()->query($parameter);

        if (! is_string($raw) || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return array_key_exists($id, $options) ? $id : null;
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

    /** Helper-ul câmpului de notă: intervalul FIX 1–10 dacă disciplina e aleasă, altfel îndemnul de a o alege. */
    private static function valueHelper(Get $get): string
    {
        if (self::subjectFor($get) === null) {
            return __('panel.forms.grade.helper_pick_subject');
        }

        return __('panel.forms.grade.helper_value_range', ['min' => 1, 'max' => 10]);
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
