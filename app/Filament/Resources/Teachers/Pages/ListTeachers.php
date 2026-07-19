<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Registrul corpului didactic, pe VEDERI-segmente (restructurare 2026-07-19, pe tiparul
 * navigatoarelor din panou): Toți / Diriginți / Fără alocări / Fără cont / Arhivă — stare în URL
 * validată la citire, badge-uri numărate o dată per cerere. Vederile operaționale (fără alocări /
 * fără cont) fac vizibile fișele care cer acțiune — până acum se pierdeau în lista plată.
 */
class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    public const VIEWS = ['toti', 'diriginti', 'fara-alocari', 'fara-cont', 'arhiva'];

    protected string $view = 'filament.catalog.teachers-registry';

    /** Vederea de registru activă (`?vedere=`) — nume distinct de $view (blade-ul paginii). */
    #[Url(as: 'vedere')]
    public string $registryView = 'toti';

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
            // contul, alocările și diriginția se nasc împreună. Numele acțiunii rămâne „create"
            // (limbajul paginilor de listă); vizibilă doar cui poate crea conturi.
            Action::make('create')
                ->label(__('panel.users_nav.onboard_teacher'))
                ->icon('heroicon-o-plus')
                ->url(UserResource::getUrl('create', ['rol' => UserRole::Profesor->value]))
                ->visible(fn (): bool => auth('web')->user()?->canManageAccounts() ?? false),
        ];
    }

    /** Vederea activă, VALIDATĂ la citire — un `?vedere=` străin cade pe „toți". */
    public function activeView(): string
    {
        return in_array($this->registryView, self::VIEWS, true) ? $this->registryView : 'toti';
    }

    public function openView(string $view): void
    {
        $this->registryView = in_array($view, self::VIEWS, true) ? $view : 'toti';
        $this->resetTable();
    }

    /**
     * Constrângerea vederii active peste query-ul tabelului (chemată din TeachersTable prin
     * modifyQueryUsing — tabelul se randează doar pe această pagină).
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
                // Vederile operaționale semnalează vizual când au ceva de rezolvat.
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
