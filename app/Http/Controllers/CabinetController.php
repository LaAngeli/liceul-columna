<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Profilul unui elev (note pe discipline + absențe). Acces controlat de StudentPolicy.
     */
    public function student(Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load(['grades.subject', 'grades.term', 'absences.subject']);

        $subjects = [];
        foreach ($student->grades->groupBy(fn (Grade $grade): string => $grade->subject->name) as $name => $items) {
            $numeric = $items->whereNotNull('value');
            $subjects[] = [
                'subject' => $name,
                'average' => $numeric->count() ? round((float) $numeric->avg('value'), 2) : null,
                'items' => $items->map(fn (Grade $grade): array => [
                    'value' => $grade->value,
                    'calificativ' => $grade->calificativ,
                    'date' => $grade->graded_on->format('d.m.Y'),
                    'term' => $grade->term->number,
                ])->all(),
            ];
        }

        $absences = [];
        foreach ($student->absences->groupBy(fn (Absence $absence): string => $absence->subject->name) as $name => $items) {
            $absences[] = ['subject' => (string) $name, 'count' => $items->count()];
        }
        usort($absences, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return Inertia::render('cabinet/student-profile', [
            'student' => $this->summary($student),
            'subjects' => $subjects,
            'absencesBySubject' => $absences,
            'absencesTotal' => $student->absences->count(),
        ]);
    }

    /**
     * Rezumat afișabil pentru un elev (card).
     *
     * @return array<string, mixed>
     */
    private function summary(Student $student): array
    {
        $class = $student->enrollments()->with('schoolClass')->latest('id')->first()?->schoolClass;
        $numericAvg = $student->grades()->whereNotNull('value')->avg('value');

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'class' => $class ? trim($class->name.' '.($class->section ?? '')) : null,
            'grades_count' => $student->grades()->count(),
            'absences_count' => $student->absences()->count(),
            'average' => $numericAvg !== null ? round((float) $numericAvg, 2) : null,
        ];
    }
}
