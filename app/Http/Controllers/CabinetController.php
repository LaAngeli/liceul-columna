<?php

namespace App\Http\Controllers;

use App\Actions\ComputeStudentDynamics;
use App\Actions\DetermineStudentStatus;
use App\Enums\AcademicRecordPeriod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\AcademicRecord;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\ContentTranslator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CabinetController extends Controller
{
    /**
     * Cabinetul personal: copiii (părinte) și/sau propriul profil (elev).
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Personalul folosește exclusiv panoul Filament — niciodată cabinetul Inertia.
        if ($user->hasAnyRole(UserRole::panelRoleValues())) {
            return redirect()->to($user->homePath());
        }

        $children = $user->students()->get()
            ->map(fn (Student $student): array => $this->summary($student))
            ->all();

        $self = Student::query()->where('user_id', $user->id)->first();

        return Inertia::render('dashboard', [
            'cabinet' => [
                'children' => $children,
                'self' => $self ? $this->summary($self) : null,
            ],
        ]);
    }

    /**
     * Profilul unui elev: note, absențe, foaie matricolă și teme. Acces via StudentPolicy.
     */
    public function student(Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load(['grades.subject', 'grades.term', 'absences.subject', 'academicRecords.subject']);

        $viewer = auth()->user();

        return Inertia::render('cabinet/student-profile', [
            'student' => $this->summary($student),
            'subjects' => $this->gradesBySubject($student),
            'absencesBySubject' => $this->absencesBySubject($student),
            'absencesTotal' => $student->absences->count(),
            'absencesMotivated' => $student->absences->where('is_motivated', true)->count(),
            'absencesUnmotivated' => $student->absences->where('is_motivated', false)->count(),
            'transcript' => $this->transcript($student),
            'homework' => $this->homeworkForStudent($student),
            'status' => $this->currentStatus($student),
            'dynamics' => app(ComputeStudentDynamics::class)->for($student),
            'motivations' => $this->motivations($student),
            // Doar familia (tutore/elev) poate depune cereri de motivare — personalul vede pagina,
            // dar nu și formularul (ar primi 403 la trimitere).
            'canRequestMotivation' => $viewer instanceof User && $this->isFamilyOf($viewer, $student),
        ]);
    }

    /**
     * Cerere de motivare a absențelor depusă de familie (§2.1). Doar familia (tutore/elev)
     * poate depune; dirigintele validează ulterior din panou.
     */
    public function requestMotivation(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->isFamilyOf($user, $student), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        AbsenceMotivation::create([
            'student_id' => $student->id,
            'requested_by_user_id' => $user->id,
            'reason' => $data['reason'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ]);

        return back()->with('success', 'Cererea de motivare a fost trimisă dirigintelui.');
    }

    /**
     * Doar familia (tutorele atribuit sau elevul însuși) poate depune/vedea formularul de motivare.
     */
    private function isFamilyOf(User $user, Student $student): bool
    {
        return $user->students()->whereKey($student->id)->exists() || $student->user_id === $user->id;
    }

    /**
     * Cererile de motivare ale elevului (cele mai recente), pentru afișare în cabinet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function motivations(Student $student): array
    {
        return AbsenceMotivation::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (AbsenceMotivation $motivation): array => [
                'id' => $motivation->id,
                'reason' => $motivation->reason,
                'period' => $motivation->period_start->format('d.m.Y').' – '.$motivation->period_end->format('d.m.Y'),
                'status' => $motivation->status->value,
                'statusLabel' => $motivation->status->label(),
            ])
            ->all();
    }

    /**
     * Statutul preliminar al elevului în semestrul curent (§2.5), din mediile calculate.
     *
     * @return array{status: string|null, label: string|null, failingSubjects: array<int, string>}
     */
    private function currentStatus(Student $student): array
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return ['status' => null, 'label' => null, 'failingSubjects' => []];
        }

        $result = app(DetermineStudentStatus::class)->forTerm($student->id, (int) $currentTermId);

        return [
            'status' => $result['status']?->value,
            'label' => $result['status']?->label(),
            'failingSubjects' => array_map(
                fn (string $subject): string => ContentTranslator::subject($subject),
                $result['failingSubjects'],
            ),
        ];
    }

    /**
     * Notele grupate pe disciplină, cu media SEMESTRIALĂ calculată oficial (term_averages),
     * nu o medie aritmetică brută — vezi §2.4 din specificație.
     *
     * @return array<int, array<string, mixed>>
     */
    private function gradesBySubject(Student $student): array
    {
        $averages = $this->semesterAverages($student);

        // Notele anulate (§1) nu apar în cabinet.
        $activeGrades = $student->grades->whereNull('annulled_at');

        $subjects = [];
        foreach ($activeGrades->groupBy(fn (Grade $grade): string => $grade->subject->name) as $name => $items) {
            $subjectId = (int) $items->first()->subject_id;
            $ms = $averages[$subjectId] ?? null;
            $subjects[] = [
                'subject' => ContentTranslator::subject((string) $name),
                'average' => $ms !== null ? (float) $ms : null,
                'items' => $items->map(fn (Grade $grade): array => [
                    'value' => $grade->value,
                    'calificativ' => $grade->calificativ,
                    'date' => $grade->graded_on->format('d.m.Y'),
                    'term' => $grade->term->number,
                ])->all(),
            ];
        }

        return $subjects;
    }

    /**
     * Mediile semestriale calculate (cache term_averages) pentru semestrul curent,
     * indexate pe subject_id.
     *
     * @return Collection<int, string>
     */
    private function semesterAverages(Student $student): Collection
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return collect();
        }

        /** @var Collection<int, string> */
        return TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->pluck('value', 'subject_id');
    }

    /**
     * Absențele numărate pe disciplină (descrescător).
     *
     * @return array<int, array{subject: string, count: int}>
     */
    private function absencesBySubject(Student $student): array
    {
        $absences = [];
        foreach ($student->absences->groupBy(fn (Absence $absence): string => $absence->subject->name) as $name => $items) {
            $absences[] = ['subject' => ContentTranslator::subject((string) $name), 'count' => $items->count()];
        }
        usort($absences, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $absences;
    }

    /**
     * Foaia matricolă: pe fiecare treaptă (descrescător), media pe disciplină
     * la semestrul I, semestrul II și anuală.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transcript(Student $student): array
    {
        $levels = [];
        foreach ($student->academicRecords->groupBy('grade_level') as $gradeLevel => $records) {
            $subjects = [];
            foreach ($records->groupBy(fn (AcademicRecord $record): string => $record->subject->name) as $name => $items) {
                $byPeriod = [];
                foreach ($items as $record) {
                    $byPeriod[$record->period->value] = $record->value !== null
                        ? (string) round((float) $record->value, 2)
                        : ($record->calificativ ?: null);
                }
                $subjects[] = [
                    'subject' => ContentTranslator::subject((string) $name),
                    'sem1' => $byPeriod[AcademicRecordPeriod::SemesterI->value] ?? null,
                    'sem2' => $byPeriod[AcademicRecordPeriod::SemesterII->value] ?? null,
                    'annual' => $byPeriod[AcademicRecordPeriod::Annual->value] ?? null,
                ];
            }
            $levels[] = ['grade_level' => (int) $gradeLevel, 'subjects' => $subjects];
        }
        usort($levels, fn (array $a, array $b): int => $b['grade_level'] <=> $a['grade_level']);

        return $levels;
    }

    /**
     * Temele clasei curente a elevului (după treaptă + literă), cele mai recente întâi.
     *
     * @return array<int, array<string, mixed>>
     */
    private function homeworkForStudent(Student $student): array
    {
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;

        if (! $class) {
            return [];
        }

        return HomeworkAssignment::query()
            ->where('grade_level', $class->grade_level)
            ->where(function (Builder $query) use ($class): void {
                $query->where('section', $class->section)->orWhereNull('section');
            })
            ->orderByDesc('assigned_on')
            ->limit(25)
            ->get()
            ->map(fn (HomeworkAssignment $homework): array => [
                'id' => $homework->id,
                'date' => $homework->assigned_on->format('d.m.Y'),
                'subject' => ContentTranslator::subject((string) $homework->subject_name),
                'topic' => $homework->topic,
                'required' => $homework->required_task,
                'optional' => $homework->optional_task,
                'links' => $homework->links ?? [],
            ])
            ->all();
    }

    /**
     * Rezumat afișabil pentru un elev (card).
     *
     * @return array<string, mixed>
     */
    private function summary(Student $student): array
    {
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;

        // Media generală = media mediilor semestriale calculate (nu a notelor brute).
        $averages = $this->semesterAverages($student);
        $overall = $averages->isNotEmpty()
            ? round($averages->avg(fn (string $value): float => (float) $value), 2)
            : null;

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'class' => $class ? trim($class->name.' '.($class->section ?? '')) : null,
            'grades_count' => $student->grades()->count(),
            'absences_count' => $student->absences()->count(),
            'average' => $overall,
        ];
    }
}
