<?php

namespace App\Filament\Resources\Absences\Pages;

use App\Enums\AbsenceStatus;
use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Absences\AbsenceResource;
use App\Filament\Resources\Absences\Tables\AbsencesTable;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\ContentTranslator;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Pagina „Absențe" folosește același navigator drill-down ca „Note" (clase / discipline /
 * profesori / perioade → entitate → tabel în context). Vezi HasCatalogNavigator.
 * Bara temporală ({@see HasTimeNavigator}) filtrează pe data absenței.
 *
 * HARTA CLASEI (cerința beneficiarului, 04.08.2026): lista „un rând per absență" nu era
 * practică pentru lucru — triajul unei zile cu cinci absenți însemna cinci meniuri deschise pe
 * rând și nicio imagine de ansamblu pe elev. În context de CLASĂ, pagina afișează în loc de
 * tabel o hartă elevi × zile: fiecare absență e o pastilă colorată după statut, statutul se
 * fixează direct din pastilă (fără modal), iar capătul rândului totalizează per elev. Lista
 * clasică rămâne la un click (?forma=lista) — pentru căutare, export și operațiile pe fișă.
 */
class ListAbsences extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;
    use HasTimeNavigator;

    protected static string $resource = AbsenceResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    /** Forma vederii în context de clasă: null = harta (implicit), 'lista' = tabelul clasic. */
    #[Url(as: 'forma', except: null)]
    public ?string $absenceView = null;

    /** @var array<string, mixed> memoizare per-request a hărții (blade-ul o citește de mai multe ori) */
    private array $mapMemo = [];

    protected function timeDateExpression(): string|Expression
    {
        return 'occurred_on';
    }

    /**
     * Absențele se deschid pe LUNA curentă — aceeași decizie ca la catalogul de note
     * (04.08.2026): luna e perioada de triaj a dirigintelui și ține harta mereu sub pragul de
     * coloane. O absență veche rămasă fără statut nu se pierde: badge-ul din meniu o numără
     * indiferent de perioadă, iar „Toate" e la o pastilă distanță.
     */
    protected function defaultTimeMode(): ?string
    {
        return 'luna';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Contextul curent (clasa / disciplina) pre-completează formularul de consemnare.
            CreateAction::make()
                ->url(fn (): string => AbsenceResource::getUrl('create', $this->catalogCreateUrlParameters())),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return AbsenceResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        // Absențele nu au anulare (ca notele); cele șterse „moale" ies automat prin global scope.
        return AbsenceResource::getEloquentQuery();
    }

    protected function catalogDateColumn(): string
    {
        return 'occurred_on';
    }

    // ── Harta clasei (elevi × zile) ─────────────────────────────────────────────────────────

    /** Harta se arată doar în context de CLASĂ — pe celelalte dimensiuni rămâne tabelul. */
    public function showsAbsenceMap(): bool
    {
        return $this->catalogClassIdInContext() !== null && $this->absenceView !== 'lista';
    }

    public function setAbsenceView(?string $view): void
    {
        $this->absenceView = $view === 'lista' ? 'lista' : null;
    }

    /**
     * Datele hărții, gata de randat.
     *
     * Interogarea e EXACT cea a tabelului ({@see catalogBaseQuery} + {@see applyCatalogContext}):
     * aceleași drepturi, același context de clasă/disciplină, aceeași perioadă. Harta nu vede
     * nimic în plus față de listă — doar altfel așezat.
     *
     * Rândurile cuprind TOȚI elevii înscriși în clasă, nu doar pe cei cu absențe: zero absențe e
     * o informație (elevul care nu lipsește se vede), iar dirigintele își recunoaște clasa
     * întreagă, în aceeași ordine alfabetică precum catalogul.
     *
     * Zilele NU se plafonează (decizia beneficiarului, 04.08.2026): surplusul de coloane se
     * parcurge orizontal, cu săgețile din blade — numele elevului și totalurile rămân ancorate
     * la capete, deci lizibilitatea nu depinde de numărul de zile.
     *
     * MODUL GRUPAT (`grouped`): fara disciplina in context (filtrul „Toate"), celula unei zile
     * se afiseaza ca NUMARATOARE cu mini-lista absentelor la hover (decizia beneficiarului,
     * 04.08.2026) — pastilele per absenta redeveneau ambigue cand o zi amesteca discipline.
     * Cu disciplina aleasa, pastilele per absenta raman (doua ore de matematica = doua pastile).
     * Datele celulelor NU se schimba intre moduri — blade-ul decide forma; aici doar semnalul.
     *
     * @return array{
     *     days: list<array{iso: string, day: string, weekday: string, count: int}>,
     *     rows: list<array{student: Student, cells: array<string, list<array<string, mixed>>>, totals: array{total: int, motivated: int, unmotivated: int, pending: int}}>,
     *     grouped: bool,
     *     canStatus: bool,
     * }
     */
    public function absenceMap(): array
    {
        if ($this->mapMemo !== []) {
            /** @var array{days: list<array{iso: string, day: string, weekday: string, count: int}>, rows: list<array{student: Student, cells: array<string, list<array<string, mixed>>>, totals: array{total: int, motivated: int, unmotivated: int, pending: int}}>, grouped: bool, canStatus: bool} */
            return $this->mapMemo;
        }

        $classId = (int) $this->catalogClassIdInContext();

        // Interogarea vine netipată din resursă (Builder<Model>); bucla cu gardă `instanceof`
        // exprimă tipul VERIFICABIL, în loc să-l declare un `@var` inventat — aceeași regulă ca în
        // {@see \App\Models\Concerns\ScopedToTeachingCapacity}. Numărătorile pe zi se fac pe chei
        // de STRING, niciodată prin comparația lejeră Carbon ↔ string (capcană deja plătită).
        $records = $this->applyCatalogContext($this->catalogBaseQuery())
            ->with(['subject:id,name,abbreviation', 'student:id,first_name,last_name'])
            ->orderBy('occurred_on')
            ->get();

        /** @var array<int, Absence> $absences */
        $absences = [];
        /** @var array<string, int> $countsByDay */
        $countsByDay = [];

        foreach ($records as $record) {
            if (! $record instanceof Absence) {
                continue;
            }

            $absences[] = $record;
            $iso = $record->occurred_on->toDateString();
            $countsByDay[$iso] = ($countsByDay[$iso] ?? 0) + 1;
        }

        $days = [];

        foreach ($countsByDay as $iso => $count) {
            $days[] = [
                'iso' => $iso,
                'day' => Carbon::parse($iso)->translatedFormat('d.m'),
                'weekday' => mb_convert_case(Carbon::parse($iso)->translatedFormat('D'), MB_CASE_UPPER, 'UTF-8'),
                'count' => $count,
            ];
        }

        /** @var array<int, array<string, list<array<string, mixed>>>> $cellsByStudent */
        $cellsByStudent = [];
        /** @var array<int, array{total: int, motivated: int, unmotivated: int, pending: int}> $totalsByStudent */
        $totalsByStudent = [];

        foreach ($absences as $absence) {
            $studentId = (int) $absence->student_id;
            $iso = $absence->occurred_on->toDateString();

            $cellsByStudent[$studentId][$iso][] = $this->mapChip($absence);

            $totals = $totalsByStudent[$studentId] ?? ['total' => 0, 'motivated' => 0, 'unmotivated' => 0, 'pending' => 0];
            $totals['total']++;
            $totals[match ($absence->is_motivated) {
                true => 'motivated',
                false => 'unmotivated',
                null => 'pending',
            }]++;
            $totalsByStudent[$studentId] = $totals;
        }

        $rows = [];

        foreach ($this->mapStudents($classId, $absences) as $student) {
            $rows[] = [
                'student' => $student,
                'cells' => $cellsByStudent[$student->id] ?? [],
                'totals' => $totalsByStudent[$student->id] ?? ['total' => 0, 'motivated' => 0, 'unmotivated' => 0, 'pending' => 0],
            ];
        }

        return $this->mapMemo = [
            'days' => $days,
            'rows' => $rows,
            'grouped' => $this->catalogSubjectIdInContext() === null,
            'canStatus' => $this->viewerCanStatusClass($classId),
        ];
    }

    /**
     * Fixează statutul unei absențe DIN hartă — aceeași semantică precum butoanele rapide din
     * listă ({@see AbsencesTable}).
     *
     * Gărzile stau pe SERVER, nu în blade: absența se rezolvă prin interogarea scoped a paginii
     * (un id străin de context sau de drepturi nu se găsește deloc), iar dreptul de a statuta e
     * al dirigintelui clasei sau al administrației ({@see User::canMotivateAbsencesFor}) —
     * profesorul de disciplină consemnează, nu decide.
     */
    public function setAbsenceMapStatus(int $absenceId, string $status): void
    {
        $target = AbsenceStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        /** @var Absence|null $absence */
        $absence = $this->applyCatalogContext($this->catalogBaseQuery())
            ->whereKey($absenceId)
            ->first();

        $user = auth('web')->user();

        if ($absence === null || ! $user instanceof User || ! $user->canMotivateAbsencesFor((int) $absence->school_class_id)) {
            Notification::make()
                ->danger()
                ->title(__('absence_map.status_denied'))
                ->send();

            return;
        }

        $absence->update(['is_motivated' => $target->motivatedValue()]);

        $this->mapMemo = [];

        Notification::make()
            ->success()
            ->title(match ($target) {
                AbsenceStatus::Motivated => __('panel.tables.absences.status_set_motivated'),
                AbsenceStatus::Unmotivated => __('panel.tables.absences.status_set_unmotivated'),
                AbsenceStatus::Pending => __('absence_map.status_reset'),
            })
            ->send();
    }

    /**
     * Elevii rândurilor: înscrișii ACTIVI ai clasei ∪ elevii care au absențe în perioadă (un
     * elev plecat între timp nu-și pierde istoricul de pe hartă). Alfabetic, ca în catalog.
     *
     * @param  array<int, Absence>  $absences
     * @return list<Student>
     */
    private function mapStudents(int $classId, array $absences): array
    {
        /** @var array<int, Student> $students */
        $students = [];

        foreach ($absences as $absence) {
            if ($absence->student instanceof Student) {
                $students[(int) $absence->student->id] = $absence->student;
            }
        }

        $enrollments = Enrollment::query()
            ->where('school_class_id', $classId)
            ->whereNull('left_on')
            ->with('student:id,first_name,last_name')
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->student instanceof Student) {
                $students[(int) $enrollment->student->id] = $enrollment->student;
            }
        }

        // usort reindexează de la zero — lista e gata de întors ca atare.
        usort(
            $students,
            fn (Student $a, Student $b): int => strcoll((string) $a->last_name, (string) $b->last_name)
                ?: strcoll((string) $a->first_name, (string) $b->first_name),
        );

        return $students;
    }

    /**
     * O absență, redusă la ce randează celula: statut (culoarea), marcajul „A" și linkul fișei.
     * Blade-ul nu primește modelul întreg — doar ce afișează.
     *
     * Eticheta e MEREU același marcaj (cerința beneficiarului, 04.08.2026): abrevierile de
     * disciplină făceau harta pestriță și greu de citit dintr-o privire — un singur semn, ca „a"-ul
     * din catalogul de hârtie, lasă CULOAREA să spună statutul. Disciplina și statutul rămân la
     * hover (title) și în numele accesibil al pastilei.
     *
     * @return array<string, mixed>
     */
    private function mapChip(Absence $absence): array
    {
        $status = $absence->status();

        $subjectLabel = $absence->subject !== null
            ? ContentTranslator::subject((string) $absence->subject->name)
            : (string) __('absence_map.whole_day');

        return [
            'id' => (int) $absence->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'color' => $status->color(),
            'label' => (string) __('absence_map.marker'),
            // Disciplina SEPARAT de titlu: mini-lista zilei (modul „Toate") o afișează ca rând de
            // sine stătător — fiecare absență apare aparte, deci două ore de matematică din aceeași
            // zi = două rânduri „Matematică", nu unul singur.
            'subject_label' => $subjectLabel,
            'title' => $subjectLabel.' — '.$status->label(),
            'url' => AbsenceResource::getUrl('edit', ['record' => $absence]),
        ];
    }

    private function viewerCanStatusClass(int $classId): bool
    {
        $user = auth('web')->user();

        return $user instanceof User && $user->canMotivateAbsencesFor($classId);
    }
}
