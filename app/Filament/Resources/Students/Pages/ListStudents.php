<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Students\StudentResource;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Pagina „Elevi" folosește navigatorul drill-down ADAPTAT: elevul nu are o clasă-coloană, ci
 * ÎNMATRICULĂRI (pe ani) — deci singura dimensiune e „Clase" (cardurile claselor → elevii lor),
 * iar constrângerea trece prin enrollments. Administrația păstrează accesul la TOT registrul
 * (elevi plecați / fără clasă curentă) prin vederea explicită „Arhivă".
 */
class ListStudents extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator {
        hasCatalogContext as baseHasCatalogContext;
        applyCatalogContext as baseApplyCatalogContext;
        catalogContextTitle as baseCatalogContextTitle;
        catalogContextSubtitle as baseCatalogContextSubtitle;
        catalogSiblingOptions as baseCatalogSiblingOptions;
        leaveCatalogContext as baseLeaveCatalogContext;
    }

    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    /** Vederea „toți elevii" (arhiva) — doar administrația; flag explicit în URL. */
    #[Url(as: 'arhiva', except: null)]
    public ?string $archiveMode = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('panel.catalog_nav.students_archive'))
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->visible(fn (): bool => (auth('web')->user()?->isAdministrator() ?? false) && ! $this->isArchiveMode())
                ->action(function (): void {
                    $this->archiveMode = '1';
                    $this->catalogNavMemo = [];
                    $this->resetCatalogPagination();
                }),
            CreateAction::make(),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return StudentResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        return StudentResource::getEloquentQuery();
    }

    protected function catalogDateColumn(): string
    {
        // Nefolosit la elevi (cardurile de clasă se construiesc din înmatriculări, nu din agregate
        // pe dată) — cerut doar de contractul trait-ului.
        return 'created_at';
    }

    /**
     * Elevii se navighează DOAR pe clase (disciplina/profesorul/perioada nu au sens aici).
     *
     * @return array<int, string>
     */
    protected function catalogDimensionKeys(): array
    {
        return ['clase'];
    }

    public function catalogHint(): string
    {
        return (string) __('panel.catalog_nav.students_hint');
    }

    /**
     * Clasa elevului = înmatricularea lui — constrângerea trece prin enrollments.
     *
     * @param  Builder<Model>  $query
     */
    protected function constrainToClass(Builder $query, ?SchoolClass $class): void
    {
        if ($class !== null) {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('school_class_id', $class->getKey()));
        }
    }

    /** Fără dimensiunile disciplină / profesor / semestru la elevi — parametrii din URL se ignoră. */
    protected function resolvedSubject(): ?Subject
    {
        return null;
    }

    protected function resolvedTeacher(): ?Teacher
    {
        return null;
    }

    protected function resolvedTerm(): ?Term
    {
        return null;
    }

    /**
     * Cardurile claselor: elevii înmatriculați + dirigintele + badge „Clasa mea" — fără agregate
     * pe dată (un elev nu are „ultima înregistrare" relevantă aici).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, badge: string|null, stats: array<int, string>}>
     */
    protected function classCards(): array
    {
        $enrollments = $this->enrollmentCounts();

        $cards = [];

        foreach ($this->navigatorClasses() as $class) {
            $students = $enrollments->get($class->id);
            $count = $students !== null ? (int) $students->aggregate : 0;

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'badge' => $this->isOwnHomeroomClass((int) $class->id)
                    ? (string) __('panel.catalog_nav.homeroom')
                    : null,
                'stats' => [
                    (string) trans_choice('panel.catalog_nav.students', $count, ['count' => $count]),
                ],
            ];
        }

        return $cards;
    }

    /**
     * Fără chips în contextul unei clase de elevi (nu există sub-dimensiune utilă).
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function catalogChips(): array
    {
        return [];
    }

    // ── Vederea „Arhivă" (toți elevii) — doar administrația ────────────────────────────────

    protected function isArchiveMode(): bool
    {
        return $this->archiveMode === '1' && (auth('web')->user()?->isAdministrator() ?? false);
    }

    public function hasCatalogContext(): bool
    {
        return $this->isArchiveMode() || $this->baseHasCatalogContext();
    }

    public function applyCatalogContext(Builder $query): Builder
    {
        // Arhiva = registrul complet, nescoped suplimentar (interogarea resursei rămâne sursa).
        return $this->isArchiveMode() ? $query : $this->baseApplyCatalogContext($query);
    }

    public function catalogContextTitle(): string
    {
        return $this->isArchiveMode()
            ? (string) __('panel.catalog_nav.students_archive')
            : $this->baseCatalogContextTitle();
    }

    public function catalogContextSubtitle(): ?string
    {
        return $this->isArchiveMode() ? null : $this->baseCatalogContextSubtitle();
    }

    /** @return array<int|string, string> */
    public function catalogSiblingOptions(): array
    {
        return $this->isArchiveMode() ? [] : $this->baseCatalogSiblingOptions();
    }

    public function leaveCatalogContext(): void
    {
        $this->archiveMode = null;

        $this->baseLeaveCatalogContext();
    }
}
