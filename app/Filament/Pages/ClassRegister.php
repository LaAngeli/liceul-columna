<?php

namespace App\Filament\Pages;

use App\Enums\AbsenceStatus;
use App\Enums\Calificativ;
use App\Enums\CorrectionStatus;
use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Concerns\HasTimeNavigator;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use App\Support\Holidays;
use App\Support\SchoolCalendar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * CATALOGUL CLASEI (borderoul): toată clasa pe un ecran — elevi × zile, cu note, medii și
 * absențe — iar CELULA ZILEI e unitatea de interacțiune (restructurarea beneficiarului,
 * 05.08.2026): click pe (elev × zi) deschide PANOUL ZILEI, unde se citesc și se scriu notele și
 * absențele acelei zile, cu pârghiile privitorului. Vechea introducere în masă (coloanele „Notă
 * nouă"/„Absent", data globală, „Salvează tot") a fost ELIMINATĂ la cererea explicită a
 * beneficiarului — un singur mecanism de scriere, ancorat vizual în ziua pe care o atinge.
 *
 * SECURITATEA NU E RE-INVENTATĂ: fiecare scriere din panou trece prin ACELEAȘI traituri ca
 * formularele clasice ({@see EnforcesGradeScope}, {@see EnforcesAbsenceScope}) — semestrul
 * derivat din ZIUA celulei, fără viitor, fără an închis, anti-duplicat pe slotul de oră,
 * scope-ul titularului verificat pe server. Vizibilitatea urmează regula resurselor: profesorul
 * își vede (clasa, disciplina) lui, dirigintele toată clasa lui, administrația tot; AT nu are
 * acces la date academice. Scrierea trece prin MODELE — observerii recalculează mediile și
 * notifică familia.
 *
 * GEOMETRIA E FIXĂ (05.08.2026): coloana elevului și ancorele din dreapta (Media, Absențe) au
 * lățimi constante, sticky pe antet ȘI pe corp; zona zilelor ia tot restul, cu conținutul aliniat
 * la stânga — filtrarea nu mișcă nicio margine, doar conținutul dintre ele.
 */
class ClassRegister extends Page
{
    use EnforcesAbsenceScope;
    use EnforcesGradeScope;
    use HasTimeNavigator;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    /** Înaintea listelor Note (10) / Absențe: e unealta de zi cu zi a profesorului. */
    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'catalog-clasa';

    protected string $view = 'filament.catalog.class-register';

    #[Url(as: 'clasa', except: null)]
    public ?string $classParam = null;

    #[Url(as: 'disciplina', except: null)]
    public ?string $subjectParam = null;

    /**
     * FILTRUL DE TIP (cerința beneficiarului, 04.08.2026): notele stăteau una lângă alta, pe rândul
     * elevului, deci „colonița" unei zile — sau a ESS-ului — nu se putea urmări cu ochiul.
     *
     * Implicit „Curentă", iar „toate tipurile" NU e o opțiune: amestecul de curente, ESI și teze pe
     * același rând e chiar starea din care nu se putea citi nimic. Se alege mereu UN tip, deci
     * coloana înseamnă ceva. Perioada se alege din bara temporală comună ({@see HasTimeNavigator}),
     * identică cu cea din Note/Absențe/Teme.
     */
    #[Url(as: 'tip_note', except: self::DEFAULT_GRADE_TYPE)]
    public string $gradeTypeFilter = self::DEFAULT_GRADE_TYPE;

    public const DEFAULT_GRADE_TYPE = 'curenta';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.class_register.title');
    }

    public function getTitle(): string
    {
        $class = $this->activeClass();

        return $class !== null
            ? __('panel.class_register.title').' — '.trim($class->name.' '.($class->section ?? ''))
            : __('panel.class_register.title');
    }

    /** Aceeași poartă ca resursele de catalog: AT (fără date academice) și familia rămân afară. */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    // ── Context: clasă / disciplină / semestru ──────────────────────────────────────────────

    protected function viewer(): ?User
    {
        /** @var User|null */
        return auth('web')->user();
    }

    /** Fișa de profesor a privitorului — null pentru administrație (vede nescoped). */
    protected function viewerTeacher(): ?Teacher
    {
        $user = $this->viewer();

        return ($user !== null && ! $user->isAdministrator()) ? $user->teacher : null;
    }

    /**
     * Clasele dintre care se alege: profesorul — ale lui (predate + dirigenție), administrația —
     * toate. Preferăm anul CURENT; dacă profesorul nu are nimic în anul curent, cad pe tot ce are
     * (an vechi încă neînchis) — un borderou gol nu ajută pe nimeni.
     *
     * @return Collection<int, SchoolClass>
     */
    public function classOptions(): Collection
    {
        $teacher = $this->viewerTeacher();

        $base = SchoolClass::query()
            ->when($teacher !== null, fn ($q) => $q->whereIn('id', $teacher->contextSchoolClassIds($this->viewer()?->teachingContext()))) // contextul pedagogic activ (F3)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('section');

        $current = (clone $base)
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))
            ->get();

        return $current->isNotEmpty() ? $current : $base->get();
    }

    public function activeClass(): ?SchoolClass
    {
        $options = $this->classOptions();

        if ($this->classParam !== null && ctype_digit($this->classParam)) {
            $found = $options->firstWhere('id', (int) $this->classParam);

            if ($found instanceof SchoolClass) {
                return $found;
            }
        }

        // Fără parametru (sau parametru străin): prima clasă — profesorul aterizează direct
        // pe ceva utilizabil, nu pe un ecran de selecție.
        return $options->first();
    }

    /**
     * Disciplinele vizibile în clasa activă, cu drepturile per disciplină. Oglindesc scoping-ul
     * resurselor: profesorul — DOAR disciplinele lui; dirigintele — toate disciplinele clasei lui
     * (citire), dar notează doar la ale lui; administrația — toate.
     *
     * @return list<array{id: int, name: string, mine: bool}>
     */
    public function subjectOptions(): array
    {
        $class = $this->activeClass();

        if ($class === null) {
            return [];
        }

        $teacher = $this->viewerTeacher();
        $isHomeroom = $teacher !== null
            && in_array((int) $class->getKey(), $this->viewer()?->contextHomeroomClassIds() ?? [], true); // contextul pedagogic activ (F3)

        $rows = DB::table('teaching_assignments')
            ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->where('teaching_assignments.school_class_id', $class->getKey())
            ->whereNull('teaching_assignments.deleted_at')
            ->whereNull('subjects.deleted_at')
            ->when(
                $teacher !== null && ! $isHomeroom,
                fn ($q) => $q->where('teaching_assignments.teacher_id', $teacher->getKey()),
            )
            ->distinct()
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name']);

        /** @var list<array{id: int, name: string, mine: bool}> $options */
        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'mine' => $teacher !== null && $teacher->canGradeClassSubject((int) $class->getKey(), (int) $row->id),
            ];
        }

        // Disciplinele PROPRII întâi — la ele vine profesorul zilnic; alfabetic în interior.
        usort($options, fn (array $a, array $b): int => [$b['mine'], $a['name']] <=> [$a['mine'], $b['name']]);

        return $options;
    }

    public function activeSubject(): ?Subject
    {
        $options = $this->subjectOptions();

        if ($options === []) {
            return null;
        }

        $requested = ($this->subjectParam !== null && ctype_digit($this->subjectParam))
            ? (int) $this->subjectParam
            : null;

        foreach ($options as $option) {
            if ($option['id'] === $requested) {
                return Subject::query()->find($option['id']);
            }
        }

        return Subject::query()->find($options[0]['id']);
    }

    /**
     * Semestrele anului clasei — axa pe care se așază borderoul.
     *
     * @return Collection<int, Term>
     */
    public function termOptions(): Collection
    {
        $class = $this->activeClass();

        if ($class === null) {
            return new Collection;
        }

        return $class->academicYear?->terms()->orderBy('starts_on')->get() ?? new Collection;
    }

    /**
     * Semestrul VIZUALIZAT: cel curent al clasei (fallback: primul). Scrierea nu mai depinde de
     * el — fiecare notă/absență din panoul zilei își derivă semestrul din ZIUA celulei, pe server
     * ({@see EnforcesGradeScope}/{@see EnforcesAbsenceScope}); aparatul de introducere în masă,
     * cu data lui globală, a fost eliminat (cerința beneficiarului, 05.08.2026).
     */
    public function activeTerm(): ?Term
    {
        $terms = $this->termOptions();

        return $terms->firstWhere('is_current', true) ?? $terms->first();
    }

    // ── Drepturi pe contextul activ ─────────────────────────────────────────────────────────

    /** Poate INTRODUCE note la (clasa, disciplina) activă — autoritatea academică sau titularul. */
    public function canEnterGrades(): bool
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($user === null || $class === null || $subject === null) {
            return false;
        }

        if ($user->canAdministerCatalog()) {
            return true;
        }

        // Context Diriginte (multi-rol F3): notarea e un act de PROFESOR (doc pct. 5 — separarea
        // strictă). Chiar la disciplina proprie, comuți pe Profesor ca să notezi; aici rămân
        // vizualizarea întregii clase și consemnarea absențelor.
        if ($user->teachingContext() === UserRole::Diriginte) {
            return false;
        }

        return $user->teacher?->canGradeClassSubject((int) $class->getKey(), (int) $subject->getKey()) ?? false;
    }

    /** Poate CONSEMNA absențe: titularul disciplinei SAU dirigintele clasei (orice disciplină). */
    public function canRecordAbsences(): bool
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($user === null || $class === null || $subject === null) {
            return false;
        }

        if ($user->canAdministerCatalog()) {
            return true;
        }

        return $user->teacher?->canRecordAbsence((int) $class->getKey(), (int) $subject->getKey()) ?? false;
    }

    /**
     * Catalogul se deschide pe LUNA curentă, nu pe tot istoricul (cerința beneficiarului,
     * 04.08.2026): luna e perioada de lucru a profesorului și încape fără derulare. „Toate"
     * rămâne la o pastilă distanță — tot pe coloane, doar mai lat ({@see gradesAlignedByDate()}).
     */
    protected function defaultTimeMode(): ?string
    {
        return 'luna';
    }

    /** Vara, „azi" nu e zi de școală: registrul se deschide pe ultima zi de curs, nu pe un tabel gol. */
    protected function anchorsToSchoolYear(): bool
    {
        return true;
    }

    /**
     * FĂRĂ pastila „Toate" în bara de timp (decizia beneficiarului, 05.08.2026): tot istoricul
     * aducea aici mai multă confuzie decât folos — borderoul e unealta perioadei de LUCRU, iar
     * pentru arhivă există Note/Absențe. Un `?mod=toate` din URL cade pe luna implicită.
     */
    protected function timeIncludesAll(): bool
    {
        return false;
    }

    /** Bara temporală filtrează pe DATA notei — aceeași coloană ca în lista Note. */
    protected function timeDateExpression(): string|Expression
    {
        return 'graded_on';
    }

    public function gradingType(): GradingType
    {
        $subject = $this->activeSubject();

        return $subject === null ? GradingType::Numeric : $subject->grading_type;
    }

    // ── Rândurile borderoului ───────────────────────────────────────────────────────────────

    /**
     * Un rând per elev înmatriculat (alfabetic): notele semestrului pe disciplina activă
     * (cronologic, cu tip + dată), media semestrială oficială, sinteza absențelor. Patru
     * interogări pentru toată clasa — nu N+1.
     *
     * Grupele de engleză: dacă ALOCAREA PROPRIE a profesorului pe (clasă, disciplină) are grupă,
     * lista se restrânge la elevii grupei lui (realitatea predării pe grupe); privitorii fără
     * alocare proprie (diriginte, administrație) văd clasa întreagă.
     *
     * ZIUA e unitatea de lucru (cerința beneficiarului, 05.08.2026): fiecare notă și fiecare
     * absență își păstrează identitatea (id) și pârghiile privitorului, iar absențele intră pe
     * zile lângă note — două ore consecutive ale aceleiași discipline = două intrări distincte.
     *
     * @return list<array{
     *     student: Student,
     *     grades: list<array{id: int, value: string, weighted: bool, pending: bool, can_request: bool, edit_url: string|null, tooltip: string, iso: string}>,
     *     gradesByDate: array<string, list<array{id: int, value: string, weighted: bool, pending: bool, can_request: bool, edit_url: string|null, tooltip: string, iso: string}>>,
     *     absencesByDate: array<string, list<array{id: int, status: string, color: string, status_label: string, lesson: int|null}>>,
     *     average: string|null,
     *     absences: array{total: int, unmotivated: int, pending: int, dates: string}
     * }>
     */
    public function rows(): array
    {
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $term = $this->activeTerm();

        if ($class === null || $subject === null || $term === null) {
            return [];
        }

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('school_class_id', $class->getKey())
            ->whereNull('left_on')
            ->get();

        // Restrângerea pe grupa de engleză — doar din perspectiva titularului cu grupă.
        $group = $this->ownAssignmentGroup((int) $class->getKey(), (int) $subject->getKey());

        /** @var list<Student> $students */
        $students = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student instanceof Student) {
                continue;
            }

            // Elevii fără grupă stabilită rămân vizibili ambilor titulari — mai bine un elev
            // în plus pe listă decât unul dispărut din toate borderourile.
            $studentGroup = $student->getAttribute('english_group');

            if ($group !== null && $studentGroup !== null && (int) $studentGroup !== $group) {
                continue;
            }

            $students[] = $student;
        }

        usort($students, fn (Student $a, Student $b): int => [(string) $a->last_name, (string) $a->first_name] <=> [(string) $b->last_name, (string) $b->first_name]);

        $studentIds = array_map(fn (Student $student): int => (int) $student->getKey(), $students);

        // Filtrele de CITIRE lovesc interogarea, nu doar afișarea: tipul ales (mereu unul singur)
        // și perioada din bara temporală comună — aceeași ca în Note/Absențe/Teme.
        $gradeQuery = Grade::query()
            ->active()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('term_id', $term->getKey())
            ->whereIn('student_id', $studentIds)
            ->where('evaluation_type', $this->gradeTypeFilter);

        $grades = $this->applyTimeRange($gradeQuery)
            ->withCount(['corrections as pending_corrections_count' => fn ($q) => $q->where('status', CorrectionStatus::Pending)])
            ->orderBy('graded_on')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        $averages = TermAverage::query()
            ->where('subject_id', $subject->getKey())
            ->where('term_id', $term->getKey())
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        // Absențele trec prin ACEEAȘI perioadă ca notele (bara temporală, pe occurred_on):
        // zilele-uniune ale borderoului trebuie să vorbească despre aceeași fereastră de timp.
        $absences = $this->applyTimeRange(
            Absence::query()
                ->where('school_class_id', $class->getKey())
                ->where('subject_id', $subject->getKey())
                ->where('term_id', $term->getKey())
                ->whereIn('student_id', $studentIds),
            'occurred_on',
        )
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        // Eticheta sumativei diferă pe ciclu (ESS la gimnaziu, teză la liceu) — un singur cod,
        // două nume; borderoul trebuie să-l spună pe cel din clasa deschisă.
        $cycle = SchoolCycle::fromGradeLevel((int) $class->grade_level);

        // Pârghiile pe (clasă, disciplină) sunt ACELEAȘI pentru toate pastilele contextului —
        // se calculează o dată, cu gărzile partajate cu tabelul/hărțile.
        $rights = $this->dayRights();

        $rows = [];

        foreach ($students as $student) {
            $studentGrades = $grades->get($student->getKey(), new Collection);
            $studentAbsences = $absences->get($student->getKey(), new Collection);
            $average = $averages->get($student->getKey());

            /** @var list<array{id: int, value: string, weighted: bool, pending: bool, can_request: bool, edit_url: string|null, tooltip: string, iso: string}> $gradeChips */
            $gradeChips = [];
            /** @var array<string, list<array{id: int, value: string, weighted: bool, pending: bool, can_request: bool, edit_url: string|null, tooltip: string, iso: string}>> $byDate */
            $byDate = [];

            foreach ($studentGrades as $grade) {
                $displayValue = $grade->value !== null
                    ? (string) (int) (float) $grade->value
                    : (string) ($grade->calificativ ?? '—');

                $chip = [
                    'id' => (int) $grade->getKey(),
                    'pending' => $grade->hasPendingCorrection(),
                    // Cererea de corecție dispare cât timp există una în așteptare.
                    'can_request' => $rights['can_request'] && ! $grade->hasPendingCorrection(),
                    'edit_url' => $rights['is_admin'] ? GradeResource::getUrl('edit', ['record' => $grade]) : null,
                    'value' => $displayValue,
                    // Teza/ESI se disting prin culoare; tipul și DATA notei se citesc la survol.
                    // Formulare explicită („Nota 8 · Curentă · 20.07.2026”): prima variantă arăta
                    // doar „Curentă 20.07" și părea o a doua valoare, nu o descriere a notei.
                    'weighted' => $grade->evaluation_type !== EvaluationType::Curenta,
                    'tooltip' => (string) trans('panel.class_register.grade_tooltip', [
                        'value' => $displayValue,
                        'type' => $grade->evaluation_type->labelForCycle($cycle),
                        'date' => $grade->graded_on->format('d.m.Y'),
                    ]),
                    'iso' => $grade->graded_on->toDateString(),
                ];

                $gradeChips[] = $chip;
                // Cheia pe ZI, nu pe notă: două note în aceeași zi (rar, dar există) stau una sub
                // alta în aceeași celulă, ca să nu spargă alinierea coloanei.
                $byDate[$chip['iso']][] = $chip;
            }

            /** @var array<string, list<array{id: int, status: string, color: string, status_label: string, lesson: int|null}>> $absByDate */
            $absByDate = [];

            foreach ($studentAbsences as $absence) {
                $status = $absence->status();

                $absByDate[$absence->occurred_on->toDateString()][] = [
                    'id' => (int) $absence->getKey(),
                    'status' => $status->value,
                    'color' => $status->color(),
                    'status_label' => $status->label(),
                    // ORA lecției — identitatea care desparte două absențe ale aceleiași zile
                    // (ore consecutive); null = „ziua, fără oră precizată".
                    'lesson' => $absence->lesson_number,
                ];
            }

            $rows[] = [
                'student' => $student,
                'grades' => $gradeChips,
                'gradesByDate' => $byDate,
                'absencesByDate' => $absByDate,
                'average' => $average?->value !== null
                    ? number_format((float) $average->value, 2, ',', '')
                    : null,
                'absences' => [
                    'total' => $studentAbsences->count(),
                    // STRICT `=== false`: `->where(…, false)` compară LEJER, iar null == false —
                    // absențele încă fără statut ar fi numărate drept nemotivate.
                    'unmotivated' => $studentAbsences->filter(fn (Absence $absence): bool => $absence->is_motivated === false)->count(),
                    'pending' => $studentAbsences->filter(fn (Absence $absence): bool => $absence->is_motivated === null)->count(),
                    // Datele absențelor: ✓ motivată, ? încă fără statut — se citesc la survol pe contor.
                    'dates' => $studentAbsences
                        ->map(function (Absence $absence): string {
                            $marker = match ($absence->is_motivated) {
                                true => ' ✓',
                                false => '',
                                default => ' ?',
                            };

                            return $absence->occurred_on->format('d.m.Y').$marker;
                        })
                        ->implode(', '),
                ],
            ];
        }

        return $rows;
    }

    // ── Filtrul de tip (perioada vine din bara temporală comună) ────────────────────────────

    /**
     * Tipurile de evaluare, cu eticheta CICLULUI clasei deschise (ESS la gimnaziu, teză la liceu).
     * Fără „toate": vezi nota de la {@see $gradeTypeFilter}.
     *
     * @return array<string, string>
     */
    public function gradeTypeOptions(): array
    {
        $class = $this->activeClass();
        $cycle = $class !== null ? SchoolCycle::fromGradeLevel((int) $class->grade_level) : null;

        $options = [];

        foreach (EvaluationType::cases() as $type) {
            $options[$type->value] = $type->labelForCycle($cycle);
        }

        return $options;
    }

    /**
     * Coloanele de dată ale borderoului: zilele distincte din mulțimea FILTRATĂ, cronologic —
     * UNIUNEA notelor și absențelor (05.08.2026): o zi în care un elev doar a lipsit are coloană,
     * altfel scenariul „a lipsit la ambele ore" nu s-ar vedea deloc în borderou.
     * Antetul și celulele folosesc aceeași listă → totul cade fix sub ziua lui.
     *
     * @return list<array{iso: string, label: string, weekday: string}>
     */
    public function gradeColumns(): array
    {
        /** @var array<string, bool> $dates */
        $dates = [];

        foreach ($this->rows() as $row) {
            foreach (array_keys($row['gradesByDate']) as $iso) {
                $dates[(string) $iso] = true;
            }

            foreach (array_keys($row['absencesByDate']) as $iso) {
                $dates[(string) $iso] = true;
            }
        }

        // ZILELE DE LECȚIE ale perioadei, chiar dacă n-au încă nimic scris (05.08.2026): altfel
        // ziua de azi — care abia urmează să primească notele lecției — nu are coloană, deci nu
        // are nici celulă de deschis, iar panoul (singura cale de scriere) devine inaccesibil fix
        // când e nevoie de el. Catalogul de hârtie are coloană pentru fiecare lecție, goală până
        // se scrie în ea; aici la fel.
        foreach ($this->lessonDaysInRange() as $iso) {
            $dates[$iso] = true;
        }

        $isoList = array_keys($dates);
        sort($isoList);

        return array_map(fn (string $iso): array => [
            'iso' => $iso,
            'label' => Carbon::parse($iso)->format('d.m'),
            'weekday' => Carbon::parse($iso)->translatedFormat('D'),
        ], $isoList);
    }

    /**
     * Zilele din perioadă în care clasa ARE lecția disciplinei — din orar; fără orar (sau fără
     * lecțiile disciplinei în el), toate zilele lucrătoare, ca registrul să rămână utilizabil.
     *
     * Mărginite la ZIUA DE AZI: o coloană în viitor ar fi o fundătură (gărzile refuză scrierea
     * înainte), iar sărbătorile legale ies — școala e închisă, nu se consemnează nimic.
     *
     * @return list<string>
     */
    private function lessonDaysInRange(): array
    {
        $range = $this->timeRange();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($range === null || $class === null || $subject === null) {
            return [];
        }

        [$start, $end] = $range;

        if ($start === null || $end === null) {
            return [];
        }

        $today = SchoolCalendar::localNow()->startOfDay();
        $cursor = Carbon::parse($start->toDateString())->startOfDay();
        $last = Carbon::parse($end->toDateString())->startOfDay();

        if ($last->greaterThan($today)) {
            $last = $today->copy();
        }

        // Plasă de siguranță: o perioadă absurd de lungă (interval liber pe ani) n-are voie să
        // producă mii de coloane — ziua cu date rămâne oricum vizibilă din ramura de mai sus.
        if ($cursor->greaterThan($last) || $cursor->diffInDays($last) > 400) {
            return [];
        }

        // `day_of_week` e cast la enumul Weekday (Luni = 1, ca `isoWeekday()`).
        $lessonDays = Lesson::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->get(['day_of_week'])
            ->map(fn (Lesson $lesson): int => $lesson->day_of_week->value)
            ->unique()
            ->all();

        $days = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $isLessonDay = $lessonDays === []
                ? $cursor->isWeekday()
                : in_array($cursor->isoWeekday(), $lessonDays, true);

            if ($isLessonDay && ! Holidays::isHoliday($cursor)) {
                $days[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Se afișează pe COLOANE de dată? Doar când zilele distincte încap pe ecran; peste prag notele
     * cad înapoi în șir, cu invitația de a restrânge filtrul — nicio notă nu dispare, doar forma.
     */
    /**
     * O SINGURĂ formă, indiferent de volum (cerința beneficiarului, 04.08.2026): notele stau pe
     * coloane de dată ori de câte ori există măcar una — și pe „Toate".
     *
     * Pragul de aici (fostul MAX_GRADE_COLUMNS) făcea forma dependentă de DATE: pe aceeași
     * pastilă „Toate", clasa 1B cădea în șir (56 de zile), iar 7B stătea pe coloane (18) — două
     * tabele diferite pentru aceeași alegere, ba chiar aceeași clasă își schimba forma de la o
     * disciplină la alta. Multe zile nu mai schimbă forma, ci doar LĂȚIMEA: tabelul derulează
     * orizontal în containerul lui, cu numele elevului lipit la stânga — ca un catalog de hârtie
     * întins pe toată banca.
     */
    public function gradesAlignedByDate(): bool
    {
        return $this->gradeColumns() !== [];
    }

    /** Tipul revine la implicit; perioada are propriul buton „Toate" în bara temporală. */
    public function clearGradeFilters(): void
    {
        $this->gradeTypeFilter = self::DEFAULT_GRADE_TYPE;
    }

    // ── Acțiunile ZILEI (popover-ul celulei — cerința beneficiarului, 05.08.2026) ───────────

    /**
     * Pârghiile privitorului pe contextul (clasă, disciplină) activ — ACELEAȘI gărzi ca tabelul
     * Note și hărțile ({@see GradesTable}); calculate o dată, nu per pastilă.
     *
     * @return array{is_admin: bool, can_annul: bool, can_request: bool, can_status: bool}
     */
    public function dayRights(): array
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($user === null || $class === null || $subject === null) {
            return ['is_admin' => false, 'can_annul' => false, 'can_request' => false, 'can_status' => false];
        }

        $isAdmin = $user->canAdministerCatalog();
        $teaches = $user->teacher?->canGradeClassSubject((int) $class->getKey(), (int) $subject->getKey()) ?? false;
        $isHomeroom = in_array((int) $class->getKey(), $user->contextHomeroomClassIds(), true); // contextul pedagogic activ (F3)

        return [
            'is_admin' => $isAdmin,
            // Anularea = operație asupra notei: administrația sau titularul perechii (M-1/#07).
            'can_annul' => $isAdmin || $teaches,
            // Cererea de corecție (§3.1): titularul SAU dirigintele clasei; administrația editează direct.
            'can_request' => ! $isAdmin && ($teaches || $isHomeroom),
            // Statutul absenței: dirigintele clasei sau administrația — profesorul doar consemnează.
            'can_status' => $user->canMotivateAbsencesFor((int) $class->getKey()),
        ];
    }

    /**
     * PANOUL ZILEI — inima interacțiunii (cerința beneficiarului, 05.08.2026): click pe celula
     * (elev × zi) deschide un modal cu TOT ce s-a întâmplat în ziua aceea la disciplina
     * contextului — notele cu pârghiile lor, absențele pe ore cu statutul lor — plus consemnarea
     * unei absențe NOI pe ora aleasă. Modal, nu popover ancorat: celula stă într-un container cu
     * overflow ascuns (zona derulabilă), unde orice popover ar fi retezat la margine.
     */
    public function dayPanelAction(): Action
    {
        return Action::make('dayPanel')
            ->modalHeading(function (array $arguments): string {
                $student = Student::query()->find((int) ($arguments['student'] ?? 0));
                $iso = (string) ($arguments['iso'] ?? '');
                $name = $student instanceof Student ? $student->full_name : '';

                return trim($name.' · '.(preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) === 1
                    ? Carbon::parse($iso)->translatedFormat('j F Y')
                    : ''), ' ·');
            })
            ->modalContent(fn (array $arguments) => view('filament.catalog.partials.day-panel', [
                'panel' => $this->dayPanel((int) ($arguments['student'] ?? 0), (string) ($arguments['iso'] ?? '')),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('panel.class_register.day_panel.close'))
            ->modalWidth('lg');
    }

    /**
     * Datele panoului: notele și absențele elevului în ziua aleasă, STRICT în contextul activ,
     * cu pârghiile deja judecate pe server — blade-ul doar le arată, nu decide nimic.
     *
     * @return array{
     *     student: Student|null,
     *     iso: string,
     *     grades: list<array{id: int, value: string, type_label: string, weighted: bool, pending: bool, annulled: bool, edit_url: string|null, can_annul: bool, can_request: bool}>,
     *     absences: list<array{id: int, status: string, color: string, status_label: string, lesson: int|null}>,
     *     hours: array{taken: list<int>, timetable: list<int>},
     *     rights: array{is_admin: bool, can_annul: bool, can_request: bool, can_status: bool},
     *     can_absent: bool,
     *     can_grade: bool,
     *     numeric: bool,
     *     grade_types: array<string, string>,
     * }
     */
    public function dayPanel(int $studentId, string $iso): array
    {
        $empty = [
            'student' => null, 'iso' => $iso, 'grades' => [], 'absences' => [],
            'hours' => ['taken' => [], 'timetable' => []],
            'rights' => $this->dayRights(), 'can_absent' => false,
            'can_grade' => false, 'numeric' => true, 'grade_types' => [],
        ];

        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($class === null || $subject === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
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

        $rights = $this->dayRights();
        $cycle = SchoolCycle::fromGradeLevel((int) $class->grade_level);

        $grades = [];

        // TOATE notele zilei, inclusiv cele anulate (gri, cu semn): panoul e locul unde ziua se
        // citește întreagă — filtrul de TIP al borderoului nu se aplică aici, tot ce poartă ziua
        // se vede. Anulata nu mai are pârghii.
        $gradeRecords = Grade::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $student->getKey())
            ->whereDate('graded_on', $iso)
            ->orderBy('id')
            ->get();

        foreach ($gradeRecords as $grade) {
            $numeric = $this->gradingType() === GradingType::Numeric;
            $annulled = $grade->isAnnulled();
            $pending = $grade->hasPendingCorrection();

            $grades[] = [
                'id' => (int) $grade->getKey(),
                'value' => $numeric
                    ? ($grade->value !== null ? (string) (int) $grade->value : '—')
                    : (string) ($grade->calificativ ?? '—'),
                'type_label' => $grade->evaluation_type->labelForCycle($cycle),
                'weighted' => $grade->evaluation_type !== EvaluationType::Curenta,
                'pending' => $pending,
                'annulled' => $annulled,
                'edit_url' => $rights['is_admin'] && ! $annulled ? GradeResource::getUrl('edit', ['record' => $grade]) : null,
                'can_annul' => ! $annulled && ($rights['is_admin'] || GradesTable::teacherTeachesGrade($grade)),
                'can_request' => ! $annulled && ! $pending && ! $rights['is_admin'] && GradesTable::canRequestCorrectionFor($grade),
            ];
        }

        $absences = [];
        $taken = [];

        $absenceRecords = Absence::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $student->getKey())
            ->whereDate('occurred_on', $iso)
            ->orderByRaw('lesson_number IS NULL, lesson_number')
            ->get();

        foreach ($absenceRecords as $absence) {
            $status = $absence->status();

            $absences[] = [
                'id' => (int) $absence->getKey(),
                'status' => $status->value,
                'color' => $status->color(),
                'status_label' => (string) $status->getLabel(),
                'lesson' => $absence->lesson_number,
            ];

            if ($absence->lesson_number !== null) {
                $taken[] = (int) $absence->lesson_number;
            }
        }

        $notFuture = ! Carbon::parse($iso)->startOfDay()->isAfter(Carbon::today());

        return [
            'student' => $student,
            'iso' => $iso,
            'grades' => $grades,
            'absences' => $absences,
            'hours' => ['taken' => $taken, 'timetable' => $this->timetableHours($iso)],
            'rights' => $rights,
            'can_absent' => $this->canRecordAbsences() && $notFuture,
            // Adăugarea unei NOTE pe ziua panoului (cerința 05.08.2026) — aceeași poartă ca
            // introducerea rapidă; garda de scope face restul la salvare.
            'can_grade' => $this->canEnterGrades() && $notFuture,
            'numeric' => $this->gradingType() === GradingType::Numeric,
            'grade_types' => $this->gradeTypeOptions(),
        ];
    }

    /**
     * Adaugă o NOTĂ din panoul zilei — pe ziua celulei, nu pe data introducerii rapide. Trece
     * prin ACEEAȘI validare prietenoasă și ACEEAȘI gardă ca borderoul ({@see saveEntries},
     * {@see EnforcesGradeScope}): 1–10 întreg la numerice, calificativ scurt la celelalte;
     * semestrul derivat din zi, sumativa doar unde e desemnată, scope-ul titularului pe server.
     */
    public function addDayGrade(int $studentId, string $iso, string $value, string $type): void
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $value = trim($value);

        if ($user === null || $class === null || $subject === null
            || ! $this->canEnterGrades()
            || ! $this->studentInActiveClass($studentId)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1
            || $value === '') {
            $this->denyDayAction();

            return;
        }

        $numeric = $this->gradingType() === GradingType::Numeric;

        if ($numeric && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_value'))->send();

            return;
        }

        // Calificativul e un SIMBOL dintr-o scală închisă, nu text de lungime oarecare: regula
        // veche („cel mult 10 caractere") lăsa să intre orice. Aici profesorul TASTEAZĂ direct în
        // celulă, deci se acceptă și „fb"/„SP" — `normalize()` le duce la forma canonică.
        $calificativ = $numeric ? null : Calificativ::normalize($value);

        if (! $numeric && $calificativ === null) {
            Notification::make()->danger()->title(__('panel.class_register.invalid_calificativ'))->send();

            return;
        }

        try {
            $data = $this->enforceGradeScope([
                'student_id' => $studentId,
                'subject_id' => (int) $subject->getKey(),
                'school_class_id' => (int) $class->getKey(),
                'graded_on' => $iso,
                'evaluation_type' => $numeric && EvaluationType::tryFrom($type) !== null
                    ? $type
                    : EvaluationType::Curenta->value,
                'value' => $numeric ? (int) $value : null,
                'calificativ' => $calificativ?->value,
                'teacher_id' => $user->teacher?->getKey(),
            ]);

            Grade::query()->create($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('panel.class_register.day_panel.grade_added'))
            ->send();
    }

    /**
     * Orele din ORAR ale disciplinei contextului în ziua dată — sugestiile „reale" ale panoului
     * (două ore consecutive de Biologie → aici apar amândouă). Orarul poate lipsi sau fi
     * incomplet, deci sunt sugestii, nu gard: consemnarea acceptă orice oră 1–8.
     *
     * @return list<int>
     */
    public function timetableHours(string $iso): array
    {
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($class === null || $subject === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return [];
        }

        /** @var list<int> */
        return Lesson::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('day_of_week', Carbon::parse($iso)->isoWeekday())
            ->orderBy('lesson_number')
            ->pluck('lesson_number')
            ->map(fn ($n): int => (int) $n)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Consemnează o absență NOUĂ din panoul zilei — un click, o oră lipsită. ORA nu se mai alege
     * (decizia beneficiarului, 05.08.2026: disciplina e deja fixată de context, alegerea orei era
     * zgomot): se atribuie AUTOMAT — întâi orele disciplinei din orar (în ordine), apoi, fără
     * orar, ordinalul liber 1–8. Așa „a lipsit la ambele ore" = două apăsări, fiecare pe slotul
     * ei. Garda rămâne aceeași ({@see EnforcesAbsenceScope}): fără viitor, semestru derivat din
     * zi, anti-duplicat pe slot.
     */
    public function addDayAbsence(int $studentId, string $iso): void
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($user === null || $class === null || $subject === null
            || ! $this->canRecordAbsences()
            || ! $this->studentInActiveClass($studentId)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            $this->denyDayAction();

            return;
        }

        $lesson = $this->nextFreeLessonSlot($studentId, $iso);

        if ($lesson === null) {
            // Toate orele zilei (din orar, ori 1–8 fără orar) sunt deja consemnate: a N-a apăsare
            // nu mai are ce oră să umple — refuz prietenos, nu un rând imposibil.
            Notification::make()
                ->warning()
                ->title(__('panel.class_register.day_panel.all_hours_taken'))
                ->send();

            return;
        }

        try {
            $data = $this->enforceAbsenceScope([
                'student_id' => $studentId,
                'subject_id' => (int) $subject->getKey(),
                'school_class_id' => (int) $class->getKey(),
                'occurred_on' => $iso,
                'lesson_number' => $lesson,
                // Fără statut — profesorul consemnează, dirigintele decide (regula 04.08.2026).
                'is_motivated' => null,
                'teacher_id' => $user->teacher?->getKey(),
            ]);

            Absence::query()->create($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('panel.class_register.day_panel.absence_added'))
            ->send();
    }

    /**
     * Elevul aparține clasei ACTIVE? Batch-ul vechi filtra prin rândurile vizibile; panoul
     * primește id-ul din browser, deci înmatricularea se re-verifică explicit — altfel un id
     * străin ar trece prin ramura de administrație a gărzilor (care nu cere înmatriculare).
     */
    private function studentInActiveClass(int $studentId): bool
    {
        $class = $this->activeClass();

        return $class !== null && Enrollment::query()
            ->where('school_class_id', $class->getKey())
            ->where('student_id', $studentId)
            ->whereNull('left_on')
            ->exists();
    }

    /**
     * Următorul slot de oră LIBER pentru (elev, zi): întâi orele disciplinei din orar, în ordinea
     * lor — prima apăsare = prima oră, a doua = a doua (exact scenariul orelor consecutive); fără
     * orar, cel mai mic ordinal 1–8 neconsumat. Null = totul e consemnat deja.
     *
     * O absență istorică „fără oră" (null) nu blochează sloturile numerotate — ocupă doar slotul
     * propriu, ca până acum.
     */
    private function nextFreeLessonSlot(int $studentId, string $iso): ?int
    {
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($class === null || $subject === null) {
            return null;
        }

        $taken = Absence::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('student_id', $studentId)
            ->whereDate('occurred_on', $iso)
            ->whereNotNull('lesson_number')
            ->pluck('lesson_number')
            ->map(fn ($n): int => (int) $n)
            ->all();

        // Orarul e SUGESTIE, nu adevăr absolut: întâi orele lui (prima apăsare = prima oră reală
        // a zilei), apoi ordinalele rămase. Varianta strictă — doar orele din orar — bloca exact
        // scenariul cerut când orarul e incomplet: o oră dublă neînregistrată în el făcea a doua
        // absență imposibilă, deși profesorul știe că elevul a lipsit la ambele. Ceea ce s-a
        // întâmplat în clasă bate ce scrie în orar.
        foreach ([...$this->timetableHours($iso), ...range(1, 8)] as $hour) {
            if (! in_array($hour, $taken, true)) {
                return $hour;
            }
        }

        return null;
    }

    /**
     * Anulează o notă din popover-ul zilei — semantica și gărzile acțiunii din tabelul Note.
     * Nota rămâne în istoric, iese din medii (observerul recalculează singur).
     */
    public function annulGradeAction(): Action
    {
        return Action::make('annulGrade')
            ->label(__('panel.actions.annul.label'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('panel.actions.annul.heading'))
            ->modalDescription(__('panel.actions.annul.description'))
            ->schema([
                Textarea::make('annulment_reason')
                    ->label(__('panel.actions.annul.reason'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data): void {
                $grade = $this->dayActionGrade($arguments);

                if ($grade === null || $grade->isAnnulled()
                    || ! ($this->dayRights()['is_admin'] || GradesTable::teacherTeachesGrade($grade))) {
                    $this->denyDayAction();

                    return;
                }

                $grade->update([
                    'annulled_at' => now(),
                    'annulled_by_user_id' => auth()->id(),
                    'annulment_reason' => $data['annulment_reason'],
                ]);

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.annul.success'))
                    ->send();
            });
    }

    /** Solicită corecția unei note din popover — fluxul §3.1 (cerere → aprobarea administrației). */
    public function requestGradeCorrectionAction(): Action
    {
        return Action::make('requestGradeCorrection')
            ->label(__('panel.actions.request_correction.label'))
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->modalHeading(__('panel.actions.request_correction.heading'))
            ->modalDescription(__('panel.actions.request_correction.description'))
            ->modalSubmitActionLabel(__('panel.actions.request_correction.submit'))
            ->schema([
                TextInput::make('new_value')
                    ->label(__('panel.actions.request_correction.new_value'))
                    ->validationAttribute(__('panel.actions.request_correction.new_value'))
                    ->numeric()
                    // Aceeași scală ca nota: întreg 1–10 (vezi nota din ListGrades).
                    ->step(1)
                    ->rules(['integer'])
                    ->minValue(1)
                    ->maxValue(10)
                    ->visible(fn (Action $action): bool => $this->dayActionGrade($action->getArguments())?->subject?->grading_type === GradingType::Numeric)
                    ->requiredWithout('new_calificativ'),
                Select::make('new_calificativ')
                    ->label(__('panel.actions.request_correction.new_calificativ'))
                    ->validationAttribute(__('panel.actions.request_correction.new_calificativ'))
                    ->options(Calificativ::groupedOptions())
                    ->native(false)
                    ->visible(fn (Action $action): bool => $this->dayActionGrade($action->getArguments())?->subject?->grading_type !== GradingType::Numeric)
                    ->requiredWithout('new_value'),
                Textarea::make('reason')
                    ->label(__('panel.actions.request_correction.reason'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data): void {
                $grade = $this->dayActionGrade($arguments);

                if ($grade === null || $grade->isAnnulled() || $grade->hasPendingCorrection()
                    || ! GradesTable::canRequestCorrectionFor($grade)) {
                    $this->denyDayAction();

                    return;
                }

                GradeCorrection::create([
                    'grade_id' => $grade->id,
                    'requested_by_user_id' => auth()->id(),
                    'old_value' => $grade->value,
                    'new_value' => $data['new_value'] ?? null,
                    'old_calificativ' => $grade->calificativ,
                    'new_calificativ' => $data['new_calificativ'] ?? null,
                    'reason' => $data['reason'],
                ]);

                Notification::make()
                    ->success()
                    ->title(__('panel.actions.request_correction.success_title'))
                    ->body(__('panel.actions.request_correction.success_body'))
                    ->send();
            });
    }

    /**
     * Fixează statutul unei absențe din popover-ul zilei — aceeași semantică precum harta
     * absențelor: dirigintele clasei sau administrația; profesorul consemnează, nu decide.
     */
    public function setDayAbsenceStatus(int $absenceId, string $status): void
    {
        $target = AbsenceStatus::tryFrom($status);
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $user = $this->viewer();

        // Ținta se rezolvă STRICT în contextul activ — un id străin nu se găsește deloc.
        $absence = ($target === null || $class === null || $subject === null) ? null : Absence::query()
            ->whereKey($absenceId)
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->first();

        if ($absence === null || ! $user instanceof User || ! $user->canMotivateAbsencesFor((int) $absence->school_class_id)) {
            $this->denyDayAction();

            return;
        }

        $absence->update(['is_motivated' => $target->motivatedValue()]);

        Notification::make()
            ->success()
            ->title(__('absence_map.status_saved'))
            ->send();
    }

    /**
     * Nota țintei unei acțiuni de zi, STRICT în contextul activ (clasă + disciplină): argumentele
     * din browser sunt dorințe, nu adevăr.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function dayActionGrade(array $arguments): ?Grade
    {
        $id = $arguments['id'] ?? null;
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if (! is_numeric($id) || $class === null || $subject === null) {
            return null;
        }

        /** @var Grade|null */
        return Grade::query()
            ->whereKey((int) $id)
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->first();
    }

    private function denyDayAction(): void
    {
        Notification::make()
            ->danger()
            ->title(__('grade_map.action_denied'))
            ->send();
    }

    /** Grupa de engleză a alocării PROPRII pe (clasă, disciplină) — null fără alocare sau grupă. */
    private function ownAssignmentGroup(int $classId, int $subjectId): ?int
    {
        $teacher = $this->viewerTeacher();

        if ($teacher === null) {
            return null;
        }

        $group = DB::table('teaching_assignments')
            ->where('teacher_id', $teacher->getKey())
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereNull('deleted_at')
            ->value('english_group');

        return $group !== null ? (int) $group : null;
    }

    // ── Navigare ──────────────────────────────────────────────────────────────────────

    public function openClass(int $id): void
    {
        $this->classParam = (string) $id;
        $this->subjectParam = null;
        // Alt context → alte note: un filtru rămas din clasa precedentă ar arăta „gol" fără
        // nicio explicație (data de ieri nu există la clasa nouă).
        $this->clearGradeFilters();
    }

    public function openSubject(int $id): void
    {
        $this->subjectParam = (string) $id;
        $this->clearGradeFilters();
    }
}
