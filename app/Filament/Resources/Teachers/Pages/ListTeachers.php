<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Registrul corpului didactic = NAVIGARE PRIN ENTITĂȚI (restructurare 2026-07-19, cerința
 * beneficiarului: „utilizatorul navighează prin entități, nu prin liste"): vederi-segmente cu
 * badge-uri (Toți / Diriginți / Fără alocări / Fără cont / Arhivă) + căutare → CARDURI de
 * profesor (nume, funcția reală, disciplinele, contul) → click = FIȘA profesorului în context
 * (identitate, diriginție, alocările desfășurate cu clasele-chips, punți în catalog, editare).
 * Stare în URL validată la citire: ?vedere= & ?profesor= & ?q=.
 */
class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    public const VIEWS = ['toti', 'diriginti', 'fara-alocari', 'fara-cont', 'arhiva'];

    protected string $view = 'filament.catalog.teachers-registry';

    /** Vederea de registru activă (`?vedere=`) — nume distinct de $view (blade-ul paginii). */
    #[Url(as: 'vedere')]
    public string $registryView = 'toti';

    /** Profesorul deschis (id „dorit" din URL, validat la citire). */
    #[Url(as: 'profesor', except: null)]
    public ?string $teacherParam = null;

    /** Căutare live în carduri (nume/prenume). */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * Profesor → clasa/clasele unde e diriginte ÎN ANUL CURENT — memoizat pe instanță.
     * Restrâns la anul curent (nu toate school_classes): după arhivarea unui an, diriginția
     * istorică nu mai e „funcția" persoanei (auditul de fidelitate: diriginte = homeroom REAL).
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $homeroomOfMap = null;

    /** @var array<string, int>|null */
    private ?array $viewCounts = null;

    protected function getHeaderActions(): array
    {
        return [
            // ONBOARDING UNIFICAT: un profesor NOU nu se mai creează ca fișă separată — butonul
            // duce în fluxul de cont (Utilizatori → creare, rolul pre-completat), unde fișa,
            // contul, alocările și diriginția se nasc împreună.
            Action::make('create')
                ->label(__('panel.users_nav.onboard_teacher'))
                ->icon('heroicon-o-plus')
                ->url(UserResource::getUrl('create', ['rol' => UserRole::Profesor->value]))
                ->visible(fn (): bool => auth('web')->user()?->canManageAccounts() ?? false),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Vederi-segmente
    |--------------------------------------------------------------------------
    */

    /** Vederea activă, VALIDATĂ la citire — un `?vedere=` străin cade pe „toți". */
    public function activeView(): string
    {
        return in_array($this->registryView, self::VIEWS, true) ? $this->registryView : 'toti';
    }

    public function openView(string $view): void
    {
        $this->registryView = in_array($view, self::VIEWS, true) ? $view : 'toti';
        $this->teacherParam = null;
    }

    /**
     * Pastilele de vedere cu numărători — „Arhivă" apare doar când există fișe șterse.
     *
     * @return list<array{key: string, label: string, count: int, attention: bool}>
     */
    public function viewPills(): array
    {
        $counts = $this->viewCounts();

        $pills = [];
        foreach (self::VIEWS as $key) {
            if ($key === 'arhiva' && $counts[$key] === 0) {
                continue;
            }

            $pills[] = [
                'key' => $key,
                'label' => (string) __('panel.teachers_registry.views.'.str_replace('-', '_', $key)),
                'count' => $counts[$key],
                'attention' => in_array($key, ['fara-alocari', 'fara-cont'], true) && $counts[$key] > 0,
            ];
        }

        return $pills;
    }

    /** Explicația vederii active, sub pastile (limbajul navigatoarelor). */
    public function registryHint(): string
    {
        return (string) __('panel.teachers_registry.hints.'.str_replace('-', '_', $this->activeView()));
    }

    /*
    |--------------------------------------------------------------------------
    | Cardurile de profesor (aterizarea)
    |--------------------------------------------------------------------------
    */

    /**
     * Cardurile vederii active (+ căutare): identitatea, funcția REALĂ, disciplinele, contul.
     *
     * @return array<int, array{id: int, name: string, homeroom: string|null, subjects: string|null, account: string|null, archived: bool}>
     */
    public function teacherCards(): array
    {
        $teachers = $this->applyRegistryView(Teacher::query())
            ->with(['user:id,name,username', 'teachingAssignments:id,teacher_id,subject_id,school_class_id', 'teachingAssignments.subject:id,name'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('last_name', 'like', $term)
                    ->orWhere('first_name', 'like', $term));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $teachers->map(fn (Teacher $teacher): array => [
            'id' => (int) $teacher->id,
            'name' => trim($teacher->last_name.' '.$teacher->first_name),
            'homeroom' => $this->homeroomOfMap()->get($teacher->id),
            'subjects' => $this->subjectsSummary($teacher),
            'account' => $teacher->user->username ?? $teacher->user->name ?? null,
            'archived' => $teacher->deleted_at !== null,
        ])->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Fișa profesorului (contextul)
    |--------------------------------------------------------------------------
    */

    /** Profesorul activ, VALIDAT la citire (id străin/negăsit → null, cad pe carduri). */
    public function activeTeacher(): ?Teacher
    {
        if ($this->teacherParam === null || ! ctype_digit($this->teacherParam)) {
            return null;
        }

        // withTrashed: fișa din vederea „Arhivă" are și ea context (cu marcaj), nu un 404.
        return Teacher::withTrashed()
            ->with(['user:id,name,username', 'teachingAssignments.subject:id,name', 'teachingAssignments.schoolClass:id,name,section,grade_level'])
            ->find((int) $this->teacherParam);
    }

    public function openTeacher(int|string $id): void
    {
        $this->teacherParam = (string) (int) $id;
    }

    public function leaveTeacher(): void
    {
        $this->teacherParam = null;
    }

    /**
     * Fișa completă pentru context: identitate + funcție + alocările grupate pe disciplină
     * (fiecare clasă = chip-link spre Note pe contextul clasă+disciplină) + punți + editare.
     *
     * @return array<string, mixed>|null
     */
    public function teacherProfile(): ?array
    {
        $teacher = $this->activeTeacher();

        if ($teacher === null) {
            return null;
        }

        $assignments = $teacher->teachingAssignments
            ->filter(fn ($a): bool => $a->subject !== null && $a->schoolClass !== null)
            ->groupBy('subject_id')
            ->map(function ($group) {
                $classes = $group->pluck('schoolClass')->unique('id')
                    ->sortBy([['grade_level', 'asc'], ['section', 'asc']])
                    ->map(fn (SchoolClass $class): array => [
                        'label' => trim($class->name.' '.($class->section ?? '')),
                        'url' => GradeResource::getUrl('index', ['clasa' => $class->id, 'disciplina' => $group->first()->subject_id]),
                    ])->values();

                return [
                    'subject' => ContentTranslator::subject((string) $group->first()->subject->name),
                    'classes' => $classes->all(),
                ];
            })
            ->sortBy('subject')
            ->values();

        $context = ['vedere' => 'profesori', 'profesor' => $teacher->id];

        return [
            'id' => (int) $teacher->id,
            'name' => trim($teacher->last_name.' '.$teacher->first_name),
            'homeroom' => $this->homeroomOfMap()->get($teacher->id),
            'archived' => $teacher->deleted_at !== null,
            'email' => $teacher->email,
            'sex' => $teacher->sex?->getLabel(),
            'account' => $teacher->user->username ?? $teacher->user->name ?? null,
            'assignments' => $assignments->all(),
            'links' => [
                (string) __('panel.resources.grades.label') => GradeResource::getUrl('index', $context),
                (string) __('panel.resources.absences.label') => AbsenceResource::getUrl('index', $context),
                (string) __('panel.resources.homework.label') => HomeworkAssignmentResource::getUrl('index', $context),
            ],
            'editUrl' => (auth('web')->user()?->canConfigureSchool() ?? false)
                ? TeacherResource::getUrl('edit', ['record' => $teacher])
                : null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Interogări + agregate
    |--------------------------------------------------------------------------
    */

    /**
     * Constrângerea vederii active.
     *
     * @param  Builder<Teacher>  $query
     * @return Builder<Teacher>
     */
    public function applyRegistryView(Builder $query): Builder
    {
        return match ($this->activeView()) {
            'diriginti' => $query->whereHas('homeroomClasses', fn (Builder $q) => $q->where('academic_year_id', $this->currentYearId())),
            'fara-alocari' => $query->whereDoesntHave('teachingAssignments'),
            'fara-cont' => $query->whereNull('user_id'),
            'arhiva' => $query->onlyTrashed(),
            default => $query,
        };
    }

    /** @return Collection<int, string> */
    public function homeroomOfMap(): Collection
    {
        return $this->homeroomOfMap ??= SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->where('academic_year_id', $this->currentYearId())
            ->get()
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($classes) => $classes
                ->map(fn ($c) => trim($c->name.' '.($c->section ?? '')))
                ->unique()
                ->sort()
                ->implode(' · '));
    }

    /** Disciplinele cardului: primele 3 nominal + „+N" (desfășurarea completă e în fișă). */
    private function subjectsSummary(Teacher $teacher): ?string
    {
        $subjects = $teacher->teachingAssignments
            ->filter(fn ($a): bool => $a->subject !== null)
            ->pluck('subject.name')
            ->unique()
            ->map(fn (string $name): string => ContentTranslator::subject($name))
            ->sort()
            ->values();

        if ($subjects->isEmpty()) {
            return null;
        }

        $rest = $subjects->count() - 3;

        return $subjects->take(3)->implode(' · ')
            .($rest > 0 ? ' · '.__('panel.teachers_registry.coverage_more', ['count' => $rest]) : '');
    }

    /** @return array<string, int> */
    private function viewCounts(): array
    {
        return $this->viewCounts ??= [
            'toti' => Teacher::query()->count(),
            'diriginti' => Teacher::query()
                ->whereHas('homeroomClasses', fn (Builder $q) => $q->where('academic_year_id', $this->currentYearId()))
                ->count(),
            'fara-alocari' => Teacher::query()->whereDoesntHave('teachingAssignments')->count(),
            'fara-cont' => Teacher::query()->whereNull('user_id')->count(),
            'arhiva' => Teacher::onlyTrashed()->count(),
        ];
    }

    private function currentYearId(): int
    {
        return (int) AcademicYear::query()->where('is_current', true)->value('id');
    }
}
