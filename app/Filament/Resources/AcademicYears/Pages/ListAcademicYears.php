<?php

namespace App\Filament\Resources\AcademicYears\Pages;

use App\Actions\AcademicYears\OpenAcademicYear;
use App\Enums\SchoolCycle;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

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
     * Anul pe care se lucrează în modalul „Deschide anul nou". Argumentele acțiunii nu ajung în
     * închiderile componentelor de formular, iar previzualizarea are nevoie de an la fiecare
     * recalculare — deci ținta stă pe pagină, setată înainte de montare.
     */
    public ?int $openingYearId = null;

    /** Deschide modalul pentru un an anume (butonul din card). */
    public function startYearOpening(int $yearId): void
    {
        $this->openingYearId = $yearId;

        $this->mountAction('openYear');
    }

    /**
     * „Deschide anul nou": structura anului precedent urcă o treaptă — clasele (cu secția și
     * dirigintele) plus alocările care mai au sens la treapta nouă. Elevii vin separat, prin
     * Promovarea din Înmatriculări: structura întâi, oamenii după.
     */
    public function openYearAction(): Action
    {
        return Action::make('openYear')
            ->label(__('panel.actions.open_year.label'))
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(function (): string {
                $year = $this->openingYear();

                return (string) __('panel.actions.open_year.heading', [
                    'year' => $year === null ? '' : (string) $year->name,
                ]);
            })
            ->modalDescription(__('panel.actions.open_year.description'))
            ->modalSubmitActionLabel(__('panel.actions.open_year.submit'))
            ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User && $user->canConfigureSchool())
            ->schema([
                Select::make('source_year_id')
                    ->label(__('panel.actions.open_year.source_year'))
                    ->options(fn (): array => $this->openYearSources())
                    ->default(fn (): ?int => array_key_first($this->openYearSources()))
                    ->native(false)
                    ->required()
                    ->live(),
                Toggle::make('with_assignments')
                    ->label(__('panel.actions.open_year.with_assignments'))
                    ->helperText(__('panel.actions.open_year.with_assignments_hint'))
                    ->default(true)
                    ->live(),
                // Previzualizarea E decizia: câte clase se nasc, câte alocări urcă și — mai ales —
                // ce NU se preia (absolvenți, clase existente, ore care nu se predă la treapta nouă).
                Text::make(fn (Get $get): HtmlString => $this->openYearSummary(
                    $get('source_year_id'),
                    (bool) $get('with_assignments'),
                ))->color('gray'),
            ])
            ->action(fn (array $data) => $this->runYearOpening(
                (int) ($data['source_year_id'] ?? 0),
                (bool) ($data['with_assignments'] ?? true),
            ));
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
     * @return array<int, array{id: int, title: string, period: string|null, current: bool, closed: bool, closed_on: string|null, stats: array<int, string>, links: array<string, string>, edit_url: string|null, can_archive: bool, can_open: bool}>
     */
    public function yearCards(): array
    {
        // CRONOLOGIC crescător (cerința beneficiarului): anii se citesc de la mic la mare, ca pe
        // o axă a timpului. Ordonarea e pe `starts_on` (nu pe id = ordinea introducerii, care la
        // un an adăugat retroactiv ar fi mințit), cu numele drept criteriu secundar — `starts_on`
        // e nullable. Aceeași regulă la pastilele de an din celelalte secțiuni.
        $years = AcademicYear::query()->orderBy('starts_on')->orderBy('name')->get();

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
                // „Deschide anul" apare pe anul care se PREGĂTEȘTE — fără elevi înmatriculați încă
                // (clasele adăugate manual între timp nu blochează: acțiunea le sare). Un an cu
                // registru pornit e un an în desfășurare, nu unul de deschis.
                'can_open' => $canConfigure
                    && ! $year->isClosed()
                    && (int) ($enrollmentCounts->get($year->id) ?? 0) === 0
                    && $this->hasOpeningSource($year, $years, $classCounts),
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.years_hint');
    }

    // ── Deschiderea anului nou ──────────────────────────────────────────────────────────────

    public function openingYear(): ?AcademicYear
    {
        return $this->openingYearId === null
            ? null
            : AcademicYear::query()->find($this->openingYearId);
    }

    /** @return array<int, string> */
    public function openYearSources(): array
    {
        $year = $this->openingYear();

        return $year === null ? [] : app(OpenAcademicYear::class)->sourceYearsFor($year);
    }

    /**
     * Rezumatul planului, afișat ÎNAINTE de execuție — aceeași sursă de adevăr ca scrierea.
     * Întors ca HTML: liniile trebuie să RĂMÂNĂ linii (un paragraf continuu de 6 rânduri nu se
     * citește), iar fiecare bucată se escapează separat.
     */
    public function openYearSummary(mixed $sourceYearId, bool $withAssignments): HtmlString
    {
        $target = $this->openingYear();
        $source = is_numeric($sourceYearId) ? AcademicYear::query()->find((int) $sourceYearId) : null;

        if ($target === null || $source === null) {
            return self::summaryLines([(string) __('panel.actions.open_year.no_source')]);
        }

        $plan = app(OpenAcademicYear::class)->plan($target, $source);

        if ($plan['blocked'] !== null) {
            return self::summaryLines([(string) __('panel.actions.open_year.blocked.'.$plan['blocked'])]);
        }

        $assignments = array_sum(array_map(fn (array $row): int => $row['assignments'], $plan['promoted']));
        $dropped = array_sum(array_map(fn (array $row): int => $row['dropped'], $plan['promoted']));

        if ($plan['promoted'] === []) {
            $lines = [(string) __('panel.actions.open_year.nothing')];
        } else {
            $lines = [(string) __('panel.actions.open_year.summary', [
                'classes' => count($plan['promoted']),
                'assignments' => $withAssignments ? $assignments : 0,
            ])];

            foreach (array_slice($plan['promoted'], 0, 8) as $row) {
                $lines[] = '• '.self::classLabel($row['source']).' → '.SchoolCycle::romanNumeral($row['grade'])
                    .' '.($row['source']->section ?? '');
            }

            if (count($plan['promoted']) > 8) {
                $lines[] = '• …';
            }
        }

        // Ce NU se preia — partea care ține administratorul departe de surprize.
        if ($plan['graduating'] !== []) {
            $lines[] = (string) __('panel.actions.open_year.graduating', [
                'classes' => implode(', ', array_map(self::classLabel(...), $plan['graduating'])),
            ]);
        }

        if ($plan['existing'] !== []) {
            $lines[] = (string) __('panel.actions.open_year.existing', ['count' => count($plan['existing'])]);
        }

        if ($withAssignments && $dropped > 0) {
            $lines[] = (string) __('panel.actions.open_year.dropped', ['count' => $dropped]);
        }

        $lines[] = (string) __('panel.actions.open_year.todo');

        if ($target->terms()->count() === 0) {
            $lines[] = (string) __('panel.actions.open_year.todo_terms');
        }

        return self::summaryLines($lines);
    }

    /**
     * Liniile rezumatului, ca HTML — altfel modalul le lipește într-un paragraf continuu.
     * Fiecare linie se escapează separat; doar separatorul e marcaj.
     *
     * @param  array<int, string>  $lines
     */
    private static function summaryLines(array $lines): HtmlString
    {
        return new HtmlString(implode('<br>', array_map(fn (string $line): string => e($line), $lines)));
    }

    public function runYearOpening(int $sourceYearId, bool $withAssignments): void
    {
        $target = $this->openingYear();
        $source = AcademicYear::query()->find($sourceYearId);

        if ($target === null || $source === null
            || ! ((auth('web')->user() instanceof User) && auth('web')->user()->canConfigureSchool())) {
            return;
        }

        $result = app(OpenAcademicYear::class)->handle($target, $source, $withAssignments);

        if ($result['blocked'] !== null) {
            Notification::make()
                ->warning()
                ->title(__('panel.actions.open_year.blocked.'.$result['blocked']))
                ->send();

            return;
        }

        if ($result['classes'] === 0) {
            Notification::make()->warning()->title(__('panel.actions.open_year.nothing'))->send();

            return;
        }

        $notes = array_values(array_filter([
            $result['assignments'] > 0
                ? (string) trans_choice('panel.actions.open_year.done_assignments', $result['assignments'], ['count' => $result['assignments']])
                : null,
            $result['homeroom_missing'] > 0
                ? (string) __('panel.actions.open_year.done_homeroom', ['count' => $result['homeroom_missing']])
                : null,
            (string) __('panel.actions.open_year.done_next'),
        ]));

        Notification::make()
            ->success()
            ->title(trans_choice('panel.actions.open_year.done', $result['classes'], ['count' => $result['classes']]))
            ->body(implode(' ', $notes))
            ->send();

        $this->openingYearId = null;
    }

    private static function classLabel(SchoolClass $class): string
    {
        return trim($class->name.' '.($class->section ?? ''));
    }

    /**
     * Există un an anterior CU clase din care s-ar putea prelua structura? Aceeași regulă ca în
     * {@see OpenAcademicYear::sourceYearsFor()}, dar din datele deja încărcate — cardurile nu
     * trebuie să mai interogheze o dată per an.
     *
     * @param  Collection<int, AcademicYear>  $years
     * @param  Collection<int|string, int>  $classCounts
     */
    private function hasOpeningSource(AcademicYear $target, Collection $years, Collection $classCounts): bool
    {
        foreach ($years as $year) {
            if ((int) $year->getKey() === (int) $target->getKey()) {
                continue;
            }

            if ((int) ($classCounts->get($year->id) ?? 0) === 0) {
                continue;
            }

            if ($year->starts_on !== null && $target->starts_on !== null && $year->starts_on >= $target->starts_on) {
                continue;
            }

            return true;
        }

        return false;
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
