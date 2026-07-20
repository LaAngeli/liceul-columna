<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Enums\Weekday;
use App\Filament\Resources\Lessons\LessonResource;
use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\ContentTranslator;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Orarul structurat, pe CLASE (navigatorul de configurare, 2026-07-16): pastile pe ani →
 * cardurile claselor anului — cu numărul de lecții/săptămână și avertisment „fără orar" unde
 * e gol (clasa se configurează de aici) → GRILA SĂPTĂMÂNALĂ a clasei (restructurare 2026-07-20).
 *
 * De ce grilă și nu tabel: un orar este o matrice zi × oră — exact forma în care îl gândește
 * dirigintele și îl vede familia. Tabelul plat pagina orarul la 10 rânduri (o săptămână are
 * ~25-30), rupea ziua în două pagini și făcea golurile INVIZIBILE: lipsa lecției a 2-a de marți
 * nu se vede într-o listă, dar sare în ochi într-o grilă. Lista rămâne vedere secundară
 * (`?vedere=lista`) pentru filtre și operațiuni pe rânduri.
 */
class ListLessons extends ListRecords
{
    protected static string $resource = LessonResource::class;

    protected string $view = 'filament.catalog.lessons-navigator';

    /** Anul școlar activ (id „dorit" din URL, validat la citire). */
    #[Url(as: 'an', except: null)]
    public ?string $yearParam = null;

    /** Clasa al cărei orar e deschis (validată la citire). */
    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    /** Vederea în contextul clasei: implicit grila; `lista` = tabelul clasic (filtre, rânduri). */
    #[Url(as: 'vedere', except: null)]
    public ?string $viewParam = null;

    /** @var Collection<int|string, int>|null */
    private ?Collection $classCountsByYearMemo = null;

