<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\ContentTranslator;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Discipline" — REFĂCUTĂ (2026-07-15, feedback beneficiar): pentru profesor/diriginte
 * e un navigator cu carduri, ca la Elevi — cardurile disciplinelor PREDATE DE EL; click pe o
 * disciplină → clasele în care EL o predă (filtrarea e pe disciplină ȘI utilizator: doi profesori
 * de chimie își văd fiecare doar clasele proprii), cu sărituri directe în Note / Absențe / Teme
 * pe contextul (clasă, disciplină). Administrația păstrează tabelul de nomenclator + CRUD.
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
}
