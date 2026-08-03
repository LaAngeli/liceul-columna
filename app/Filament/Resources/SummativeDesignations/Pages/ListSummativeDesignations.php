<?php

namespace App\Filament\Resources\SummativeDesignations\Pages;

use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use App\Filament\Concerns\HasYearPillsTable;
use App\Filament\Resources\SummativeDesignations\SummativeDesignationResource;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SummativeDesignation;
use App\Models\User;
use App\Observers\GradeObserver;
use App\Support\ContentTranslator;
use App\Support\PanelGuide;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * „Discipline cu sumativă" — RESTRUCTURATĂ pe clase (cerința beneficiarului, 2026-08-04, după ce
 * secțiunea a fost populată cu date demo: 86 de rânduri plate pe nouă pagini).
 *
 * Ce nu mergea, văzut abia cu date reale: lista repeta clasa pe fiecare rând (2–3 discipline
 * fiecare), tipul sumativei era identic pe tot ciclul (e derivat din treaptă, nu o proprietate a
 * rândului), iar informația cea mai importantă LIPSEA cu totul — care clase n-au NICIO disciplină
 * desemnată.
 *
 * Aici designarea se citește cum e gândită: o proprietate a CLASEI. Aterizarea = un card per clasă,
 * grupat pe cicluri, cu disciplinele ca etichete și cu starea gărzii scrisă pe card. Sus stă
 * ACOPERIREA, fiindcă efectul de prag e contraintuitiv: prima desemnare dintr-o clasă ARMEAZĂ garda
 * pentru toată clasa — de atunci, o sumativă la orice altă disciplină nedesemnată e refuzată la
 * salvare ({@see GradeObserver}).
 *
 * Tabelul rămâne, dar în contextul unei clase: acolo redundanța dispare, iar acțiunile de rând
 * (editare, ștergere) își păstrează sensul.
 */
class ListSummativeDesignations extends ListRecords
{
    use HasYearPillsTable;

    protected static string $resource = SummativeDesignationResource::class;

    protected string $view = 'filament.catalog.summative-designations';

    /** Clasa deschisă (id „dorit" din URL, validat la citire — ca la celelalte navigatoare). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** @var array<string, mixed> memoizare per-request */
    private array $memo = [];

    protected function getHeaderActions(): array
    {
        return [
            $this->bulkDesignateAction(),
            CreateAction::make()
                // În contextul unei clase, adăugarea vine pre-completată pe ea.
                ->url(fn (): string => SummativeDesignationResource::getUrl('create', array_filter([
                    'clasa' => $this->activeClass()?->getKey(),
                ]))),
        ];
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.summative_hint');
    }

    // ── Navigarea pe clase ──────────────────────────────────────────────────────────────────

    public function openClass(int|string $id): void
    {
        $id = (int) $id;

        $this->classParam = $this->designableClasses()->has($id) ? (string) $id : null;
        $this->memo = [];
        $this->resetPage();
    }

    public function leaveClass(): void
    {
        $this->classParam = null;
        $this->memo = [];
        $this->resetPage();
    }

    /** Clasa din context, validată pe setul designabil al anului activ (id străin → null). */
    public function activeClass(): ?SchoolClass
    {
        if ($this->classParam === null || ! ctype_digit($this->classParam)) {
            return null;
        }

        return $this->designableClasses()->get((int) $this->classParam);
    }

    public function hasClassContext(): bool
    {
        return $this->activeClass() !== null;
    }

    /** Eticheta tipului de sumativă al clasei (gimnaziu → ESS, liceu → teză). */
    public function summativeLabelFor(SchoolClass $class): string
    {
        return EvaluationType::Teza->labelForCycle(SchoolCycle::fromGradeLevel((int) $class->grade_level));
    }