    private SchoolClass|false|null $activeClassMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Din orarul unei clase, adăugarea vine pre-completată (an + clasă).
                ->url(function (): string {
                    $class = $this->activeClass();

                    return LessonResource::getUrl('create', $class !== null
                        ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey()]
                        : []);
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openYear(int|string $id): void
    {
        if ($this->classCountsByYear()->has((int) $id)) {
            $this->yearParam = (string) (int) $id;
        }
    }

    public function openClass(int|string $id): void
    {
        if (SchoolClass::query()->whereKey((int) $id)->exists()) {
            $this->classParam = (string) (int) $id;
            $this->activeClassMemo = null;
        }
    }

    public function leaveClass(): void
    {
        $this->classParam = null;
        $this->viewParam = null;
        $this->activeClassMemo = null;
    }

    public function showListView(): bool
    {
        return $this->viewParam === 'lista';
    }

    public function openGridView(): void
    {
        $this->viewParam = null;
    }

    public function openListView(): void
    {
        $this->viewParam = 'lista';
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

        $currentYearId = Term::query()->where('is_current', true)->value('academic_year_id');

        if ($currentYearId !== null && $visible->has((int) $currentYearId)) {
            return (int) $currentYearId;
        }

        $newest = $visible->keys()->map(fn ($id): int => (int) $id)->sortDesc()->first();

        return $newest;
    }

    /** Constrângerea lecțiilor pe clasa activă (apelată din LessonsTable). */
    public function hasClassContext(): bool
    {
        return $this->activeClass() !== null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyClassContext(Builder $query): Builder
    {
        $class = $this->activeClass();

        return $class !== null
            ? $query->where('school_class_id', $class->getKey())
            : $query;
    }

    // ── Grila săptămânală ───────────────────────────────────────────────────────────────────

    /**
     * Orarul clasei active ca matrice zi × lecție — datele pentru vederea implicită.
     *
     * Null când nu e nimic de arătat: cititorul unei clase fără orar primește empty-state-ul, nu o
     * grilă goală. SCRIITORUL primește însă întotdeauna scheletul (min. 7 rânduri): golurile sunt
     * chiar suprafața lui de lucru — fiecare celulă liberă e un link de adăugare pre-completat cu
     * clasa, ziua și numărul lecției, ca formularul să nu mai ceară nimic de re-ales.
     *
     * @return array{
     *     days: list<array{value: int, label: string, short: string, count: int}>,
     *     slots: list<int>,
     *     cells: array<int, array<int, array{subject: string, teacher: string|null, room: string|null, edit_url: string|null}>>,
     *     times: array<int, array{label: string, start: string, end: string}>,
     *     today: int|null,
     *     holiday_today: bool,
     *     current_slot: int|null,
     *     next_slot: int|null,
     *     can_write: bool,
     *     lessons: int,
     *     subjects: int
     * }|null
     */
    public function timetableGrid(): ?array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return null;
        }

        // Prin query-ul RESURSEI, nu pe model: perimetrul profesorului (doar clasele lui) se aplică
        // și aici — cardurile sunt deja filtrate (visibleClassIds), dar URL-ul rămâne o cale de
        // intrare directă, iar o clasă străină trebuie să apară GOALĂ, nu cu lecțiile ei.
        $lessons = LessonResource::getEloquentQuery()
            ->where('school_class_id', $class->getKey())
            ->with(['subject', 'teacher'])
            ->get();

        $canWrite = LessonResource::canCreate();

        if ($lessons->isEmpty() && ! $canWrite) {
            return null;
        }

        $cells = [];
        $perDay = [];
        $maxSlot = 0;
        $saturday = false;

        foreach ($lessons as $lesson) {
            // Query-ul resursei e tipat generic (Builder<Model>) — îngustare reală, nu supresie.
            if (! $lesson instanceof Lesson) {
                continue;
            }

            $day = $lesson->day_of_week->value;
            $slot = (int) $lesson->lesson_number;
            $maxSlot = max($maxSlot, $slot);
            $saturday = $saturday || $day === Weekday::Saturday->value;
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;

            $cells[$day][$slot] = [
                'subject' => $lesson->subject !== null
                    ? ContentTranslator::subject((string) $lesson->subject->name)
                    : (string) __('panel.common.dash'),
                'teacher' => $lesson->teacher?->full_name,
                'room' => $lesson->room,
                'edit_url' => $canWrite ? LessonResource::getUrl('edit', ['record' => $lesson]) : null,
            ];
        }

        // Rânduri: până la ultima lecție folosită; scriitorul primește mereu și rânduri libere
        // (min. 7, plafonat la 8 — limita formularului), ca orarul să crească din grilă.
        $slotCount = $canWrite
            ? min(max($maxSlot + 1, 7), 8)
            : max($maxSlot, 1);

        // Luni–vineri mereu; sâmbăta doar dacă are lecții — o coloană veșnic goală e zgomot.
        $days = [];
        foreach (Weekday::cases() as $weekday) {
            if ($weekday->value <= Weekday::Friday->value || $saturday) {
                $days[] = [
                    'value' => $weekday->value,
                    'label' => $weekday->label(),
                    'short' => $weekday->short(),
                    'count' => (int) ($perDay[$weekday->value] ?? 0),
                ];
            }
        }

        $times = $this->slotTimes($class);

        // „Acum"/„Urmează" au sens doar azi, într-o zi de școală prezentă în grilă — și doar în
        // FUSUL ȘCOLII: orele din orare sunt locale, iar acum-ul aplicației e UTC (vezi
        // SchoolCalendar::TIMEZONE; la verificarea live, în UTC, „Acum" cădea pe lecția greșită).
        $now = SchoolCalendar::localNow();
        $todayValue = (int) $now->isoWeekday();
        $isSchoolDay = collect($days)->contains(fn (array $day): bool => $day['value'] === $todayValue);
        $holidayToday = $isSchoolDay && Holidays::isNonWorkingDay($now);
        $today = ($isSchoolDay && ! $holidayToday) ? $todayValue : null;

        $currentSlot = null;
        $nextSlot = null;

        if ($today !== null) {
            foreach ($times as $slot => $time) {
                if ($slot > $slotCount) {
                    continue;
                }

                $start = $now->copy()->setTimeFromTimeString($time['start']);
                $end = $now->copy()->setTimeFromTimeString($time['end']);

                if ($now->between($start, $end)) {
                    $currentSlot = $slot;
                } elseif ($now->lt($start) && ($nextSlot === null || $slot < $nextSlot)) {
                    $nextSlot = $slot;
                }
            }

            // Un singur marcaj: în timpul unei lecții, „urmează" e zgomot.
            if ($currentSlot !== null) {
                $nextSlot = null;
            }
        }

        return [
            'days' => $days,
            'slots' => range(1, $slotCount),
            'cells' => $cells,
            'times' => $times,
            'today' => $today,
            'holiday_today' => $holidayToday,
            'current_slot' => $currentSlot,
            'next_slot' => $nextSlot,
            'can_write' => $canWrite,
            'lessons' => $lessons->count(),
            'subjects' => $lessons->pluck('subject_id')->unique()->count(),
        ];
    }

    /** Linkul de adăugare al unei celule libere: clasa, ziua și lecția vin gata completate. */
    public function createSlotUrl(int $day, int $slot): string
    {
        $class = $this->activeClass();

        return LessonResource::getUrl('create', $class !== null
            ? ['an' => $class->academic_year_id, 'clasa' => $class->getKey(), 'zi' => $day, 'lectie' => $slot]
            : []);
    }

