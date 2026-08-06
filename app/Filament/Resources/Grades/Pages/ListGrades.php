<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Enums\Calificativ;
use App\Enums\CorrectionStatus;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Enums\UserRole;
use App\Filament\Concerns\HasCatalogNavigator;
use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Concerns\WritesGradesFromDay;
use App\Filament\Contracts\CatalogNavigator;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\ContentTranslator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Pagina „Note" nu mai e o listă plată: se navighează prin meniu (clase / discipline /
 * profesori / perioade → entitate → tabel în context). Vezi HasCatalogNavigator.
 * Bara temporală ({@see HasTimeNavigator}) filtrează pe data acordării notei.
 *
 * HARTA NOTELOR (cerința beneficiarului, 05.08.2026 — sora hărții absențelor): în context de
 * CLASĂ, pagina arată elevi × zile, cu VALOAREA notei pe pastilă. Spre deosebire de absențe,
 * aici nu există mod-numărătoare pe „Toate": o pastilă „3" s-ar citi ca nota 3 — valoarea e
 * informația însăși, disciplina stă în hover și în meniul pastilei. Harta MONITORIZEAZĂ și
 * rezolvă excepțiile (anulare / solicitare de corecție); introducerea notelor rămâne DOAR în
 * Catalogul Electronic — un singur canal de scriere, cu gărzile lui.
 *
 * Disciplina e MEREU aleasă (pastila „Toate" nu există aici, decizia 05.08.2026), iar coloana
 * Total arată Note / Sub 5 / MEDIA — media OFICIALĂ din term_averages (semestrul curent), nu o
 * medie aritmetică a perioadei filtrate: cu disciplina unică, cifra oficială e legitimă și pe
 * hartă. Detalii: docs/PLAN-HARTA-NOTELOR.md.
 */
class ListGrades extends ListRecords implements CatalogNavigator
{
    use HasCatalogNavigator;
    use HasTimeNavigator;
    use WritesGradesFromDay;

    protected static string $resource = GradeResource::class;

    protected string $view = 'filament.catalog.list-with-navigator';

    /** Forma vederii în context de clasă: null = harta (implicit), 'lista' = tabelul clasic. */
    #[Url(as: 'forma', except: null)]
    public ?string $gradeView = null;

    /**
     * FILTRUL DE TIP al hărții (cerința beneficiarului, 05.08.2026) — același mecanism ca în
     * Catalogul Electronic: se vede UN tip odată, deci coloana unei zile înseamnă ceva.
     *
     * Fără „toate", ca în borderou: amestecul de curente, ESI și teze pe același rând e chiar
     * starea din care nu se poate urmări „colonița" unei evaluări. Media din coloana Total rămâne
     * cea OFICIALĂ (toate tipurile, cu ponderare) — filtrul taie ce se VEDE, nu cum se calculează.
     */
    #[Url(as: 'tip_note', except: self::DEFAULT_GRADE_TYPE)]
    public string $gradeTypeFilter = self::DEFAULT_GRADE_TYPE;

    public const DEFAULT_GRADE_TYPE = 'curenta';

    /** @var array<string, mixed> memoizare per-request a hărții (blade-ul o citește de mai multe ori) */
    private array $gradeMapMemo = [];

    /** Vara, „azi" nu e zi de școală: registrul se deschide pe ultima zi de curs, nu pe un tabel gol. */
    protected function anchorsToSchoolYear(): bool
    {
        return true;
    }

    /**
     * Notele se deschid pe LUNA curentă — aceeași decizie ca la Absențe și la borderou
     * (04.08.2026): luna e perioada de lucru și ține harta la un număr de coloane parcurs
     * dintr-o privire; „Toate" rămâne la o pastilă distanță.
     */
    protected function defaultTimeMode(): ?string
    {
        return 'luna';
    }

    protected function timeDateExpression(): string|Expression
    {
        return 'graded_on';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Contextul curent (clasa / disciplina) pre-completează formularul de adăugare.
            CreateAction::make()
                ->url(fn (): string => GradeResource::getUrl('create', $this->catalogCreateUrlParameters())),
        ];
    }

    protected function catalogBaseQuery(): Builder
    {
        return GradeResource::getEloquentQuery();
    }

    protected function catalogCountableQuery(): Builder
    {
        // Numărătorile cardurilor reflectă doar notele ACTIVE (anulatele rămân în tabel, cu filtru).
        return GradeResource::getEloquentQuery()->whereNull('annulled_at');
    }

    protected function catalogDateColumn(): string
    {
        return 'graded_on';
    }

    // ── Harta clasei (elevi × zile) ─────────────────────────────────────────────────────────

    /**
     * Pe Note, disciplina e MEREU aleasă (decizia beneficiarului, 05.08.2026): pastila „Toate"
     * dispare din rândul de discipline, iar la intrarea în contextul clasei se alege automat
     * primul chip. Cu disciplina unică, media oficială a semestrului devine legitimă în coloana
     * Total — exact ce amestecul de discipline interzicea.
     */
    public function catalogChipsIncludeAll(): bool
    {
        return false;
    }

    /**
     * Rulează înaintea FIECĂREI randări (mount, deschidere de clasă, comutare de chip): dacă
     * clasa e în context fără disciplină, se alege automat primul chip — bara de context și harta
     * citesc deci mereu un context complet, fără să suprascriem metode din trait.
     */
    public function rendering(): void
    {
        $this->ensureSubjectContext();
    }

    /** Fără disciplină în contextul clasei → primul chip (aceeași ordine ca pe ecran). */
    private function ensureSubjectContext(): void
    {
        if ($this->catalogActiveDimension() !== 'clase'
            || $this->catalogClassIdInContext() === null
            || $this->catalogSubjectIdInContext() !== null) {
            return;
        }

        $chips = $this->catalogChips();

        if ($chips !== []) {
            $this->catalogSubject = (string) $chips[0]['id'];
            $this->catalogNavMemo = [];
        }
    }

    /** Harta se arată doar în context de CLASĂ — pe celelalte dimensiuni rămâne tabelul. */
    public function showsGradeMap(): bool
    {
        return $this->catalogClassIdInContext() !== null && $this->gradeView !== 'lista';
    }

    public function setGradeView(?string $view): void
    {
        $this->gradeView = $view === 'lista' ? 'lista' : null;
    }

    /**
     * Tipul ACTIV, validat: valoarea din URL e o dorință, nu un adevăr — un `?tip_note=teza1`
     * ar fi produs o hartă goală fără explicație, în loc să cadă pe implicit.
     */
    public function activeGradeType(): EvaluationType
    {
        return EvaluationType::tryFrom($this->gradeTypeFilter) ?? EvaluationType::from(self::DEFAULT_GRADE_TYPE);
    }

    /**
     * Tipurile de evaluare, cu eticheta CICLULUI clasei deschise (ESS în gimnaziu, teză în liceu)
     * — aceeași sursă ca în borderou, deci aceleași cuvinte pe ambele ecrane.
     *
     * @return array<string, string>
     */
    public function gradeTypeOptions(): array
    {
        $cycle = $this->gradeMapCycle();
        $options = [];

        foreach (EvaluationType::cases() as $type) {
            $options[$type->value] = $type->labelForCycle($cycle);
        }

        return $options;
    }

    /** Schimbarea tipului invalidează memoizarea hărții (altfel s-ar reafișa setul vechi). */
    public function updatedGradeTypeFilter(): void
    {
        $this->gradeMapMemo = [];
    }

    /** Ciclul clasei din context — decide eticheta sumativei (ESS vs teză). */
    private function gradeMapCycle(): ?SchoolCycle
    {
        $class = $this->resolvedClass();

        return $class !== null ? SchoolCycle::fromGradeLevel((int) $class->grade_level) : null;
    }

    /**
     * Datele hărții, gata de randat.
     *
     * Interogarea e EXACT cea a tabelului ({@see catalogBaseQuery} + {@see applyCatalogContext}),
     * restrânsă la notele ACTIVE: cele anulate nu contează la medii și nu apar în cabinet, deci
     * nici pe hartă — excepțiile se văd în listă (gri, cu motiv). Rândurile cuprind TOȚI elevii
     * înscriși în clasă, alfabetic, ca la absențe: rândul gol e o informație.
     *
     * Fiecare pastilă poartă DOAR pârghiile privitorului (edit_url / can_annul / can_request),
     * calculate pe server cu ACELEAȘI gărzi ca tabelul ({@see GradesTable::teacherTeachesGrade},
     * {@see GradesTable::canRequestCorrectionFor}) — nimic care să ducă în 403.
     *
     * Coloana Total (redesign 05.08.2026, la cererea beneficiarului): Note / Sub 5 / MEDIA —
     * media e cea OFICIALĂ din term_averages (semestrul curent, disciplina contextului), nu o
     * medie aritmetică a perioadei; cu disciplina mereu unică, afișarea ei a devenit legitimă.
     *
     * @return array{
     *     days: list<array{iso: string, day: string, weekday: string, count: int}>,
     *     rows: list<array{student: Student, cells: array<string, list<array<string, mixed>>>, totals: array{total: int, below: int, average: string|null}}>,
     *     canAct: bool,
     * }
     */
    public function gradeMap(): array
    {
        if ($this->gradeMapMemo !== []) {
            /** @var array{days: list<array{iso: string, day: string, weekday: string, count: int}>, rows: list<array{student: Student, cells: array<string, list<array<string, mixed>>>, totals: array{total: int, below: int, average: string|null}}>, canAct: bool} */
            return $this->gradeMapMemo;
        }

        $classId = (int) $this->catalogClassIdInContext();

        // Ciclul clasei decide eticheta sumativei (ESS în gimnaziu, teză în liceu) — o singură
        // dată, nu per notă.
        $cycle = $this->gradeMapCycle();

        $records = $this->applyCatalogContext($this->catalogBaseQuery())
            ->whereNull('annulled_at')
            // Filtrul de tip lovește INTEROGAREA, nu doar afișarea: zilele fără nota tipului ales
            // dispar din antet, deci coloana rămasă chiar e „colonița" acelei evaluări.
            ->where('evaluation_type', $this->activeGradeType()->value)
            ->with(['subject:id,name,abbreviation,grading_type', 'student:id,first_name,last_name'])
            ->withCount(['corrections as pending_corrections_count' => fn ($q) => $q->where('status', CorrectionStatus::Pending)])
            ->orderBy('graded_on')
            ->orderBy('id')
            ->get();

        /** @var array<int, Grade> $grades */
        $grades = [];
        /** @var array<string, int> $countsByDay */
        $countsByDay = [];

        foreach ($records as $record) {
            if (! $record instanceof Grade) {
                continue;
            }

            $grades[] = $record;
            $iso = $record->graded_on->toDateString();
            $countsByDay[$iso] = ($countsByDay[$iso] ?? 0) + 1;
        }

        // ZILELE DE LECȚIE ale perioadei intră pe hartă și GOALE (05.08.2026): harta e acum și
        // suprafață de scriere — lecția de azi, care abia își așteaptă notele, trebuie să aibă
        // coloană și celule de deschis. Ziua fără nicio notă rămâne, în plus, o informație.
        foreach ($this->lessonDayIsosInRange() as $iso) {
            $countsByDay[$iso] ??= 0;
        }

        ksort($countsByDay);

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
        /** @var array<int, array{total: int, below: int}> $totalsByStudent */
        $totalsByStudent = [];

        foreach ($grades as $grade) {
            $studentId = (int) $grade->student_id;
            $iso = $grade->graded_on->toDateString();

            $chip = $this->gradeChip($grade, $cycle);
            $cellsByStudent[$studentId][$iso][] = $chip;

            $totals = $totalsByStudent[$studentId] ?? ['total' => 0, 'below' => 0];
            $totals['total']++;
            $totals['below'] += $chip['below'] ? 1 : 0;
            $totalsByStudent[$studentId] = $totals;
        }

        // Media OFICIALĂ a semestrului curent la disciplina contextului — o singură interogare,
        // pe toți elevii. Sursa e term_averages (motorul cu ponderare + trunchiere), nu un calcul
        // pe perioada filtrată.
        $averages = $this->officialAverages($classId);

        $rows = [];

        foreach ($this->gradeMapStudents($classId, $grades) as $student) {
            $totals = $totalsByStudent[$student->id] ?? ['total' => 0, 'below' => 0];
            $totals['average'] = $averages[$student->id] ?? null;

            $rows[] = [
                'student' => $student,
                'cells' => $cellsByStudent[$student->id] ?? [],
                'totals' => $totals,
            ];
        }

        // `canAct` = există MĂCAR o pârghie pentru privitor (subtitlul hărții o spune cinstit:
        // cine doar citește nu e invitat să „apese").
        $user = auth('web')->user();
        $canAct = $user instanceof User
            && ($user->canAdministerCatalog() || $user->teacher !== null);

        return $this->gradeMapMemo = [
            'days' => $days,
            'rows' => $rows,
            'canAct' => $canAct,
        ];
    }

    /**
     * O notă, redusă la ce randează celula. VALOAREA e eticheta (nota individuală e ÎNTREAGĂ
     * 1–10; disciplinele cu calificativ arată calificativul). Culoarea spune pragul — sub 5 =
     * roșu; accentul chihlimbar = sumativa (aceeași convenție ca în cabinet: „nota pe fond
     * galben este teza"). Pârghiile sunt DOAR ale privitorului.
     *
     * @return array<string, mixed>
     */
    private function gradeChip(Grade $grade, ?SchoolCycle $cycle): array
    {
        $user = auth('web')->user();
        $isAdmin = $user instanceof User && $user->canAdministerCatalog();
        $teaches = GradesTable::teacherTeachesGrade($grade);

        $numeric = $grade->subject?->grading_type === GradingType::Numeric;
        // Nota individuală e ÎNTREAGĂ (1–10) — castul zecimal al coloanei (9.00) nu are ce căuta
        // pe pastilă; zecimalele aparțin DOAR mediilor.
        $label = $numeric
            ? ($grade->value !== null ? (string) (int) $grade->value : '—')
            : (string) ($grade->calificativ ?? '—');
        $below = $numeric && $grade->value !== null && (int) $grade->value < 5;
        $summative = $grade->evaluation_type !== EvaluationType::Curenta;
        $pending = $grade->hasPendingCorrection();

        $subjectLabel = ContentTranslator::subject((string) ($grade->subject->name ?? ''));
        $typeLabel = $grade->evaluation_type->labelForCycle($cycle);

        return [
            'id' => (int) $grade->id,
            'label' => $label,
            'below' => $below,
            'summative' => $summative,
            'pending' => $pending,
            'subject_label' => $subjectLabel,
            'type_label' => $typeLabel,
            'title' => $subjectLabel.' — '.$typeLabel.($pending ? ' · '.__('panel.tables.grades.pending_correction_tooltip') : ''),
            // Pârghiile privitorului — calculate AICI, ca blade-ul să nu poată oferi fundături 403.
            'edit_url' => $isAdmin ? GradeResource::getUrl('edit', ['record' => $grade]) : null,
            'can_annul' => $isAdmin || $teaches,
            'can_request' => ! $isAdmin && ! $pending && GradesTable::canRequestCorrectionFor($grade),
        ];
    }

    /**
     * Media OFICIALĂ a semestrului CURENT la disciplina contextului, per elev — dintr-o singură
     * interogare. `value` e castul decimal:2 al motorului (trunchiere, nu rotunjire); se predă
     * blade-ului ca STRING, exact cum e stocată.
     *
     * @return array<int, string>
     */
    private function officialAverages(int $classId): array
    {
        $subjectId = $this->catalogSubjectIdInContext();

        if ($subjectId === null) {
            return [];
        }

        return TermAverage::query()
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereHas('term', fn ($q) => $q->where('is_current', true))
            ->pluck('value', 'student_id')
            ->map(fn ($v): string => (string) $v)
            ->all();
    }

    /**
     * Elevii rândurilor: toți cei înscriși ACTIV în clasă, alfabetic — plus autorii de note care
     * între timp au părăsit clasa (nota lor din perioadă trebuie să aibă rând).
     *
     * @param  array<int, Grade>  $grades
     * @return list<Student>
     */
    private function gradeMapStudents(int $classId, array $grades): array
    {
        $enrolledIds = Enrollment::query()
            ->where('school_class_id', $classId)
            ->whereNull('left_on')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id);

        $withGrades = collect($grades)->pluck('student_id')->map(fn ($id): int => (int) $id);

        return array_values(Student::query()
            ->whereKey($enrolledIds->merge($withGrades)->unique()->values())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->all());
    }

    // ── Panoul ZILEI, doar-NOTE (scrierea din hartă — cerința beneficiarului, 05.08.2026) ──

    /** Contextul de scriere al concernului ({@see WritesGradesFromDay}) = contextul navigatorului. */
    protected function dayGradeClass(): ?SchoolClass
    {
        return $this->resolvedClass();
    }

    protected function dayGradeSubject(): ?Subject
    {
        return $this->resolvedSubject();
    }

    /**
     * Poate INTRODUCE note la (clasa, disciplina) contextului — aceeași regulă ca borderoul:
     * autoritatea academică sau titularul perechii; în context Diriginte (F3) notarea rămâne act
     * de PROFESOR, deci comuti ca să notezi.
     */
    public function canEnterGrades(): bool
    {
        $user = auth('web')->user();
        $class = $this->dayGradeClass();
        $subject = $this->dayGradeSubject();

        if (! $user instanceof User || $class === null || $subject === null) {
            return false;
        }

        if ($user->canAdministerCatalog()) {
            return true;
        }

        if ($user->teachingContext() === UserRole::Diriginte) {
            return false;
        }

        return $user->teacher?->canGradeClassSubject((int) $class->getKey(), (int) $subject->getKey()) ?? false;
    }

    /** Tipul de notare al disciplinei contextului (numeric vs calificativ). */
    public function gradingType(): GradingType
    {
        $subject = $this->dayGradeSubject();

        return $subject === null ? GradingType::Numeric : $subject->grading_type;
    }

    /** Orice scriere reușită invalidează memoizarea — harta se redesenează cu realitatea. */
    protected function afterDayGradeWrite(): void
    {
        $this->gradeMapMemo = [];
    }

    /**
     * Panoul zilei al hărții: click pe celula (elev × zi) → modal cu NOTELE zilei și adăugarea
     * uneia noi. DOAR note — secțiunea e a notelor; absențele au harta și borderoul lor.
     * Modal, nu popover: celula trăiește în zona derulabilă cu overflow ascuns.
     */
    public function gradeDayPanelAction(): Action
    {
        return Action::make('gradeDayPanel')
            ->modalHeading(function (array $arguments): string {
                $student = Student::query()->find((int) ($arguments['student'] ?? 0));
                $iso = (string) ($arguments['iso'] ?? '');
                $name = $student instanceof Student ? $student->full_name : '';

                return trim($name.' · '.(preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) === 1
                    ? Carbon::parse($iso)->translatedFormat('j F Y')
                    : ''), ' ·');
            })
            ->modalContent(fn (array $arguments) => view('filament.catalog.partials.day-panel', [
                'panel' => $this->gradeDayPanel((int) ($arguments['student'] ?? 0), (string) ($arguments['iso'] ?? '')),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('panel.class_register.day_panel.close'))
            ->modalWidth('lg');
    }

    /**
     * Datele panoului doar-note. FĂRĂ cheile de absențe: partial-ul comun `day-panel` își randează
     * secțiunea de absențe numai când ele există în payload — aceeași vedere, două alcătuiri.
     *
     * Panoul NU aplică filtrul de tip al hărții: ziua se citește întreagă (toate tipurile,
     * inclusiv anulatele — gri), altfel „de ce nu-mi văd teza pusă adineauri" ar fi întrebarea zilei.
     *
     * @return array{
     *     student: Student|null,
     *     iso: string,
     *     grades: list<array{id: int, value: string, type_label: string, lesson: int|null, weighted: bool, pending: bool, annulled: bool, edit_url: string|null, can_annul: bool, can_request: bool}>,
     *     hours: array{timetable: list<int>},
     *     hour_slots: list<array{hour: int, timetable: bool, busy: string|null}>,
     *     default_hour: int|null,
     *     can_grade: bool,
     *     numeric: bool,
     *     grade_types: array<string, string>,
     * }
     */
    public function gradeDayPanel(int $studentId, string $iso): array
    {
        $empty = [
            'student' => null, 'iso' => $iso, 'grades' => [],
            'hours' => ['timetable' => []], 'hour_slots' => [], 'default_hour' => null,
            'can_grade' => false, 'numeric' => true, 'grade_types' => [],
        ];

        $class = $this->dayGradeClass();

        if ($class === null || $this->dayGradeSubject() === null
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return $empty;
        }

        // Elevul trebuie să fie AL CLASEI din context — un id străin nu deschide nimic.
        $student = Student::query()
            ->whereKey($studentId)
            ->whereHas('enrollments', fn ($q) => $q->where('school_class_id', $class->getKey())->whereNull('left_on'))
            ->first();

        if ($student === null) {
            return $empty;
        }

        // Stările orelor pentru selectorul de oră: și în panoul doar-note, orele cu ABSENȚE apar
        // ocupate — elevul absent la ora 4 nu poate primi notă pe ea (excluderea reciprocă e a
        // catalogului întreg, nu doar a borderoului).
        $hourStates = $this->dayHourStates((int) $student->getKey(), $iso);

        return [
            'student' => $student,
            'iso' => $iso,
            'grades' => $this->dayGradeEntriesFor((int) $student->getKey(), $iso, $this->gradeMapCycle()),
            'hours' => ['timetable' => $hourStates['timetable']],
            'hour_slots' => $hourStates['slots'],
            'default_hour' => $hourStates['default'],
            'can_grade' => $this->canEnterGrades()
                && ! Carbon::parse($iso)->startOfDay()->isAfter(Carbon::today()),
            'numeric' => $this->gradingType() === GradingType::Numeric,
            'grade_types' => $this->gradeTypeOptions(),
        ];
    }
}
