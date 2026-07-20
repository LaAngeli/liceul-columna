<?php

namespace App\Filament\Resources\AcademicYears\Pages;

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Filament\Resources\Terms\TermResource;
use App\Jobs\ArchiveYearJob;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Anii școlari = HUB-ul configurării (2026-07-16): un card per an — badge „An curent",
 * conținutul lui (semestre / clase / înmatriculări) cu sărituri directe în secțiunile
 * respective (pre-filtrate pe an) — plus operațiunile anului: Editare și „Arhivează în
 * matricolă" (pe queue, {@see ArchiveYearJob}). Tabelul nu se mai randează: 3-5 ani nu
 * sunt o listă, sunt niște hub-uri.
 */
class ListAcademicYears extends ListRecords
{
    protected static string $resource = AcademicYearResource::class;

    protected string $view = 'filament.catalog.academic-years-hub';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Acțiunea „Arhivează în matricolă" — pe PAGINĂ (cardurile o montează cu argumentul anului);
     * păstrează confirmarea + textele existente și pleacă pe queue, ca înainte.
     */
    public function archiveYearAction(): Action
    {
        return Action::make('archiveYear')
            ->label(__('panel.actions.archive_year.label'))
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('panel.actions.archive_year.heading', [
                'year' => AcademicYear::query()->whereKey((int) ($arguments['year'] ?? 0))->value('name') ?? '',
            ]))
            ->modalDescription(fn (): string => __('panel.actions.archive_year.description'))
            ->modalSubmitActionLabel(__('panel.actions.archive_year.submit'))
            ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool())
            ->action(function (array $arguments): void {
                $year = AcademicYear::query()->whereKey((int) ($arguments['year'] ?? 0))->first();

                if ($year === null) {
                    return;
                }

                ArchiveYearJob::dispatch($year, (int) auth('web')->id());

                Notification::make()
                    ->info()
                    ->title(__('panel.actions.archive_year.queued', ['year' => $year->name]))
                    ->body(__('panel.actions.archive_year.queued_body'))
                    ->send();
            });
    }

    /**
     * Cardurile anilor (cei mai noi întâi): badge „An curent" + semestre/clase/înmatriculări
     * + sărituri pre-filtrate + Editare.
     *
     * @return array<int, array{id: int, title: string, period: string|null, current: bool, closed: bool, closed_on: string|null, stats: array<int, string>, links: array<string, string>, edit_url: string|null, can_archive: bool}>
     */
    public function yearCards(): array
    {
        $years = AcademicYear::query()->orderByDesc('id')->get();

        if ($years->isEmpty()) {
            return [];
        }

        $termCounts = $this->countsFor(Term::query()->toBase());
        $classCounts = $this->countsFor(SchoolClass::query()->toBase());
        $enrollmentCounts = $this->countsFor(Enrollment::query()->toBase());

        $currentYearId = Term::query()->where('is_current', true)->value('academic_year_id');
        $user = auth('web')->user();
        $canConfigure = $user instanceof User && $user->canConfigureSchool();

        $cards = [];

        foreach ($years as $year) {
            $cards[] = [
                'id' => (int) $year->id,
                'title' => (string) $year->name,
                'period' => $year->starts_on !== null && $year->ends_on !== null
                    ? Carbon::parse($year->starts_on)->format('d.m.Y').' – '.Carbon::parse($year->ends_on)->format('d.m.Y')
                    : null,
                'current' => $currentYearId !== null && (int) $year->id === (int) $currentYearId,
                // Regimul anului, nu doar un detaliu: într-un an închis catalogul nu mai primește
                // note. Fără semnalul de aici, refuzul de la salvare ar părea o defecțiune.
                'closed' => $year->isClosed(),
                'closed_on' => $year->closed_at?->format('d.m.Y'),
                'stats' => [
                    (string) trans_choice('panel.config_nav.terms', (int) ($termCounts->get($year->id) ?? 0), ['count' => (int) ($termCounts->get($year->id) ?? 0)]),
                    (string) trans_choice('panel.catalog_nav.classes', (int) ($classCounts->get($year->id) ?? 0), ['count' => (int) ($classCounts->get($year->id) ?? 0)]),
                    (string) trans_choice('panel.config_nav.enrollments', (int) ($enrollmentCounts->get($year->id) ?? 0), ['count' => (int) ($enrollmentCounts->get($year->id) ?? 0)]),
                ],
                'links' => [
                    (string) __('panel.resources.terms.label') => TermResource::getUrl('index', ['an' => $year->id]),
                    (string) __('panel.resources.school_classes.label') => SchoolClassResource::getUrl('index', ['an' => $year->id]),
                    (string) __('panel.resources.enrollments.label') => EnrollmentResource::getUrl('index', ['an' => $year->id]),
                ],
                'edit_url' => $canConfigure
                    ? AcademicYearResource::getUrl('edit', ['record' => $year])
                    : null,
                'can_archive' => $canConfigure && ! $year->isClosed(),
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.years_hint');
    }

    /**
     * @return Collection<int|string, int>
     */
    private function countsFor(QueryBuilder $query): Collection
    {
        return $query
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->whereNull('deleted_at')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }
}