    /**
     * Intervalele orare ale lecțiilor, CITITE din orarul publicat al clasei — etichetele rândurilor
     * au forma „Lecția N HH.MM – HH.MM". Sursa e cea văzută de familii; grila nu inventează ore.
     * Fără orar publicat (sau fără ore în etichete), grila afișează doar numerele lecțiilor.
     *
     * @return array<int, array{label: string, start: string, end: string}>
     */
    private function slotTimes(SchoolClass $class): array
    {
        $schedule = $class->lessonsSchedule;

        if ($schedule === null || ! $schedule->is_public) {
            return [];
        }

        $times = [];

        foreach ((array) $schedule->rows as $row) {
            $row = array_values((array) $row);
            // Diacritice legacy cu sedilă (ş/ţ) → formă canonică, ca la import.
            $label = strtr(trim((string) ($row[0] ?? '')), ['ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']);

            if (preg_match('/^Lecția\s+(\d+)/iu', $label, $slot) !== 1) {
                continue;
            }

            if (preg_match('/(\d{1,2})[.:](\d{2})\s*[–—-]\s*(\d{1,2})[.:](\d{2})/u', $label, $time) !== 1) {
                continue;
            }

            $times[(int) $slot[1]] = [
                'label' => sprintf('%02d:%02d – %02d:%02d', (int) $time[1], (int) $time[2], (int) $time[3], (int) $time[4]),
                'start' => sprintf('%02d:%02d', (int) $time[1], (int) $time[2]),
                'end' => sprintf('%02d:%02d', (int) $time[3], (int) $time[4]),
            ];
        }

        return $times;
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Pastilele anilor cu clase, badge = lecțiile programate în anul respectiv.
     *
     * @return array<int, array{id: int, label: string, count: int}>
     */
    public function yearPills(): array
    {
        $classCounts = $this->classCountsByYear();

        if ($classCounts->isEmpty()) {
            return [];
        }

        $lessonCounts = Lesson::query()
            ->toBase()
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id');

        return AcademicYear::query()
            ->whereKey($classCounts->keys()->all())
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicYear $year): array => [
                'id' => (int) $year->id,
                'label' => (string) $year->name,
                'count' => (int) ($lessonCounts->get($year->id) ?? 0),
            ])
            ->all();
    }

    /**
     * Cardurile claselor anului activ: lecțiile pe săptămână; „fără orar" = avertisment
     * (de aici se configurează).
     *
     * @return array<int, array{id: int, title: string, subtitle: string|null, stats: array<int, string>, badge: string|null}>
     */
    public function classCards(): array
    {
        $yearId = $this->activeYearId();

        if ($yearId === null) {
            return [];
        }

        $classes = SchoolClass::query()
            ->with('homeroomTeacher')
            ->where('academic_year_id', $yearId)
            ->when($this->visibleClassIds() !== null, fn (Builder $query): Builder => $query->whereIn('id', $this->visibleClassIds()))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $lessonCounts = Lesson::query()
            ->toBase()
            ->selectRaw('school_class_id, COUNT(*) AS aggregate')
            ->whereIn('school_class_id', $classes->pluck('id')->all())
            ->groupBy('school_class_id')
            ->pluck('aggregate', 'school_class_id');

        $cards = [];

        foreach ($classes as $class) {
            $lessons = (int) ($lessonCounts->get($class->id) ?? 0);

            $cards[] = [
                'id' => (int) $class->id,
                'title' => trim($class->name.' '.($class->section ?? '')),
                'subtitle' => $class->homeroomTeacher?->full_name,
                'stats' => [
                    $lessons > 0
                        ? (string) trans_choice('panel.config_nav.lessons_per_week', $lessons, ['count' => $lessons])
                        : (string) __('panel.config_nav.no_timetable'),
                ],
                'badge' => $lessons === 0 ? (string) __('panel.config_nav.no_timetable_badge') : null,
            ];
        }

        return $cards;
    }

    public function configHint(): string
    {
        return (string) __('panel.config_nav.lessons_hint');
    }

    /**
     * PERIMETRUL cardurilor: administrația vede toate clasele; profesorul/dirigintele — doar pe
     * ale lui (aceeași sursă unică folosită de SchoolClassResource). Prins la verificarea live:
     * profesorul vedea cardurile tuturor claselor, cu numărul de lecții, dar la click primea
     * empty state — un rând de uși care nu se deschid. Null = nerestricționat.
     *
     * @return list<int>|null
     */
    private function visibleClassIds(): ?array
    {
        $user = auth('web')->user();

        if (! $user || $user->isAdministrator()) {
            return null;
        }

        return $user->teacher?->visibleSchoolClassIds() ?? [];
    }

    /** @return Collection<int|string, int> */
    private function classCountsByYear(): Collection
    {
        return $this->classCountsByYearMemo ??= SchoolClass::query()
            ->toBase()
            ->when($this->visibleClassIds() !== null, fn ($query) => $query->whereIn('id', $this->visibleClassIds()))
            ->selectRaw('academic_year_id, COUNT(*) AS aggregate')
            ->groupBy('academic_year_id')
            ->pluck('aggregate', 'academic_year_id')
            ->map(fn ($count): int => (int) $count);
    }
}
