<?php

namespace App\Filament\Resources\Absences\Schemas;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\ContentTranslator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AbsenceForm
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
                    ->afterStateUpdated(function (Set $set): void {
                        $set('student_id', null);
                        $set('subject_id', null);
                    }),
                Select::make('subject_id')
                    ->label(__('panel.fields.subject'))
                    // Doar disciplinele predate în clasa ALEASĂ (profesorul-pur: doar ale lui din acea clasă).
                    ->options(fn (Get $get): array => self::subjectOptions(
                        ($classId = $get('school_class_id')) !== null ? (int) $classId : null,
                    ))
                    ->searchable()
                    ->live()
                    // Obligatorie pentru profesorul-pur; dirigintele/administrația pot lăsa gol (absență pe zi întreagă).
                    ->required(fn (Get $get): bool => self::subjectRequired($get)),
                Select::make('student_id')
                    ->label(__('panel.fields.student'))
                    ->options(fn (Get $get): array => self::studentOptions(
                        ($classId = $get('school_class_id')) !== null ? (int) $classId : null,
                    ))
                    ->searchable()
                    ->required(),
                // Semestrul NU se alege manual: se derivă din `occurred_on` pe server (EnforcesAbsenceScope).
                DatePicker::make('occurred_on')
                    ->label(__('panel.fields.date'))
                    ->required()
                    ->default(now())
                    // O absență nu poate fi în viitor; data determină și semestrul.
                    ->maxDate(now()),
                // Motivarea NU se setează aici (fără un toggle brut, fără dovadă). Se face din lista
                // Absențe → acțiunea „Motivează cu dovadă" (creează un AbsenceMotivation cu justificativ),
                // sau prin fluxul familiei. O singură sursă de adevăr pentru is_motivated.
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
     * Disciplinele selectabile. Când o clasă e aleasă, se restrâng la disciplinele PREDATE în acea
     * clasă (din teaching_assignments): profesorul-pur vede doar disciplinele LUI din clasa aleasă,
     * dirigintele/administrația pe toate cele predate în clasă. Fără nicio clasă aleasă, profesorul-pur
     * vede doar disciplinele lui. Așa dropdown-ul nu mai arată TOATE materiile (chiar și pt. diriginte).
     *
     * @return array<int, string>
     */
    private static function subjectOptions(?int $classId): array
    {
        $query = Subject::query()->orderBy('name');
        $teacher = self::currentTeacher();

        if ($classId !== null) {
            $subjectIds = TeachingAssignment::query()
                ->where('school_class_id', $classId)
                ->pluck('subject_id')
                ->unique();

            if ($teacher !== null && $teacher->homeroomSchoolClassIds() === []) {
                $ownIds = TeachingAssignment::query()
                    ->where('teacher_id', $teacher->id)
                    ->where('school_class_id', $classId)
                    ->pluck('subject_id');
                $subjectIds = $subjectIds->intersect($ownIds);
            }

            $query->whereKey($subjectIds->all());
        } elseif ($teacher !== null && $teacher->homeroomSchoolClassIds() === []) {
            $query->whereKey($teacher->taughtSubjectIds());
        }

        $options = [];
        foreach ($query->get() as $subject) {
            $options[$subject->id] = ContentTranslator::subject($subject->name);
        }

        return $options;
    }

    /**
     * Disciplina e obligatorie pentru profesorul-pur (consemnează absență la lecția LUI). Dirigintele
     * clasei alese și administrația pot lăsa gol = absență pe zi întreagă.
     */
    private static function subjectRequired(Get $get): bool
    {
        $teacher = self::currentTeacher();

        if ($teacher === null) {
            return false; // administrația academică — absență pe zi întreagă permisă
        }

        $classId = ($c = $get('school_class_id')) !== null ? (int) $c : null;

        if ($classId === null) {
            return true; // profesor, fără clasă aleasă → cere disciplina
        }

        return ! in_array($classId, $teacher->homeroomSchoolClassIds(), true);
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
