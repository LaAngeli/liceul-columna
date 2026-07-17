<?php

namespace App\Actions\Documents;

use App\Actions\DetermineStudentStatus;
use App\Enums\GradingType;
use App\Enums\StaffReportType;
use App\Enums\StudentStatus;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\ContentTranslator;
use App\Support\Grades;
use Illuminate\Support\Collection;

/**
 * Construiește datele pentru un raport GENERAT de staff, din catalog. Scoping-ul de acces e
 * verificat de apelant (pagina Rapoarte) ÎNAINTE de a ajunge aici; datele sunt cele oficiale
 * (mediile semestriale calculate + statutul determinat), nu agregate ad-hoc.
 *
 * Fiecare raport primește ANTETUL instituțional comun (perioada, generat la/de) — documentele
 * ies gata de tipărit/arhivat, nu simple exporturi.
 */
class BuildStaffReportData
{
    /**
     * @return array<string, mixed>
     */
    public function build(StaffReportType $type, ?int $classId, ?int $subjectId): array
    {
        $term = Term::query()->where('is_current', true)->with('academicYear')->first();
        $termId = $term?->getKey() !== null ? (int) $term->getKey() : null;

        $common = [
            'periodLabel' => $term !== null
                ? $term->name.', '.$term->academicYear->name
                : '—',
            'generatedAt' => now()->format('d.m.Y, H:i'),
            'generatedBy' => (string) (auth('web')->user()->name ?? 'Sistem'),
        ];

        if ($type->needsClass()) {
            $class = SchoolClass::findOrFail((int) $classId);
            $common['className'] = trim($class->name.' '.($class->section ?? ''));
        }

        $students = $type->needsClass() ? $this->classStudents((int) $classId) : collect();

        return match ($type) {
            StaffReportType::ClassRoster => [
                ...$common,
                'students' => $students->map(fn (Student $student, int $index): array => [
                    'index' => $index + 1,
                    'name' => $student->full_name,
                    'registerNumber' => $student->register_number,
                ])->all(),
            ],
            StaffReportType::StudentRanking => [
                ...$common,
                'rows' => $this->rankingRows($students, $termId),
            ],
            StaffReportType::ClassSubjectSituation => [
                ...$common,
                'subjectName' => $this->subjectName($subjectId),
                'rows' => $this->subjectRows($students, $subjectId, $termId),
            ],
            StaffReportType::GradeDistribution => [
                ...$common,
                'subjectName' => $this->subjectName($subjectId),
                ...$this->gradeDistribution((int) $classId, (int) $subjectId, $termId),
            ],
            StaffReportType::AveragesEvolution => [
                ...$common,
                'rows' => $this->averagesEvolution($students, $term),
                'termNames' => $this->yearTermNames($term),
            ],
            StaffReportType::SubjectAverages => [
                ...$common,
                'rows' => $this->subjectAverages($students, $termId),
            ],
            StaffReportType::AbsenceStatistics => [
                ...$common,
                ...$this->absenceStatistics($students, $termId),
            ],
            StaffReportType::ClassFullSituation => [
                ...$common,
                'rows' => $this->fullRows($students, $termId),
            ],
            StaffReportType::PromotionRate => [
                ...$common,
                ...$this->promotionRate($students, $termId),
            ],
            StaffReportType::TeacherActivity => [
                ...$common,
                'rows' => $this->teacherActivity($termId),
            ],
            StaffReportType::SchoolOverview => [
                ...$common,
                ...$this->schoolOverview($term),
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
     * Clasamentul clasei: rândurile situației complete, ordonate descrescător după media
     * generală (elevii fără medie — la coadă, nepoziționați).
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array<string, mixed>>
     */
    private function rankingRows(Collection $students, ?int $termId): array
    {
        $rows = collect($this->fullRows($students, $termId));

        [$ranked, $unranked] = $rows->partition(fn (array $row): bool => $row['average'] !== null);

        return $ranked
            ->sortByDesc('average')
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->concat($unranked->map(function (array $row): array {
                $row['rank'] = null;

                return $row;
            }))
            ->values()
            ->all();
    }

    /**
     * Distribuția notelor CURENTE (active) la (clasă, disciplină) în semestrul curent:
     * histograma 1–10 pentru discipline numerice, pe calificative altfel.
     *
     * @return array<string, mixed>
     */
    private function gradeDistribution(int $classId, int $subjectId, ?int $termId): array
    {
        $subject = Subject::find($subjectId);
        $numeric = $subject === null || $subject->grading_type === GradingType::Numeric;

        $grades = Grade::query()
            ->active()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->when($termId !== null, fn ($query) => $query->where('term_id', $termId))
            ->get(['value', 'calificativ']);

        if ($numeric) {
            $buckets = array_fill_keys(range(10, 1), 0);

            foreach ($grades as $grade) {
                if ($grade->value === null) {
                    continue;
                }

                $bucket = (int) round((float) $grade->value);
                $bucket = max(1, min(10, $bucket));
                $buckets[$bucket]++;
            }

            $values = $grades->pluck('value')->filter()->map(fn ($value): float => (float) $value);

            return [
                'numeric' => true,
                'buckets' => $buckets,
                'maxCount' => max(1, ...array_values($buckets)),
                'total' => $values->count(),
                'mean' => $values->isNotEmpty() ? Grades::truncate2((float) $values->avg()) : null,
            ];
        }

        $byCalificativ = $grades
            ->pluck('calificativ')
            ->filter()
            ->countBy()
            ->sortDesc();

        return [
            'numeric' => false,
            'buckets' => $byCalificativ->all(),
            'maxCount' => max(1, (int) ($byCalificativ->max() ?? 1)),
            'total' => (int) $byCalificativ->sum(),
            'mean' => null,
        ];
    }

    /**
     * Evoluția mediilor pe semestrele anului curent, per disciplină (media clasei).
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{subject: string, first: float|null, second: float|null, delta: float|null}>
     */
    private function averagesEvolution(Collection $students, ?Term $currentTerm): array
    {
        if ($currentTerm === null || $students->isEmpty()) {
            return [];
        }

        $terms = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('number')
            ->get();

        $averages = TermAverage::query()
            ->whereIn('term_id', $terms->pluck('id')->all())
            ->whereIn('student_id', $students->pluck('id')->all())
            ->whereNotNull('value')
            ->with('subject')
            ->get();

        $firstTermId = $terms->first()?->getKey();
        $secondTermId = $terms->skip(1)->first()?->getKey();

        return $averages
            ->groupBy(fn (TermAverage $average): string => $average->subject->name)
            ->map(function (Collection $group, string $subjectName) use ($firstTermId, $secondTermId): array {
                $first = $group->where('term_id', $firstTermId)->avg(fn (TermAverage $ta): float => (float) $ta->value);
                $second = $group->where('term_id', $secondTermId)->avg(fn (TermAverage $ta): float => (float) $ta->value);

                return [
                    'subject' => ContentTranslator::subject($subjectName),
                    'first' => $first !== null ? Grades::truncate2((float) $first) : null,
                    'second' => $second !== null ? Grades::truncate2((float) $second) : null,
                    'delta' => ($first !== null && $second !== null)
                        ? round((float) $second - (float) $first, 2)
                        : null,
                ];
            })
            ->sortBy('subject', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Numele semestrelor anului curent (antetele coloanelor de evoluție).
     *
     * @return array{first: string, second: string}
     */
    private function yearTermNames(?Term $currentTerm): array
    {
        if ($currentTerm === null) {
            return ['first' => 'Semestrul I', 'second' => 'Semestrul II'];
        }

        $terms = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('number')
            ->get();

        return [
            'first' => (string) ($terms->first()->name ?? 'Semestrul I'),
            'second' => (string) ($terms->skip(1)->first()->name ?? 'Semestrul II'),
        ];
    }

    /**
     * Situația disciplinelor: media clasei per disciplină (semestrul curent), descrescător —
     * cu procentul pentru barele din raport (raportat la nota maximă 10).
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, array{subject: string, average: float, students: int, percent: int}>
     */
    private function subjectAverages(Collection $students, ?int $termId): array
    {
        if ($termId === null || $students->isEmpty()) {
            return [];
        }

        return TermAverage::query()
            ->where('term_id', $termId)
            ->whereIn('student_id', $students->pluck('id')->all())
            ->whereNotNull('value')
            ->with('subject')
            ->get()
            ->groupBy(fn (TermAverage $average): string => $average->subject->name)
            ->map(function (Collection $group, string $subjectName): array {
                $average = Grades::truncate2((float) $group->avg(fn (TermAverage $ta): float => (float) $ta->value));

                return [
                    'subject' => ContentTranslator::subject($subjectName),
                    'average' => $average,
                    'students' => $group->count(),
                    'percent' => (int) round($average * 10),
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->all();
    }

    /**
     * Statistica absențelor clasei în semestrul curent: per elev (total/motivate/nemotivate) +
     * totalurile clasei + defalcarea pe luni (pentru barele lunare).
     *
     * @param  Collection<int, Student>  $students
     * @return array<string, mixed>
     */
    private function absenceStatistics(Collection $students, ?int $termId): array
    {
        $absences = ($termId !== null && $students->isNotEmpty())
            ? Absence::query()
                ->where('term_id', $termId)
                ->whereIn('student_id', $students->pluck('id')->all())
                ->get(['student_id', 'is_motivated', 'occurred_on'])
            : collect();

        $byStudent = $absences->groupBy('student_id');

        $rows = $students->map(function (Student $student, int $index) use ($byStudent): array {
            /** @var Collection<int, Absence> $own */
            $own = $byStudent->get($student->id, collect());
            $motivated = $own->where('is_motivated', true)->count();

            return [
                'index' => $index + 1,
                'name' => $student->full_name,
                'total' => $own->count(),
                'motivated' => $motivated,
                'unmotivated' => $own->count() - $motivated,
            ];
        })->all();

        $monthly = $absences
            ->groupBy(fn (Absence $absence): string => $absence->occurred_on->format('Y-m'))
            ->sortKeys()
            ->mapWithKeys(fn (Collection $group, string $key): array => [
                (string) $group->first()?->occurred_on->translatedFormat('F Y') => $group->count(),
            ])
            ->all();

        $motivatedTotal = $absences->where('is_motivated', true)->count();

        return [
            'rows' => $rows,
            'totals' => [
                'total' => $absences->count(),
                'motivated' => $motivatedTotal,
                'unmotivated' => $absences->count() - $motivatedTotal,
            ],
            'monthly' => $monthly,
            'monthlyMax' => max(1, ...(array_values($monthly) ?: [1])),
        ];
    }

    /**
     * Promovabilitatea clasei (semestrul curent): promovați / corigenți / amânați + disciplinele
     * cu cele mai multe restanțe.
     *
     * @param  Collection<int, Student>  $students
     * @return array<string, mixed>
     */
    private function promotionRate(Collection $students, ?int $termId): array
    {
        $statusAction = app(DetermineStudentStatus::class);

        $counts = [];
        foreach (StudentStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }
        $failingFrequency = [];

        foreach ($students as $student) {
            if ($termId === null) {
                continue;
            }

            $result = $statusAction->forTerm($student->id, $termId);
            $status = $result['status'];

            if ($status !== null) {
                $counts[$status->value]++;
            }

            foreach ($result['failingSubjects'] as $subjectName) {
                $label = ContentTranslator::subject($subjectName);
                $failingFrequency[$label] = ($failingFrequency[$label] ?? 0) + 1;
            }
        }

        $evaluated = array_sum($counts);
        arsort($failingFrequency);

        return [
            'statusCounts' => $counts,
            'evaluated' => $evaluated,
            'studentsTotal' => $students->count(),
            'promotionPercent' => $evaluated > 0
                ? (int) round($counts[StudentStatus::Promovat->value] * 100 / $evaluated)
                : null,
            'failingSubjects' => array_slice($failingFrequency, 0, 10, true),
            'failingMax' => max(1, ...(array_values($failingFrequency) ?: [1])),
        ];
    }

    /**
     * Activitatea profesorilor în semestrul curent (administrație): note consemnate, absențe
     * consemnate, alocări (clase × discipline) și diriginția — pe fiecare fișă de profesor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function teacherActivity(?int $termId): array
    {
        $teachers = Teacher::query()
            ->withCount(['teachingAssignments as assignments_count'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $gradeCounts = $termId !== null
            ? Grade::query()
                ->toBase()
                ->selectRaw('teacher_id, count(*) as total')
                ->where('term_id', $termId)
                ->whereNull('annulled_at')
                ->whereNotNull('teacher_id')
                ->groupBy('teacher_id')
                ->pluck('total', 'teacher_id')
            : collect();

        $absenceCounts = $termId !== null
            ? Absence::query()
                ->toBase()
                ->selectRaw('teacher_id, count(*) as total')
                ->where('term_id', $termId)
                ->whereNotNull('teacher_id')
                ->groupBy('teacher_id')
                ->pluck('total', 'teacher_id')
            : collect();

        $homerooms = SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->get()
            ->groupBy('homeroom_teacher_id')
            ->map(fn (Collection $classes): string => $classes
                ->map(fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))
                ->unique()
                ->sort()
                ->implode(', '));

        return $teachers->map(fn (Teacher $teacher, int $index): array => [
            'index' => $index + 1,
            'name' => (string) $teacher->full_name,
            'position' => $teacher->position,
            'assignments' => (int) $teacher->getAttribute('assignments_count'),
            'grades' => (int) ($gradeCounts[$teacher->id] ?? 0),
            'absences' => (int) ($absenceCounts[$teacher->id] ?? 0),
            'homeroom' => $homerooms->get($teacher->id),
        ])->all();
    }

    /**
     * Sinteza școlii pe clasele anului curent: elevi activi, media clasei, corigenți (medii < 5)
     * — imaginea managerială dintr-o privire, cu barele mediilor.
     *
     * @return array<string, mixed>
     */
    private function schoolOverview(?Term $currentTerm): array
    {
        if ($currentTerm === null) {
            return ['rows' => [], 'totals' => ['students' => 0, 'failing' => 0]];
        }

        $classes = SchoolClass::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        $termId = (int) $currentTerm->getKey();

        $rows = [];
        $studentsTotal = 0;
        $failingTotal = 0;

        foreach ($classes as $class) {
            $studentIds = Enrollment::query()
                ->where('school_class_id', $class->id)
                ->whereNull('left_on')
                ->pluck('student_id');

            $averages = TermAverage::query()
                ->where('term_id', $termId)
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('value');

            $classAverage = (clone $averages)->avg('value');
            // Corigent = cel puțin o medie semestrială sub 5 (aceeași regulă ca statutul §2.5).
            $failing = (clone $averages)->where('value', '<', 5)->distinct()->count('student_id');

            $studentsTotal += $studentIds->count();
            $failingTotal += $failing;

            $rows[] = [
                'class' => trim($class->name.' '.($class->section ?? '')),
                'students' => $studentIds->count(),
                'average' => $classAverage !== null ? Grades::truncate2((float) $classAverage) : null,
                'percent' => $classAverage !== null ? (int) round((float) $classAverage * 10) : 0,
                'failing' => $failing,
            ];
        }

        return [
            'rows' => $rows,
            'totals' => ['students' => $studentsTotal, 'failing' => $failingTotal],
        ];
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