    /**
     * Cardurile claselor, GRUPATE pe cicluri — designarea e o proprietate a clasei, iar ciclul
     * decide tipul sumativei, deci gruparea e chiar taxonomia domeniului.
     *
     * @return array<int, array{label: string, cards: array<int, array{id: int, title: string, type: string, subjects: array<int, string>, configured: bool}>}>
     */
    public function classGroups(): array
    {
        $designations = $this->designationsByClass();

        $groups = [];

        foreach ($this->designableClasses() as $class) {
            $cycle = SchoolCycle::fromGradeLevel((int) $class->grade_level);

            $groups[$cycle->value][] = [
                'id' => (int) $class->getKey(),
                'title' => trim($class->name.' '.($class->section ?? '')),
                'type' => $this->summativeLabelFor($class),
                'subjects' => $designations->get((int) $class->getKey(), collect())
                    ->map(fn (SummativeDesignation $designation): string => ContentTranslator::subject((string) $designation->subject?->name))
                    ->sort()
                    ->values()
                    ->all(),
                'configured' => $designations->has((int) $class->getKey()),
            ];
        }

        $ordered = [];

        foreach ([SchoolCycle::Gimnaziu, SchoolCycle::Liceu] as $cycle) {
            if (isset($groups[$cycle->value])) {
                $ordered[] = [
                    'label' => (string) __('panel.catalog_nav.cycles.'.$cycle->value),
                    'cards' => $groups[$cycle->value],
                ];
            }
        }

        return $ordered;
    }

    /**
     * ACOPERIREA: câte clase au cel puțin o desemnare. Semnalul lipsea cu totul, deși e singura
     * informație operațională a secțiunii — o clasă neconfigurată nu e „goală", e o clasă în care
     * garda nu apără nimic.
     *
     * @return array{configured: int, total: int, missing: int, missing_labels: array<int, string>}
     */
    public function coverage(): array
    {
        $classes = $this->designableClasses();
        $designations = $this->designationsByClass();

        $missing = $classes->reject(fn (SchoolClass $class): bool => $designations->has((int) $class->getKey()));

        return [
            'configured' => $classes->count() - $missing->count(),
            'total' => $classes->count(),
            'missing' => $missing->count(),
            'missing_labels' => $missing
                ->map(fn (SchoolClass $class): string => trim($class->name.' '.($class->section ?? '')))
                ->values()
                ->all(),
        ];
    }

    /** Ghidul „i" al semnalului de acoperire — efectul de prag nu se deduce din ecran. */
    public function coverageHint(): ?HtmlString
    {
        return PanelGuide::hint('summative_class');
    }

    // ── Designarea în MASĂ ──────────────────────────────────────────────────────────────────

    /**
     * O clasă are 2–3 discipline cu sumativă, iar școala are zeci de clase: pe rând, configurarea
     * anului însemna ~90 de treceri prin formular. Aici se aleg clasele și disciplinele deodată,
     * cu previzualizare — perechile care există deja se sar, iar cele nepotrivite pe treaptă nici
     * nu se creează (o disciplină de gimnaziu n-are ce desemna la liceu).
     */
    public function bulkDesignateAction(): Action
    {
        return Action::make('bulkDesignate')
            ->label(__('grading.designation.bulk.label'))
            ->icon('heroicon-o-squares-plus')
            ->color('primary')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(__('grading.designation.bulk.heading'))
            ->modalDescription(__('grading.designation.bulk.description'))
            ->modalSubmitActionLabel(__('grading.designation.bulk.submit'))
            // ACEEAȘI regulă ca la adăugarea unei singure desemnări: sumativa e decizie
            // PEDAGOGICĂ (intră 50% în media semestrială), deci o scrie cine administrează
            // catalogul — nu cine configurează școala. Un buton „în masă" oferit administratorului
            // operațional ar fi promis exact ce formularul îi refuză.
            ->visible(fn (): bool => ($user = auth('web')->user()) instanceof User
                && $user->canAdministerCatalog()
                && $this->designableClasses()->isNotEmpty())
            ->schema([
                Select::make('class_ids')
                    ->label(__('grading.designation.bulk.classes'))
                    ->hint(PanelGuide::hint('summative_bulk'))
                    ->helperText(__('grading.designation.bulk.classes_hint'))
                    ->options(fn (): array => $this->classOptions())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('subject_ids')
                    ->label(__('grading.designation.bulk.subjects'))
                    ->helperText(__('grading.designation.bulk.subjects_hint'))
                    ->options(fn (Get $get): array => $this->subjectOptionsFor($get('class_ids')))
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->live(),
                TextInput::make('order_reference')
                    ->label(__('grading.designation.fields.order_reference'))
                    ->helperText(__('grading.designation.help'))
                    ->maxLength(255),
                Text::make(fn (Get $get): string => $this->bulkPreview($get('class_ids'), $get('subject_ids')))
                    ->color('gray'),
            ])
            ->action(fn (array $data) => $this->runBulkDesignate($data));
    }

