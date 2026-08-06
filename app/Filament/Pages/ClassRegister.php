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
use App\Filament\Concerns\WritesGradesFromDay;
use App\Filament\Resources\Grades\GradeResource;
use App\Filament\Resources\Grades\Tables\GradesTable;
use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TermAverage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
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
 * EXCEPȚIA (cerința din 06.08.2026): pe filtrarea „Zi" — și NUMAI acolo — reapare introducerea în
 * masă ({@see saveDayBatch}), fiindcă ziua-țintă e neambiguă: e chiar ziua filtrată. Restul
 * modurilor rămân neatinse.
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
    use HasTimeNavigator;
    use WritesGradesFromDay;

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

    /** Contextul de scriere al panoului zilei ({@see WritesGradesFromDay}). */
    protected function dayGradeClass(): ?SchoolClass
    {
        return $this->activeClass();
    }

    protected function dayGradeSubject(): ?Subject
    {
        return $this->activeSubject();
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
     *     absencesByDate: array<string, list<array{id: int, status: string, color: string, status_label: string, lesson: int|null, solo: bool, label: string}>>,
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
                ->active()
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

            /** @var array<string, list<Absence>> $absencesPerDay */
            $absencesPerDay = [];

            foreach ($studentAbsences as $absence) {
                $absencesPerDay[$absence->occurred_on->toDateString()][] = $absence;
            }

            /** @var array<string, list<array{id: int, status: string, color: string, status_label: string, lesson: int|null, solo: bool, label: string}>> $absByDate */
            $absByDate = [];

            foreach ($absencesPerDay as $iso => $dayAbsences) {
                // CUM SE CITEȘTE absența în celulă (cerința beneficiarului, 07.08.2026): ziua cu o
                // SINGURĂ consemnare — o absență și nicio notă — poartă doar „A", la dimensiunea
                // unei note. Așa arată ~90% din zile, iar acolo ordinalul n-are ce dezambiguiza:
                // „A1" citit zilnic sugera fals că ar mai fi existat o oră. Ordinalul revine exact
                // unde chiar spune ceva: două absențe, ori notă și absență în aceeași zi.
                $solo = count($dayAbsences) === 1 && ($byDate[$iso] ?? []) === [];

                foreach ($dayAbsences as $absence) {
                    $status = $absence->status();
                    // ORA lecției — identitatea care desparte două absențe ale aceleiași zile
                    // (ore consecutive); null = „ziua, fără oră precizată" (rânduri istorice).
                    $lesson = $absence->lesson_number !== null ? (int) $absence->lesson_number : null;

                    $absByDate[$iso][] = [
                        'id' => (int) $absence->getKey(),
                        'status' => $status->value,
                        'color' => $status->color(),
                        'status_label' => $status->label(),
                        'lesson' => $lesson,
                        'solo' => $solo,
                        'label' => ($solo || $lesson === null) ? 'A' : 'A'.$lesson,
                    ];
                }
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
        foreach ($this->lessonDayIsosInRange() as $iso) {
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
     *     grades: list<array{id: int, value: string, type_label: string, lesson: int|null, can_move_hour: bool, weighted: bool, pending: bool, annulled: bool, edit_url: string|null, can_annul: bool, can_request: bool}>,
     *     absences: list<array{id: int, status: string, color: string, status_label: string, lesson: int|null, can_move_hour: bool}>,
     *     default_hour: int|null,
     *     busy_count: int,
     *     hour_menu: list<array{hour: int, busy: string|null}>,
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
            'default_hour' => null, 'busy_count' => 0, 'hour_menu' => [],
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

        // Intrările de notă vin din concernul partajat cu harta Note — aceleași chei, aceleași
        // pârghii, pe ambele ecrane.
        $grades = $this->dayGradeEntriesFor((int) $student->getKey(), $iso, $cycle);

        $absences = [];

        $absenceRecords = Absence::query()
            ->active()
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
                'can_move_hour' => $this->canRecordAbsences(),
                'can_annul' => $this->canAnnulAbsence($absence),
            ];
        }

        $notFuture = ! Carbon::parse($iso)->startOfDay()->isAfter(Carbon::today());

        // Ocuparea ordinalelor zilei: ce oră urmează (Ora 1 la zi „curată") și câte consemnări
        // există deja — aceeași sursă ca scrierea (concernul partajat).
        $usage = $this->dayHourUsage((int) $student->getKey(), $iso);

        return [
            'student' => $student,
            'iso' => $iso,
            'grades' => $grades,
            'absences' => $absences,
            'default_hour' => $usage['default'],
            'busy_count' => $usage['busy_count'],
            'hour_menu' => $this->dayHourMenu((int) $student->getKey(), $iso),
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
     * Consemnează o absență NOUĂ din panoul zilei — pe primul ORDINAL liber al zilei (Ora 1 =
     * prima consemnare a disciplinei, Ora 2 = a doua; 06.08.2026 v2 — ora nu vine din orar).
     * Ordinalele sunt împărțite cu notele, ca aceeași oră să nu poată purta amândouă. Garda
     * rămâne aceeași ({@see EnforcesAbsenceScope}): fără viitor, semestru derivat din zi,
     * anti-duplicat pe slot, ora cu notă refuzată. `$lesson` explicit rămâne pentru formulare/API.
     */
    public function addDayAbsence(int $studentId, string $iso, ?int $lesson = null): void
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

        $lesson ??= $this->firstFreeDayHour($studentId, $iso);

        if ($lesson === null) {
            // Toate cele 8 sloturi ale zilei (note + absențe) sunt deja consemnate: a N-a apăsare
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
     * ANULAREA unei absențe direct din panoul zilei (cerința beneficiarului, 07.08.2026) — aici
     * se consemnează, deci aici se și desface greșeala, fără drum până în secțiunea Absențe.
     *
     * Motivul e OBLIGATORIU, ca la note: o absență care dispare din totaluri fără explicație e
     * exact incertitudinea pe care mecanismul o repară. Rândul rămâne în istoric.
     */
    public function annulDayAbsence(int $absenceId, string $reason): void
    {
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $user = $this->viewer();
        $reason = trim($reason);

        // Ținta se rezolvă STRICT în contextul activ — un id străin nu se găsește deloc.
        $absence = ($class === null || $subject === null) ? null : Absence::query()
            ->active()
            ->whereKey($absenceId)
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->first();

        if ($absence === null || ! $user instanceof User || ! $this->canAnnulAbsence($absence)) {
            $this->denyDayAction();

            return;
        }

        if ($reason === '') {
            Notification::make()->danger()->title(__('panel.actions.annul.reason_required'))->send();

            return;
        }

        $absence->update([
            'annulled_at' => now(),
            'annulled_by_user_id' => $user->getKey(),
            'annulment_reason' => mb_substr($reason, 0, 255),
        ]);

        Notification::make()
            ->success()
            ->title(__('panel.actions.annul.absence_success'))
            ->send();
    }

    /**
     * Poți desface ce puteai face: profesorul pe (clasa, disciplina) lui, dirigintele pe clasa
     * lui, administrația oriunde — aceeași regulă ca în secțiunea Absențe.
     */
    private function canAnnulAbsence(Absence $absence): bool
    {
        $user = $this->viewer();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->canMotivateAbsencesFor((int) $absence->school_class_id)) {
            return true;
        }

        return $user->teacher?->canRecordAbsence(
            (int) $absence->school_class_id,
            $absence->subject_id !== null ? (int) $absence->subject_id : null,
        ) ?? false;
    }

    /**
     * În borderou trăiesc AMBELE specii, deci schimbul de locuri poate mișca și o absență —
     * dacă privitorul are dreptul să consemneze absențe. (Harta Note lasă implicitul `false`.)
     */
    protected function canMoveAbsences(): bool
    {
        return $this->canRecordAbsences();
    }

    /**
     * CORECTAREA orei unei ABSENȚE — perechea lui {@see WritesGradesFromDay::moveDayGradeHour}.
     * Cerința (07.08.2026) vine din introducerea în masă: acolo nota ia Ora 1 și absența Ora 2
     * prin convenția ordinii de procesare, dar în clasă putea fi invers (a lipsit la prima oră,
     * a răspuns la a doua). Ora ocupată = SCHIMB de locuri; o absență istorică fără oră se poate
     * doar așeza pe un ordinal liber.
     */
    public function moveDayAbsenceHour(int $absenceId, mixed $hour): void
    {
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $target = is_numeric($hour) ? (int) $hour : 0;

        $absence = ($class === null || $subject === null) ? null : Absence::query()
            ->whereKey($absenceId)
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->first();

        if ($absence === null || $target < 1 || $target > 8 || ! $this->canRecordAbsences()) {
            $this->denyDayAction();

            return;
        }

        $current = $absence->lesson_number !== null ? (int) $absence->lesson_number : null;

        if ($current === $target) {
            return;
        }

        $iso = $absence->occurred_on->toDateString();
        $occupant = $this->dayHourUsage((int) $absence->student_id, $iso)['busy'][$target] ?? null;

        // Nota altcuiva nu se mișcă de mâna cuiva care nu poate nota perechea; iar o absență
        // fără oră nu are ce ceda la schimb.
        if ($occupant !== null && ($current === null
            || ($occupant['kind'] === 'grade' && ! $this->canEnterGrades()))) {
            Notification::make()
                ->warning()
                ->title(__('panel.class_register.day_panel.hour_taken', ['number' => $target]))
                ->send();

            return;
        }

        DB::transaction(function () use ($absence, $occupant, $current, $target): void {
            if ($occupant !== null) {
                $this->parkDayOccupant($occupant, $current);
            }

            $absence->update(['lesson_number' => $target]);
        });

        Notification::make()
            ->success()
            ->title(__('panel.class_register.day_panel.hour_moved', ['number' => $target]))
            ->send();
    }

    // ── Introducerea în MASĂ, doar pe filtrarea „Zi" (cerința beneficiarului, 06.08.2026) ──
    //
    // Reintrodusă DUPĂ eliminarea din 05.08 fiindcă acum are ancora care lipsea atunci: în modul
    // „Zi", ziua-țintă e chiar ziua filtrată — nu mai există câmpul global de dată care despărțea
    // vizual scrierea de ziua ei. În orice alt mod, coloanele nu se randează și salvarea refuză.

    /**
     * Intrările introducerii rapide: `entries.{studentId}.value` (nota) și `.absent` (bifa).
     * Doar rândurile VIZIBILE se procesează — id-urile străine strecurate în payload se ignoră.
     *
     * @var array<int, array{value?: string, absent?: bool}>
     */
    public array $entries = [];

    /** Tipul de evaluare al notelor din batch (doar discipline numerice; altfel forțat „curentă"). */
    public string $batchType = EvaluationType::Curenta->value;

    /** Ziua-țintă a introducerii în masă — există DOAR pe filtrarea „Zi". */
    public function batchDayIso(): ?string
    {
        if ($this->timeMode() !== 'zi') {
            return null;
        }

        $range = $this->timeRange();

        return $range !== null && $range[0] !== null ? $range[0]->toDateString() : null;
    }

    /**
     * Coloanele de introducere există doar pe „Zi", pe o zi care nu e în viitor, pentru cine
     * poate scrie măcar una din specii. Gărzile reale rămân în {@see saveDayBatch} — asta e
     * doar vizibilitatea.
     */
    public function canBatchWrite(): bool
    {
        $iso = $this->batchDayIso();

        return $iso !== null
            && ! Carbon::parse($iso)->startOfDay()->isAfter(Carbon::today())
            && ($this->canEnterGrades() || $this->canRecordAbsences());
    }

    /**
     * Salvează TOT ce s-a completat în sesiunea curentă — un singur buton, o singură tranzacție.
     *
     * ATOMIC, ca vechiul batch (regula validată în iulie): ORICE rând invalid → rollback total +
     * eroarea pe rândul lui — nu rămân jumătăți de clasă salvate. Fiecare rând trece prin
     * ACELEAȘI gărzi ca panoul zilei ({@see EnforcesGradeScope}/{@see EnforcesAbsenceScope}:
     * scope pe server, semestru derivat din zi, fără viitor, an închis refuzat) plus ordinalul
     * de oră ({@see firstFreeDayHour}) — nota și absența aceluiași elev cad NATURAL pe ore
     * diferite (Ora 1, Ora 2), iar excluderea notă↔absență pe aceeași oră rămâne garantată.
     * Scrierea prin MODELE: observerii recalculează mediile și notifică familia (pe coadă,
     * rulată înapoi la eșec — coada e pe database).
     */
    public function saveDayBatch(): void
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();
        $iso = $this->batchDayIso();

        if ($user === null || $class === null || $subject === null || $iso === null || ! $this->canBatchWrite()) {
            $this->denyDayAction();

            return;
        }

        $numeric = $this->gradingType() === GradingType::Numeric;
        $canGrade = $this->canEnterGrades();
        $canAbsent = $this->canRecordAbsences();

        $this->resetErrorBag();

        $savedGrades = 0;
        $savedAbsences = 0;

        DB::beginTransaction();

        try {
            foreach ($this->rows() as $row) {
                $studentId = (int) $row['student']->getKey();
                $entry = $this->entries[$studentId] ?? null;

                if (! is_array($entry)) {
                    continue;
                }

                $value = trim((string) ($entry['value'] ?? ''));
                $absent = filter_var($entry['absent'] ?? false, FILTER_VALIDATE_BOOL);

                if ($value !== '') {
                    // Coloana notei nu se randează fără drept — o valoare venită totuși (payload
                    // manipulat) se refuză explicit, nu se ignoră tăcut.
                    if (! $canGrade) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.value" => __('grade_map.action_denied'),
                        ]);
                    }

                    // Aceeași validare prietenoasă ca panoul zilei: întreg 1–10 la numerice,
                    // simbol din scala închisă la calificative.
                    $calificativ = $numeric ? null : Calificativ::normalize($value);

                    if ($numeric && (! ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.value" => __('panel.class_register.invalid_value'),
                        ]);
                    }

                    if (! $numeric && $calificativ === null) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.value" => __('panel.class_register.invalid_calificativ'),
                        ]);
                    }

                    $lesson = $this->firstFreeDayHour($studentId, $iso);

                    if ($lesson === null) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.value" => __('panel.class_register.day_panel.all_hours_taken'),
                        ]);
                    }

                    // Gărzile aruncă pe chei generice (`data.*`) — re-aruncăm pe cheia RÂNDULUI,
                    // ca eroarea să se aprindă exact pe celula vinovată.
                    try {
                        $data = $this->enforceGradeScope([
                            'student_id' => $studentId,
                            'subject_id' => (int) $subject->getKey(),
                            'school_class_id' => (int) $class->getKey(),
                            'graded_on' => $iso,
                            'lesson_number' => $lesson,
                            'evaluation_type' => $numeric && EvaluationType::tryFrom($this->batchType) !== null
                                ? $this->batchType
                                : EvaluationType::Curenta->value,
                            'value' => $numeric ? (int) $value : null,
                            'calificativ' => $calificativ?->value,
                            'teacher_id' => $user->teacher?->getKey(),
                        ]);

                        Grade::query()->create($data);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.value" => (string) collect($exception->errors())->flatten()->first(),
                        ]);
                    }

                    $savedGrades++;
                }

                if ($absent && $canAbsent) {
                    // DUPĂ nota aceluiași rând (aceeași tranzacție): ordinalul următor e liber —
                    // notă la Ora 1, absență la Ora 2, exact semantica ordinală a zilei.
                    $lesson = $this->firstFreeDayHour($studentId, $iso);

                    if ($lesson === null) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.absent" => __('panel.class_register.day_panel.all_hours_taken'),
                        ]);
                    }

                    try {
                        $data = $this->enforceAbsenceScope([
                            'student_id' => $studentId,
                            'subject_id' => (int) $subject->getKey(),
                            'school_class_id' => (int) $class->getKey(),
                            'occurred_on' => $iso,
                            'lesson_number' => $lesson,
                            // Fără statut — profesorul consemnează, dirigintele decide (04.08.2026).
                            'is_motivated' => null,
                            'teacher_id' => $user->teacher?->getKey(),
                        ]);

                        Absence::query()->create($data);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            "entries.{$studentId}.absent" => (string) collect($exception->errors())->flatten()->first(),
                        ]);
                    }

                    $savedAbsences++;
                }
            }
        } catch (ValidationException $exception) {
            DB::rollBack();

            $firstMessage = '';

            foreach ($exception->errors() as $key => $messages) {
                $rowMessage = (string) ($messages[0] ?? '');
                $firstMessage = $firstMessage === '' ? $rowMessage : $firstMessage;
                $this->addError($key, $rowMessage);
            }

            Notification::make()
                ->danger()
                ->title(__('panel.class_register.batch_failed'))
                ->body($firstMessage)
                ->send();

            return;
        }

        if ($savedGrades === 0 && $savedAbsences === 0) {
            DB::rollBack();

            Notification::make()
                ->info()
                ->title(__('panel.class_register.nothing_to_save'))
                ->send();

            return;
        }

        DB::commit();

        $this->entries = [];

        Notification::make()
            ->success()
            ->title(__('panel.class_register.batch_saved'))
            ->body(trans_choice('panel.class_register.saved_grades', $savedGrades, ['count' => $savedGrades])
                .' · '.trans_choice('panel.class_register.saved_absences', $savedAbsences, ['count' => $savedAbsences]))
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
