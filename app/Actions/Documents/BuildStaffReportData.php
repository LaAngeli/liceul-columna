<?php

namespace App\Actions\Documents;

use App\Actions\DetermineStudentStatus;
use App\Enums\StaffReportType;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\ContentTranslator;
use App\Support\Grades;
use Illuminate\Support\Collection;

/**
 * Construiește datele pentru un raport GENERAT de staff (per-clasă), din catalog. Scoping-ul de acces
 * e verificat de apelant (pagina Rapoarte) ÎNAINTE de a ajunge aici; datele sunt cele oficiale
 * (mediile semestriale calculate + statutul determinat), nu agregate ad-hoc.
 */
class BuildStaffReportData
{
    /**
     * @return array<string, mixed>
     */
    public function build(StaffReportType $type, int $classId, ?int $subjectId): array
    {
        $class = SchoolClass::findOrFail($classId);
        $students = $this->classStudents($classId);
        $termId = Term::query()->where('is_current', true)->value('id');
        $termId = $termId !== null ? (int) $termId : null;

        $common = [
            'className' => trim($class->name.' '.($class->section ?? '')),
            'date' => now()->format('d.m.Y'),
        ];

        return match ($type) {
            StaffReportType::ClassRoster => [
                ...$common,
                'students' => $students->map(fn (Student $student, int $index): array => [
                    'index' => $index + 1,
                    'name' => $student->full_name,
                    'registerNumber' => $student->register_number,
                ])->all(),
            ],
            StaffReportType::ClassSubjectSituation => [
                ...$common,
                'subjectName' => $this->subjectName($subjectId),
                'rows' => $this->subjectRows($students, $subjectId, $termId),
            ],
            StaffReportType::ClassFullSituation => [
                ...$common,
                'rows' => $this->fullRows($students, $termId),
            ],
        };
    }

    /**
     * Elevii activi ai clasei (fără cei plecați), ordonați alfabetic.
     *
     * @return Collection<int, Student>
     */
    private function classStudents(int $classId): Collection
    {
        return Enrollment::query()
            ->where('school_class_id', $classId)
            ->whereNull('left_on')
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy(fn (Student $student): string => $student->last_name.' '.$student->first_name)
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array{index: int, name: string, average: float|null}>
     */
    private function subjectRows(Collection $students, ?int $subjectId, ?int $termId): array
    {
        $averages = ($subjectId !== null && $termId !== null)
            ? TermAverage::query()
                ->where('term_id', $termId)
                ->where('subject_id', $subjectId)
                ->whereIn('student_id', $students->pluck('id')->all())
                ->get()
                ->keyBy('student_id')
            : collect();

        return $students->map(function (Student $student, int $index) use ($averages): array {
            $average = $averages->get($student->id);

            return [
                'index' => $index + 1,
                'name' => $student->full_name,
                'average' => $average !== null && $average->value !== null ? (float) $average->value : null,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array{index: int, name: string, average: float|null, statusLabel: string|null, failing: string}>
     */
    private function fullRows(Collection $students, ?int $termId): array
    {
        $byStudent = $termId !== null
            ? TermAverage::query()
                ->where('term_id', $termId)
                ->whereIn('student_id', $students->pluck('id')->all())
                ->get()
                ->groupBy('student_id')
            : collect();

        $statusAction = app(DetermineStudentStatus::class);

        return $students->map(function (Student $student, int $index) use ($byStudent, $statusAction, $termId): array {
            /** @var Collection<int, TermAverage> $avgs */
            $avgs = $byStudent->get($student->id, collect());
            $overall = $avgs->isNotEmpty()
                ? Grades::truncate2((float) $avgs->avg(fn (TermAverage $ta): float => (float) $ta->value))
                : null;

            $status = $termId !== null ? $statusAction->forTerm($student->id, $termId) : ['status' => null, 'failingSubjects' => []];
            $failing = array_map(
                fn (string $subject): string => ContentTranslator::subject($subject),
                $status['failingSubjects'],
            );

            return [
                'index' => $index + 1,
                'name' => $student->full_name,
                'average' => $overall,
                'statusLabel' => $status['status']?->label(),
                'failing' => implode(', ', $failing),
            ];
        })->all();
    }

    private function subjectName(?int $subjectId): string
    {
        if ($subjectId === null) {
            return '';
        }

        $subject = Subject::find($subjectId);

        return $subject !== null ? ContentTranslator::subject($subject->name) : '';
    }
}
