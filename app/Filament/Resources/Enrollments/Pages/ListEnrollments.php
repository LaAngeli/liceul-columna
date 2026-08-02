<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Actions\Enrollments\EnrollStudents;
use App\Actions\Enrollments\PromoteClass;
use App\Enums\SchoolCycle;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolCalendar;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Înmatriculări" = REGISTRUL ȘCOLII, restructurat 2026-08-02 după feedback („nu răspunde
 * cererii, greu de operat, pe alocuri bugs"). Ce s-a schimbat, pe cauze MĂSURATE pe datele reale:
 *
 *   • Operațiunea centrală LIPSEA. Deschiderea unui an însemna 773 de treceri prin formularul cu
 *     un elev (exact ce arăta anul 2027–2028: „773 elevi fără înmatriculare"). Acum există
 *     PROMOVAREA din anul precedent — o apăsare, mapare treaptă+1 / aceeași secțiune, cu
 *     previzualizare — și înmatricularea în masă direct în registrul clasei.
 *   • Aterizarea era o grilă plată de 52 de carduri pe 2500px, fără căutare: clasele se caută
 *     acum după nume și se grupează pe cicluri.
 *   • Badge-ul anului număra ȘI elevii plecați — nu spunea câți sunt efectiv în școală.
 *   • `openClass()` accepta o clasă din ORICE an: un `?clasa=` străin deschidea registrul ei sub
 *     pastila anului activ, o stare care se citea greșit.
 *
 * Secțiunea e a administrației (ConfiguresSchool: citire pentru administrația academică,
 * scriere doar pentru configuratori) — nu are nevoie de scoping pe rol la carduri.
 */
class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected string $view = 'filament.catalog.enrollments-navigator';

    /** Anul școlar activ (id „dorit" din URL, validat la citire prin anii cu clase). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** Clasa al cărei registru e deschis (validată la citire). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** Filtrul de căutare a clasei pe aterizare (52 de carduri nu se parcurg cu ochiul). */
    #[Url(as: 'q', except: '')]
    public string $classSearch = '';

    /** Lista elevilor NEînmatriculați în anul activ, deschisă/închisă din cardul dedicat. */
    public bool $showUnassigned = false;

    /** @var Collection<int|string, int>|null nr. de clase per an școlar (memoizat pe instanță) */
    private ?Collection $classCountsByYear = null;

    private SchoolClass|false|null $activeClassMemo = null;

    /**
     * Memoia listei de neînmatriculați — aceeași formă ca {@see unassigned()}: valoarea metodei E
     * atribuirea, deci un tip mai larg aici ar slăbi contractul returnat.
     *
     * @var array{count: int, students: array<int, array{id: int, name: string, register: string|null, enroll_url: string}>}|null
     */
    private ?array $unassignedMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            // PROMOVAREA — fluxul care lipsea cu totul. Vizibilă doar cu drept de configurare și
            // doar când există un an-sursă cu registru: altfel ar promite o operațiune imposibilă.
            Action::make('promote')
                ->label(__('panel.enrollments_nav.promote.label'))
                ->icon('heroicon-o-academic-cap')
                ->color('primary')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalHeading(__('panel.enrollments_nav.promote.heading'))
                ->modalDescription(__('panel.enrollments_nav.promote.description'))
                ->modalSubmitActionLabel(__('panel.enrollments_nav.promote.submit'))
                ->visible(fn (): bool => $this->canConfigure()
                    && $this->activeClass() === null
                    && $this->promotionSourceYears() !== [])
                ->schema([
                    Select::make('source_year_id')
                        ->label(__('panel.enrollments_nav.promote.source_year'))
                        ->options(fn (): array => $this->promotionSourceYears())
                        ->default(fn (): ?int => array_key_first($this->promotionSourceYears()))
                        ->native(false)
                        ->required()
                        ->live(),
                    // Previzualizarea E decizia: arată perechile clasă→clasă și, mai ales, clasele
                    // FĂRĂ corespondent în anul țintă (acolo lipsesc clase de creat).
                    Text::make(fn (Get $get): string => $this->promotionSummary($get('source_year_id')))
                        ->color('gray'),
                ])
                ->action(fn (array $data) => $this->runPromotion((int) ($data['source_year_id'] ?? 0))),
            CreateAction::make()
                // Din registrul unei clase, adăugarea vine pre-completată (an + clasă) —
                // formularul își validează singur contextul (id străin = ignorat).
                ->url(function (): string {
                    $class = $this->activeClass();

                    return EnrollmentResource::getUrl('create', $class !== null
                        ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey()]
                        : []);
                }),
        ];
    }

    public function canConfigure(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openYear(int|string $id): void
    {
        $id = (int) $id;

        if ($this->classCountsByYear()->has($id)) {
            $this->yearParam = (string) $id;
            $this->unassignedMemo = null;
        }
    }

    public function openClass(int|string $id): void
    {
        $id = (int) $id;

        // Clasa trebuie să fie DIN ANUL ACTIV: altfel un `?clasa=` dintr-un alt an deschidea
        // registrul acelei clase sub pastila anului activ — o stare care se citea greșit.
        $belongsToActiveYear = SchoolClass::query()
            ->whereKey($id)
            ->where('academic_year_id', $this->activeYearId())
            ->exists();

        if ($belongsToActiveYear) {
            $this->classParam = (string) $id;
            $this->activeClassMemo = null;
        }
    }

    public function leaveClass(): void
    {
        $this->classParam = null;
        $this->activeClassMemo = null;
    }

    public function activeClass(): ?SchoolClass
    {
        if ($this->activeClassMemo === null) {
            $this->activeClassMemo = ($this->classParam !== null && ctype_digit($this->classParam))
                ? (SchoolClass::query()->with(['homeroomTeacher', 'academicYear'])->whereKey((int) $this->classParam)->first() ?? false)
                : false;
        }

        return $this->activeClassMemo === false ? null : $this->activeClassMemo;
    }

    /** Anul activ: cel cerut prin URL dacă are clase, altfel anul CURENT, altfel cel mai recent. */
    public function activeYearId(): ?int
    {
        $visible = $this->classCountsByYear();

        if ($this->yearParam !== null && ctype_digit($this->yearParam) && $visible->has((int) $this->yearParam)) {
            return (int) $this->yearParam;
        }

        $currentYearId = AcademicYear::query()->where('is_current', true)->value('id');

        if ($currentYearId !== null && $visible->has((int) $currentYearId)) {
            return (int) $currentYearId;
        }

        $newest = $visible->keys()->sortDesc()->first();

        return $newest !== null ? (int) $newest : null;
    }

    /**
     * Registrul clasei active — constrângerea tabelului (apelată din EnrollmentsTable).
     *
     * @param  Builder<Enrollment>  $query
     * @return Builder<Enrollment>
     */
    public function applyRosterContext(Builder $query): Builder
    {
        $class = $this->activeClass();

        return $class !== null
            ? $query->where('school_class_id', $class->getKey())
            : $query;
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Pastilele anilor (cu clase), în ordine CRONOLOGICĂ și cu anul curent marcat. Badge = elevii
     * aflați EFECTIV în școală (activi): totalul rândurilor includea și plecații, deci nu spunea
     * cât cântărește anul.
     *
     * @return array<int, array{id: int, label: string, count: int, current: bool}>
     */
    public function yearPills(): array
    {
        $classCounts = $this->classCountsByYear();

        if ($classCounts->isEmpty()) {
            return [];
        }

        $activeCounts = Enrollment::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->whereNull('left_on')
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id');

        return AcademicYear::query()
            ->whereKey($classCounts->keys()->all())
            ->orderBy('starts_on')->orderBy('name')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => (int) $year->id,
                'label' => (string) $year->name,
                'count' => (int) ($activeCounts->get($year->id) ?? 0),
                'current' => (bool) $year->is_current,
            ])
            ->all();
    }

    /**
     * Cât din școală e înmatriculat în anul activ — numărul care spune dintr-o privire dacă
     * registrul anului e gata sau abia început (înainte, asta se deducea dintr-un avertisment).
     *
     * @return array{enrolled: int, total: int, percent: int}
     */
    public function yearProgress(): array
    {
        $total = Student::query()->count();

        if ($this->activeYearId() === null || $total === 0) {
            return ['enrolled' => 0, 'total' => $total, 'percent' => 0];
        }

        $enrolled = max(0, $total - $this->unassigned()['count']);

        return [
            'enrolled' => $enrolled,
            'total' => $total,
            'percent' => (int) round($enrolled / $total * 100),
        ];
    }

    /**
     * Cardurile claselor anului activ, GRUPATE pe cicluri și filtrate de căutare: 52 de carduri
     * într-o grilă plată nu se parcurg cu ochiul, iar clasa căutată era la capătul unei derulări.
     *
     * @return array<int, array{cycle: string, label: string, cards: array<int, array<string, mixed>>}>
     */
    public function classGroups(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $needle = trim($this->classSearch);

        $classes = SchoolClass::query()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $yearId)
            ->when($needle !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('name', 'like', '%'.$needle.'%')
                    ->orWhere('section', 'like', '%'.$needle.'%'),
            ))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $rosterCounts = Enrollment::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->selectRaw('school_class_id, SUM(CASE WHEN left_on IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN left_on IS NOT NULL THEN 1 ELSE 0 END) AS departed')
            ->whereIn('school_class_id', $classes->pluck('id')->all())
            ->groupBy('school_class_id')
            ->get()
            ->keyBy('school_class_id');

        $groups = [];

        foreach ($classes as $class) {
            $counts = $rosterCounts->get($class->id);
            $cycle = SchoolCycle::fromGradeLevel((int) $class->grade_level);

            $groups[$cycle->value] ??= [
                'cycle' => $cycle->value,
                'label' => $cycle->label(),
                'cards' => [],
            ];

            $groups[$cycle->value]['cards'][] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                // Clasa fără diriginte = coadă de validare fără validator (motivări) și registru
                // fără responsabil — chip de avertisment direct pe card, nu doar în widget.
                'no_homeroom' => $class->homeroomTeacher === null,
                'active' => (int) ($counts->active ?? 0),
                'departed' => (int) ($counts->departed ?? 0),
            ];
        }

        return array_values($groups);
    }

    /** Numărul de clase ale anului activ, indiferent de căutare — pentru mesajul „niciun rezultat". */
    public function yearClassCount(): int
    {
        $yearId = $this->activeYearId();

        return $yearId === null ? 0 : (int) ($this->classCountsByYear()->get($yearId) ?? 0);
    }

    public function enrollmentsHint(): string
    {
        return (string) __('panel.catalog_nav.enrollments_hint');
    }

    public function toggleUnassigned(): void
    {
        $this->showUnassigned = ! $this->showUnassigned;
    }

    // ── Promovarea din anul precedent ───────────────────────────────────────────────────────

    /**
     * Anii care pot fi SURSĂ de promovare pentru anul activ: cei cu elevi activi, în afara lui
     * însuși, cel mai recent întâi.
     *
     * @return array<int, string>
     */
    public function promotionSourceYears(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        return AcademicYear::query()
            ->whereKeyNot($yearId)
            ->whereHas('enrollments', fn (Builder $query) => $query->whereNull('left_on'))
            ->orderByDesc('starts_on')
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    /**
     * Planul promovării: fiecare clasă a anului-sursă cu elevi activi → clasa sugerată din anul
     * activ (treaptă+1, aceeași secțiune). Clasele fără corespondent rămân cu ținta null și se
     * RAPORTEAZĂ — acolo lipsesc clase de creat, iar o promovare tăcută le-ar pierde.
     *
     * @return array<int, array{source: SchoolClass, target: SchoolClass|null, students: int}>
     */
    public function promotionPlan(?int $sourceYearId): array
    {
        $targetYearId = $this->activeYearId();

        if ($sourceYearId === null || $targetYearId === null || $sourceYearId === $targetYearId) {
            return [];
        }

        $promote = app(PromoteClass::class);

        $counts = Enrollment::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->whereNull('left_on')
            ->where('academic_year_id', $sourceYearId)
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->groupBy('school_class_id')
            ->pluck('aggregate', 'school_class_id');

        return SchoolClass::query()
            ->whereKey($counts->keys()->all())
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class): array => [
                'source' => $class,
                'target' => $promote->suggestTarget($class, $targetYearId),
                'students' => (int) ($counts->get($class->id) ?? 0),
            ])
            ->all();
    }

    /** Rezumatul planului, afișat în modal ÎNAINTE de execuție — previzualizarea e decizia. */
    public function promotionSummary(mixed $sourceYearId): string
    {
        $plan = $this->promotionPlan(is_numeric($sourceYearId) ? (int) $sourceYearId : null);

        if ($plan === []) {
            return (string) __('panel.enrollments_nav.promote.empty');
        }

        $mapped = array_values(array_filter($plan, fn (array $row): bool => $row['target'] !== null));
        $unmapped = array_values(array_filter($plan, fn (array $row): bool => $row['target'] === null));
        $students = array_sum(array_map(fn (array $row): int => $row['students'], $mapped));

        $lines = [(string) __('panel.enrollments_nav.promote.summary', [
            'classes' => count($mapped),
            'students' => $students,
        ])];

        foreach (array_slice($mapped, 0, 8) as $row) {
            $lines[] = '• '.self::classLabel($row['source']).' → '.self::classLabel($row['target']).' ('.$row['students'].')';
        }

        if (count($mapped) > 8) {
            $lines[] = '• …';
        }

        if ($unmapped !== []) {
            $lines[] = (string) __('panel.enrollments_nav.promote.unmapped', [
                'classes' => implode(', ', array_map(fn (array $row): string => self::classLabel($row['source']), $unmapped)),
            ]);
        }

        return implode("\n", $lines);
    }

    public function runPromotion(int $sourceYearId): void
    {
        if (! $this->canConfigure()) {
            return;
        }

        $plan = array_filter($this->promotionPlan($sourceYearId), fn (array $row): bool => $row['target'] !== null);

        if ($plan === []) {
            Notification::make()->warning()->title(__('panel.enrollments_nav.promote.empty'))->send();

            return;
        }

        $promote = app(PromoteClass::class);
        $enrolled = 0;
        $skipped = 0;

        foreach ($plan as $row) {
            $result = $promote->handle($row['source'], $row['target'], SchoolCalendar::localNow());
            $enrolled += $result['enrolled'];
            $skipped += $result['skipped'];
        }

        $this->unassignedMemo = null;

        Notification::make()
            ->success()
            ->title(trans_choice('panel.enrollments_nav.promote.done', $enrolled, ['count' => $enrolled]))
            ->body($skipped > 0 ? (string) __('panel.enrollments_nav.promote.skipped', ['count' => $skipped]) : null)
            ->send();
    }

    private static function classLabel(SchoolClass $class): string
    {
        return trim($class->name.' '.($class->section ?? ''));
    }

    // ── Înmatricularea în masă în clasa activă ──────────────────────────────────────────────

    /**
     * Elevii înmatriculabili în clasa activă: cei fără NICIUN rând în anul ei (nici arhivat).
     *
     * @return array<int, string>
     */
    public function enrollableStudents(): array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return [];
        }

        return Student::query()
            ->whereDoesntHave('enrollments', fn (Builder $query) => $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('academic_year_id', $class->academic_year_id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => (string) $student->full_name])
            ->all();
    }

    /**
     * „Adaugă elevi" din registrul clasei: mai mulți elevi dintr-o singură dată, exact operațiunea
     * care lipsea. Lista oferă doar elevii înmatriculabili în anul clasei.
     */
    public function addStudentsAction(): Action
    {
        return Action::make('addStudents')
            ->label(__('panel.enrollments_nav.bulk_enroll.label'))
            ->icon('heroicon-o-user-plus')
            ->modalHeading(fn (): string => __('panel.enrollments_nav.bulk_enroll.heading', [
                'class' => $this->activeClass() !== null ? self::classLabel($this->activeClass()) : '',
            ]))
            ->modalSubmitActionLabel(__('panel.enrollments_nav.bulk_enroll.submit'))
            ->visible(fn (): bool => $this->canConfigure() && $this->activeClass() !== null)
            ->schema([
                Select::make('students')
                    ->label(__('panel.enrollments_nav.bulk_enroll.students'))
                    ->helperText(__('panel.enrollments_nav.bulk_enroll.students_hint'))
                    ->options(fn (): array => $this->enrollableStudents())
                    ->multiple()
                    ->searchable()
                    ->required(),
            ])
            ->action(fn (array $data) => $this->enrollIntoActiveClass($data['students'] ?? []));
    }

    /**
     * Înmatricularea în masă în clasa activă. Gardul de rol se repetă aici, nu doar pe butonul
     * care o cheamă: acțiunile Livewire sunt endpointuri.
     *
     * @param  array<int, mixed>  $studentIds
     */
    public function enrollIntoActiveClass(array $studentIds): void
    {
        $class = $this->activeClass();

        if ($class === null || ! $this->canConfigure()) {
            return;
        }

        $result = app(EnrollStudents::class)->handle(
            $class,
            array_values(array_map(intval(...), $studentIds)),
            SchoolCalendar::localNow(),
        );

        $this->unassignedMemo = null;
        $this->resetTable();

        if ($result['blocked']) {
            Notification::make()->danger()->title(__('panel.enrollments_nav.bulk_enroll.blocked'))->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(trans_choice('panel.enrollments_nav.bulk_enroll.done', $result['enrolled'], ['count' => $result['enrolled']]))
            ->body($result['skipped'] > 0 ? (string) __('panel.enrollments_nav.bulk_enroll.skipped', ['count' => $result['skipped']]) : null)
            ->send();
    }

    // ── Semnale de integritate + neînmatriculații ───────────────────────────────────────────

    /**
     * Elevii ACTIVI fără NICIO înmatriculare (nici măcar arhivată) în anul activ — lista de
     * lucru la deschiderea anului și plasa pentru omisiuni. Cei cu înmatriculare ARHIVATĂ nu
     * apar aici (formularul îi refuză cu îndrumare spre restaurare — semnal separat).
     *
     * @return array{count: int, students: array<int, array{id: int, name: string, register: string|null, enroll_url: string}>}
     */
    public function unassigned(): array
    {
        // Memoizat: e citit de progres, de card ȘI de blade la fiecare randare — trei interogări
        // identice pe o listă de 773 de elevi.
        if ($this->unassignedMemo !== null) {
            return $this->unassignedMemo;
        }

        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return $this->unassignedMemo = ['count' => 0, 'students' => []];
        }

        $query = Student::query()
            // Fără scope-ul SoftDeletes pe subinterogare (echivalent withTrashed): un rând ARHIVAT
            // tot îl scoate pe elev din „de înmatriculat" — formularul l-ar refuza cu îndrumare
            // spre restaurare (archived_duplicate), deci aici ar fi o fundătură.
            ->whereDoesntHave('enrollments', fn (Builder $sub) => $sub
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('academic_year_id', $yearId))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $count = (clone $query)->count();

        // Plafon de afișare: la un an nou, „toată școala" e neînmatriculată — lista rămâne
        // parcurgabilă, iar totalul spune restul.
        // Construit cu foreach, nu cu map()->all(): forma rândului se PIERDE printr-o closure
        // tipizată `: array`, iar contractul metodei (id/name/register/enroll_url) rămâne verificat.
        $students = [];

        foreach ($query->limit(60)->get() as $student) {
            $students[] = [
                'id' => (int) $student->id,
                'name' => (string) $student->full_name,
                'register' => $student->register_number !== null ? (string) $student->register_number : null,
                'enroll_url' => EnrollmentResource::getUrl('create', ['an' => $yearId, 'elev' => $student->id]),
            ];
        }

        return $this->unassignedMemo = ['count' => $count, 'students' => $students];
    }

    /**
     * Semnalele registrului pe anul activ — vizibile înaintea efectelor (elev „dispărut" din
     * cataloage, duplicat imposibil de recreat, interval negativ moștenit).
     *
     * @return list<array{level: string, text: string}>
     */
    public function integrity(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        // Neînmatriculații au acum bara de progres + cardul lor cu listă și adăugare în masă —
        // un al treilea loc care spune același lucru era zgomot, nu semnal.
        $signals = [];

        $archived = Enrollment::onlyTrashed()->where('academic_year_id', $yearId)->count();

        if ($archived > 0) {
            $signals[] = [
                'level' => 'info',
                'text' => (string) trans_choice('panel.enrollments_nav.integrity.archived', $archived, ['count' => $archived]),
            ];
        }

        // Intervale negative moștenite (garda de formular previne rândurile NOI).
        $broken = Enrollment::query()
            ->where('academic_year_id', $yearId)
            ->whereNotNull('enrolled_on')
            ->whereNotNull('left_on')
            ->whereColumn('left_on', '<', 'enrolled_on')
            ->count();

        if ($broken > 0) {
            $signals[] = [
                'level' => 'danger',
                'text' => (string) trans_choice('panel.enrollments_nav.integrity.broken_interval', $broken, ['count' => $broken]),
            ];
        }

        return $signals;
    }

    /**
     * Numărătoarea registrului clasei ACTIVE (activi/plecați) — antetul registrului o afișează
     * lângă diriginte, ca deschiderea clasei să spună imediat cât „cântărește".
     *
     * @return array{active: int, departed: int}
     */
    public function rosterCounts(): array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return ['active' => 0, 'departed' => 0];
        }

        $row = Enrollment::query()
            ->toBase()
            ->selectRaw('SUM(CASE WHEN left_on IS NULL THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN left_on IS NOT NULL THEN 1 ELSE 0 END) AS departed')
            ->where('school_class_id', $class->getKey())
            ->first();

        return [
            'active' => (int) ($row->active ?? 0),
            'departed' => (int) ($row->departed ?? 0),
        ];
    }

    /** @return Collection<int|string, int> */
    private function classCountsByYear(): Collection
    {
        return $this->classCountsByYear ??= SchoolClass::query()
            ->toBase()
            ->whereNull('deleted_at')
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }
}
