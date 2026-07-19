<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Enums\SchoolCycle;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\ContentTranslator;
use App\Support\GradeLevels;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Discipline" — navigare PRIN ENTITĂȚI pentru toți (restructurare 2026-07-19, cerința
 * beneficiarului — vederea admin era „prea liniară"):
 *  - profesor/diriginte: cardurile disciplinelor PREDATE DE EL → clasele în care EL le predă
 *    (2026-07-15; neschimbat — filtrarea e pe disciplină ȘI utilizator);
 *  - administrația: cardurile NOMENCLATORULUI (abreviere, tip notare, trepte, acoperire) →
 *    contextul disciplinei: profesorii care o predau (cu clasele fiecăruia), clasele-chips,
 *    punți în catalog pe ?disciplina= și editarea (configuratori).
 */
class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected string $view = 'filament.catalog.subjects-navigator';

    /** Disciplina deschisă (id „dorit" din URL, validat la citire prin alocările proprii). */
    #[Url(as: 'disciplina', except: null)]
    public ?string $subjectParam = null;

    /** @var Collection<int, TeachingAssignment>|null alocările proprii (memoizate pe instanță) */
    private ?Collection $ownAssignments = null;

    protected function getHeaderActions(): array
    {
        return [
            // Nomenclatorul se administrează doar de configuratori (canCreate pe resursă).
            CreateAction::make(),
        ];
    }

    /** Cadrele didactice primesc navigatorul cu carduri; administrația — tabelul. */
    public function isTeacherView(): bool
    {
        return $this->viewerTeacher() !== null;
    }

    /** Disciplina activă, validată STRICT prin alocările proprii (id străin → null). */
    public function activeSubject(): ?Subject
    {
        if ($this->subjectParam === null || ! ctype_digit($this->subjectParam)) {
            return null;
        }

        $id = (int) $this->subjectParam;

        return $this->ownAssignments()->firstWhere('subject_id', $id)?->subject;
    }

    public function openSubject(int|string $id): void
    {
        $this->subjectParam = (string) (int) $id;
    }

    public function leaveSubject(): void
    {
        $this->subjectParam = null;
    }

    /**
     * Cardurile disciplinelor MELE: nume tradus + câte clase / câți elevi acoperă (la mine).
     *
     * @return array<int, array{id: int, title: string, stats: array<int, string>}>
     */
    public function subjectCards(): array
    {
        $enrollments = $this->enrollmentCountsByClass();

        $cards = [];

        foreach ($this->ownAssignments()->groupBy('subject_id') as $subjectId => $assignments) {
            $subject = $assignments->first()?->subject;

            if ($subject === null) {
                continue;
            }

            $classIds = $assignments->pluck('school_class_id')->unique();
            $students = $classIds->sum(fn ($classId) => (int) ($enrollments->get($classId)->aggregate ?? 0));

            $cards[] = [
                'id' => (int) $subjectId,
                'title' => ContentTranslator::subject($subject->name),
                'stats' => [
                    (string) trans_choice('panel.catalog_nav.classes', $classIds->count(), ['count' => $classIds->count()]),
                    (string) trans_choice('panel.catalog_nav.students', $students, ['count' => $students]),
                ],
            ];
        }

        usort($cards, fn (array $a, array $b): int => strcoll($a['title'], $b['title']));

        return $cards;
    }

    /**
     * Clasele în care EU predau disciplina activă — cu diriginte, elevi și sărituri directe în
     * Note / Absențe / Teme pe contextul (clasă, disciplină).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, students: string, links: array<string, string>}>
     */
    public function classCards(): array
    {
        $subject = $this->activeSubject();

        if ($subject === null) {
            return [];
        }

        $enrollments = $this->enrollmentCountsByClass();

        $cards = [];

        $classes = $this->ownAssignments()
            ->where('subject_id', $subject->id)
            ->map(fn ($assignment) => $assignment->schoolClass)
            ->filter()
            ->unique('id')
            ->sortBy([['grade_level', 'asc'], ['name', 'asc'], ['section', 'asc']]);

        foreach ($classes as $class) {
            $students = (int) ($enrollments->get($class->id)->aggregate ?? 0);
            $context = ['clasa' => $class->id, 'disciplina' => $subject->id];

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'students' => (string) trans_choice('panel.catalog_nav.students', $students, ['count' => $students]),
                'links' => [
                    (string) __('panel.resources.grades.label') => GradeResource::getUrl('index', $context),
                    (string) __('panel.resources.absences.label') => AbsenceResource::getUrl('index', $context),
                    (string) __('panel.resources.homework.label') => HomeworkAssignmentResource::getUrl('index', $context),
                ],
            ];
        }

        return $cards;
    }

    /** @return Collection<int, TeachingAssignment> */
    private function ownAssignments(): Collection
    {
        return $this->ownAssignments ??= ($teacher = $this->viewerTeacher()) === null
            ? collect()
            : TeachingAssignment::query()
                ->with(['subject', 'schoolClass.homeroomTeacher'])
                ->where('teacher_id', $teacher->id)
                ->get()
                ->values();
    }

    /** @return Collection<int|string, \stdClass> nr. de elevi per clasă (o interogare) */
    private function enrollmentCountsByClass(): Collection
    {
        return Enrollment::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');
    }

    private function viewerTeacher(): ?Teacher
    {
        $user = auth('web')->user();

        return ($user && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Vederea ADMINISTRAȚIEI: nomenclatorul pe carduri + contextul disciplinei
    |--------------------------------------------------------------------------
    */

    /** Disciplina activă pentru ADMIN — validată pe întreg nomenclatorul activ. */
    public function adminActiveSubject(): ?Subject
    {
        if ($this->subjectParam === null || ! ctype_digit($this->subjectParam)) {
            return null;
        }

        return Subject::query()->find((int) $this->subjectParam);
    }

    /**
     * Cardurile nomenclatorului: nume tradus + abreviere, tip de notare, treptele (romane) și
     * acoperirea instituțională — două subquery-uri, fără N+1.
     *
     * @return array<int, array{id: int, title: string, abbreviation: string|null, grading: string, grades: string|null, cycles: string|null, coverage: string}>
     */
    public function adminSubjectCards(): array
    {
        $subjects = Subject::query()
            ->addSelect([
                'classes_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT school_class_id)')
                    ->whereColumn('subject_id', 'subjects.id'),
                'teachers_count' => TeachingAssignment::query()
                    ->selectRaw('COUNT(DISTINCT teacher_id)')
                    ->whereColumn('subject_id', 'subjects.id'),
            ])
            ->orderBy('name')
            ->get();

        return $subjects->map(fn (Subject $subject): array => [
            'id' => (int) $subject->id,
            'title' => ContentTranslator::subject($subject->name),
            'abbreviation' => $subject->abbreviation,
            'grading' => (string) $subject->grading_type->getLabel(),
            'grades' => ($subject->min_grade !== null && $subject->max_grade !== null)
                ? GradeLevels::span((int) $subject->min_grade, (int) $subject->max_grade)
                : null,
            'cycles' => self::cycleSpan($subject),
            'coverage' => (string) __('panel.tables.subjects.coverage_value', [
                'classes' => (int) $subject->getAttribute('classes_count'),
                'teachers' => (int) $subject->getAttribute('teachers_count'),
            ]),
        ])->all();
    }

    /**
     * Contextul disciplinei (admin): profesorii care o predau — fiecare cu clasele lui la această
     * disciplină (chip = catalogul pe context clasă+disciplină, ca la cadre) — + punți + editare.
     *
     * @return array<string, mixed>|null
     */
    public function adminSubjectContext(): ?array
    {
        $subject = $this->adminActiveSubject();

        if ($subject === null) {
            return null;
        }

        $assignments = TeachingAssignment::query()
            ->with(['teacher:id,last_name,first_name', 'schoolClass:id,name,section,grade_level'])
            ->where('subject_id', $subject->id)
            ->get()
            ->filter(fn (TeachingAssignment $a): bool => $a->teacher !== null && $a->schoolClass !== null);

        $teachers = $assignments
            ->groupBy('teacher_id')
            ->map(function (Collection $group) use ($subject): array {
                $teacher = $group->first()->teacher;
                $classes = $group->pluck('schoolClass')->unique('id')
                    ->sortBy([['grade_level', 'asc'], ['section', 'asc']])
                    ->map(fn ($class): array => [
                        'label' => trim($class->name.' '.($class->section ?? '')),
                        'url' => GradeResource::getUrl('index', ['clasa' => $class->id, 'disciplina' => $subject->id]),
                    ])->values();

                return [
                    'name' => trim($teacher->last_name.' '.$teacher->first_name),
                    // Puntea spre fișa profesorului din registrul „Profesori" (admin-only, ca și
                    // această vedere — cele două secțiuni se leagă natural).
                    'url' => TeacherResource::getUrl('index', ['profesor' => $teacher->id]),
                    'classes' => $classes->all(),
                ];
            })
            ->sortBy('name')
            ->values();

        $context = ['vedere' => 'discipline', 'disciplina' => $subject->id];

        return [
            'id' => (int) $subject->id,
            'title' => ContentTranslator::subject($subject->name),
            'abbreviation' => $subject->abbreviation,
            'grading' => (string) $subject->grading_type->getLabel(),
            'grades' => ($subject->min_grade !== null && $subject->max_grade !== null)
                ? GradeLevels::span((int) $subject->min_grade, (int) $subject->max_grade)
                : null,
            'cycles' => self::cycleSpan($subject),
            'teachers' => $teachers->all(),
            'links' => [
                (string) __('panel.resources.grades.label') => GradeResource::getUrl('index', $context),
                (string) __('panel.resources.absences.label') => AbsenceResource::getUrl('index', $context),
                (string) __('panel.resources.homework.label') => HomeworkAssignmentResource::getUrl('index', $context),
            ],
            'editUrl' => (auth('web')->user()?->canConfigureSchool() ?? false)
                ? SubjectResource::getUrl('edit', ['record' => $subject])
                : null,
        ];
    }

    /** Ciclul/ciclurile acoperite („Primar–Liceu"), ca sub-text lămuritor. */
    private static function cycleSpan(Subject $subject): ?string
    {
        if ($subject->min_grade === null || $subject->max_grade === null) {
            return null;
        }

        $from = SchoolCycle::fromGradeLevel((int) $subject->min_grade)->label();
        $to = SchoolCycle::fromGradeLevel((int) $subject->max_grade)->label();

        return $from === $to ? $from : $from.'–'.$to;
    }
}