    /** Previzualizarea: ce se creează, ce există deja, ce nu se potrivește pe treaptă. */
    private function bulkPreview(mixed $classIds, mixed $subjectIds): string
    {
        $plan = $this->bulkPlan($classIds, $subjectIds);

        if ($plan['create'] === [] && $plan['existing'] === 0 && $plan['mismatched'] === 0) {
            return (string) __('grading.designation.bulk.preview_empty');
        }

        $lines = [(string) trans_choice('grading.designation.bulk.preview', count($plan['create']), ['count' => count($plan['create'])])];

        if ($plan['existing'] > 0) {
            $lines[] = (string) __('grading.designation.bulk.preview_existing', ['count' => $plan['existing']]);
        }

        if ($plan['mismatched'] > 0) {
            $lines[] = (string) __('grading.designation.bulk.preview_mismatched', ['count' => $plan['mismatched']]);
        }

        return implode("\n", $lines);
    }

    /**
     * Perechile (clasă × disciplină) de creat, cu motivele excluderilor.
     *
     * @return array{create: array<int, array{class: int, subject: int}>, existing: int, mismatched: int}
     */
    private function bulkPlan(mixed $classIds, mixed $subjectIds): array
    {
        $classes = $this->designableClasses()->only(is_array($classIds) ? array_map(intval(...), $classIds) : []);
        $subjects = Subject::query()
            ->whereKey(is_array($subjectIds) ? array_map(intval(...), $subjectIds) : [])
            ->get();

        if ($classes->isEmpty() || $subjects->isEmpty()) {
            return ['create' => [], 'existing' => 0, 'mismatched' => 0];
        }

        $taken = SummativeDesignation::query()
            ->whereIn('school_class_id', $classes->keys()->all())
            ->get()
            ->map(fn (SummativeDesignation $row): string => $row->school_class_id.':'.$row->subject_id)
            ->all();

        $create = [];
        $existing = 0;
        $mismatched = 0;

        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                // Disciplina trebuie să se predea CHIAR la treapta clasei: nomenclatorul are câte o
                // fișă per ciclu pentru aceleași denumiri.
                if (! $subject->coversGrade((int) $class->grade_level)) {
                    $mismatched++;

                    continue;
                }

                if (in_array($class->getKey().':'.$subject->getKey(), $taken, true)) {
                    $existing++;

                    continue;
                }

                $create[] = ['class' => (int) $class->getKey(), 'subject' => (int) $subject->getKey()];
            }
        }

        return ['create' => $create, 'existing' => $existing, 'mismatched' => $mismatched];
    }

    /** @param  array<string, mixed>  $data */
    private function runBulkDesignate(array $data): void
    {
        if (! ((auth('web')->user() instanceof User) && auth('web')->user()->canAdministerCatalog())) {
            return;
        }

        $plan = $this->bulkPlan($data['class_ids'] ?? null, $data['subject_ids'] ?? null);

        if ($plan['create'] === []) {
            Notification::make()->warning()->title(__('grading.designation.bulk.nothing'))->send();

            return;
        }

        $order = filled($data['order_reference'] ?? null) ? trim((string) $data['order_reference']) : null;

        DB::transaction(function () use ($plan, $order): void {
            foreach ($plan['create'] as $pair) {
                SummativeDesignation::query()->create([
                    'school_class_id' => $pair['class'],
                    'subject_id' => $pair['subject'],
                    'order_reference' => $order,
                ]);
            }
        });

        $this->memo = [];

        Notification::make()
            ->success()
            ->title(trans_choice('grading.designation.bulk.done', count($plan['create']), ['count' => count($plan['create'])]))
            ->body($plan['existing'] > 0 ? (string) __('grading.designation.bulk.preview_existing', ['count' => $plan['existing']]) : null)
            ->send();
    }

    // ── Sursele de date ─────────────────────────────────────────────────────────────────────

    /**
     * Clasele DESIGNABILE ale anului activ: gimnaziu și liceu. Primarul nu are notă sumativă, deci
     * nu apare nici pe carduri, nici în designarea în masă — aceeași regulă ca în formular.
     *
     * @return Collection<int, SchoolClass>
     */
    private function designableClasses(): Collection
    {
        /** @var Collection<int, SchoolClass> */
        return $this->memo['classes'] ??= SchoolClass::query()
            ->where('academic_year_id', $this->activeYearId())
            ->where('grade_level', '>=', 5)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->keyBy(fn (SchoolClass $class): int => (int) $class->getKey());
    }

    /**
     * Designările anului, grupate pe clasă (o interogare, nu una per card).
     *
     * @return Collection<int, Collection<int, SummativeDesignation>>
     */
    private function designationsByClass(): Collection
    {
        /** @var Collection<int, Collection<int, SummativeDesignation>> */
        return $this->memo['designations'] ??= SummativeDesignation::query()
            ->with('subject')
            ->whereIn('school_class_id', $this->designableClasses()->keys()->all())
            ->get()
            ->groupBy(fn (SummativeDesignation $designation): int => (int) $designation->school_class_id);
    }

    /** @return array<int, string> */
    private function classOptions(): array
    {
        $options = [];

        foreach ($this->designableClasses() as $class) {
            $options[(int) $class->getKey()] = trim($class->name.' '.($class->section ?? ''))
                .' · '.$this->summativeLabelFor($class);
        }

        return $options;
    }

    /**
     * Disciplinele oferite: cele care se predau la CEL PUȚIN una dintre treptele claselor alese.
     * Perechile nepotrivite se filtrează oricum la aplicare și se raportează în previzualizare.
     *
     * @return array<int, string>
     */
    private function subjectOptionsFor(mixed $classIds): array
    {
        $classes = $this->designableClasses()->only(is_array($classIds) ? array_map(intval(...), $classIds) : []);

        if ($classes->isEmpty()) {
            return [];
        }

        $grades = $classes->map(fn (SchoolClass $class): int => (int) $class->grade_level)->unique()->all();

        $options = [];

        foreach (Subject::query()->orderBy('report_order')->orderBy('name')->get() as $subject) {
            foreach ($grades as $grade) {
                if ($subject->coversGrade($grade)) {
                    $options[(int) $subject->getKey()] = ContentTranslator::subject((string) $subject->name);

                    break;
                }
            }
        }

        return $options;
    }

    // ── Contractul secțiunilor de configurare pe an ─────────────────────────────────────────

    protected function yearRecordCounts(): Collection
    {
        return SummativeDesignation::query()
            ->toBase()
            ->join('school_classes', 'school_classes.id', '=', 'summative_designations.school_class_id')
            ->selectRaw('school_classes.academic_year_id AS year_id, COUNT(*) AS aggregate')
            ->groupBy('school_classes.academic_year_id')
            ->pluck('aggregate', 'year_id')
            ->map(fn ($count): int => (int) $count);
    }

    protected function constrainToYear(Builder $query, int $yearId): void
    {
        $query->whereHas('schoolClass', fn (Builder $q) => $q->where('academic_year_id', $yearId));
    }

    /**
     * Tabelul se randează DOAR în contextul unei clase, deci contextul de an primește și
     * constrângerea de clasă — validată, ca un `?clasa=` străin să nu poată lărgi vederea.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyYearContext(Builder $query): Builder
    {
        $yearId = $this->activeYearId();

        if ($yearId !== null) {
            $this->constrainToYear($query, $yearId);
        }

        $class = $this->activeClass();

        if ($class !== null) {
            $query->where('school_class_id', $class->getKey());
        }

        return $query;
    }
}
