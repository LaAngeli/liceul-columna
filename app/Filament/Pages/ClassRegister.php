<?php

namespace App\Filament\Pages;

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Enums\UserRole;
use App\Filament\Concerns\EnforcesAbsenceScope;
use App\Filament\Concerns\EnforcesGradeScope;
use App\Filament\Concerns\HasTimeNavigator;
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
use App\Support\SchoolCalendar;
use BackedEnum;
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
 * CATALOGUL CLASEI (borderoul) — cerința beneficiarului (2026-07-30): profesorul nu avea imagine
 * de ansamblu, iar introducerea notelor/absențelor se făcea elev cu elev, prin formular — zeci de
 * minute pentru o clasă. Aici: TOATĂ clasa pe un ecran (alfabetic, note, medii, absențe) și o
 * coloană de INTRODUCERE RAPIDĂ — tastezi nota, Enter/Tab, următorul elev; bifezi absenții; un
 * singur buton salvează tot. Ținta: ~25 de elevi în 2-3 minute, în pauza dintre lecții.
 *
 * SECURITATEA NU E RE-INVENTATĂ: salvarea trece prin ACELEAȘI traituri ca formularele clasice
 * ({@see EnforcesGradeScope}, {@see EnforcesAbsenceScope}) — semestrul derivat din dată, fără
 * viitor, fără an închis, fără duplicate, scope-ul profesorului verificat pe server la FIECARE
 * rând. Vizibilitatea urmează regula resurselor: profesorul își vede (clasa, disciplina) lui,
 * dirigintele toată clasa lui, administrația tot; AT nu are acces la date academice.
 *
 * SALVAREA E ATOMICĂ: orice rând invalid anulează tot batch-ul, cu eroarea afișată PE RÂND —
 * previzibil („nimic nu s-a salvat, corectează și reia"), nu jumătăți de catalog. Scrierea trece
 * prin MODELE, deci observerii lucrează normal: mediile se recalculează, familia e notificată.
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
     * Introducerea rapidă, per elev: nota tastată + marcajul de absență.
     *
     * `absence` are DOUĂ valori: `null` (elev prezent) sau `absent` — un singur buton, fără
     * statut (cerința beneficiarului, 04.08.2026). Vechile butoane „Mot./Nem." îi cereau
     * profesorului o informație pe care de regulă n-o are: DE CE lipsește elevul află doar
     * dirigintele, pe parcursul zilei. Absența pleacă de aici FĂRĂ statut, iar dirigintele o
     * statutează din secțiunea Absențe (unde îl așteaptă coada „fără statut" cu badge în meniu).
     *
     * @var array<int|string, array{value?: string|null, absence?: string|null}>
     */
    public array $entries = [];

    /** Data pentru TOT batch-ul (implicit azi) — semestrul se derivă din ea, pe server. */
    public string $entryDate = '';

    public string $entryType = EvaluationType::Curenta->value;

    /** Marcajul de absență al unui rând (starea „prezent" = null). */
    public const ABSENCE_MARKED = 'absent';

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

    public function mount(): void
    {
        if ($this->entryDate === '') {
            $this->entryDate = $this->defaultEntryDate();
        }
    }

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
     * Semestrul afișat = semestrul DATEI alese. O singură sursă de adevăr (cerința beneficiarului,
     * 2026-07-30): selectorul separat de semestru era redundant lângă o dată care oricum decide
     * unde se salvează nota — și, mai rău, sugera că se poate salva în alt semestru decât cel al
     * datei. Acum vezi exact semestrul în care vei scrie; ca să vezi altul, schimbi data.
     *
     * Fallback: dacă data cade în afara oricărui interval (vacanță), rămâne semestrul curent al
     * clasei — borderoul arată ceva util, iar salvarea e oricum arbitrată pe server de trait.
     */
    public function activeTerm(): ?Term
    {
        $terms = $this->termOptions();

        if ($terms->isEmpty()) {
            return null;
        }

        if ($this->entryDate !== '') {
            $date = Carbon::parse($this->entryDate)->startOfDay();

            $matching = $terms->first(function (Term $term) use ($date): bool {
                return $term->starts_on !== null
                    && $term->ends_on !== null
                    && $date->betweenIncluded($term->starts_on->startOfDay(), $term->ends_on->endOfDay());
            });

            if ($matching instanceof Term) {
                return $matching;
            }
        }

        return $terms->firstWhere('is_current', true) ?? $terms->first();
    }

    /**
     * Data implicită: ÎNTOTDEAUNA ziua în care se deschide borderoul (cerința beneficiarului,
     * 04.08.2026).
     *
     * Varianta anterioară muta tăcut data pe ultima zi a semestrului curent când „azi" nu cădea în
     * niciun semestru (vacanță, an neînchis). Scopul era bun — salvarea trecea — dar mijlocul era
     * greșit: ecranul arăta o ALTĂ zi decât cea în care lucrezi, iar o notă pusă din reflex se
     * scria pe 30 iunie fără ca nimeni să o ceară. Substituția tăcută a fost înlocuită cu ADEVĂRUL
     * spus la vedere: data rămâne azi, iar dacă azi nu aparține niciunui semestru, ecranul o spune
     * și arată ce e de făcut ({@see entryDateState()}).
     */
    private function defaultEntryDate(): string
    {
        return Carbon::today()->toDateString();
    }

    /** Data aleasă cade într-un semestru — cazul normal, nimic de semnalat. */
    public const DATE_IN_TERM = 'in_term';

    /** Vacanță din INTERIORUL anului: salvarea trece, cu semestrul curent (fallback legitim). */
    public const DATE_VACATION = 'vacation';

    /** Dată de DUPĂ finalul anului: structura anului nou lipsește → salvarea e refuzată. */
    public const DATE_AFTER_YEAR = 'after_year';

    /**
     * Starea datei alese față de structura anului. Oglindește EXACT decizia de pe server
     * ({@see EnforcesGradeScope}/{@see EnforcesAbsenceScope}): ce anunță ecranul aici e ce se va
     * întâmpla la salvare — altfel semnalul ar fi doar decor.
     */
    public function entryDateState(): string
    {
        $date = $this->entryDate !== ''
            ? Carbon::parse($this->entryDate)->startOfDay()
            : Carbon::today();

        if (Term::forDate($date) instanceof Term) {
            return self::DATE_IN_TERM;
        }

        $yearEndsOn = SchoolCalendar::currentTerm()?->academicYear?->ends_on;

        return $yearEndsOn !== null && $date->isAfter($yearEndsOn->startOfDay())
            ? self::DATE_AFTER_YEAR
            : self::DATE_VACATION;
    }

    /** Anul care s-a încheiat (pentru mesajul de rollover) — null dacă nu există semestru curent. */
    public function currentYearLabel(): ?string
    {
        return SchoolCalendar::currentTerm()?->academicYear?->name;
    }

    public function currentYearEndsOn(): ?string
    {
        return SchoolCalendar::currentTerm()?->academicYear?->ends_on?->format('d.m.Y');
    }

    /**
     * Linkul spre ecranul de deschidere a anului nou — DOAR pentru cine îl poate folosi.
     * Un buton care duce la 403 ar muta problema, nu ar rezolva-o; profesorul primește doar
     * explicația (și pe ea o duce mai departe la administrație).
     */
    public function yearTransitionUrl(): ?string
    {
        return SchoolYearTransition::canAccess() ? SchoolYearTransition::getUrl() : null;
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
     * @return list<array{
     *     student: Student,
     *     grades: list<array{value: string, weighted: bool, tooltip: string, iso: string}>,
     *     gradesByDate: array<string, list<array{value: string, weighted: bool, tooltip: string, iso: string}>>,
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

        $absences = Absence::query()
            ->where('school_class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('term_id', $term->getKey())
            ->whereIn('student_id', $studentIds)
            ->orderBy('occurred_on')
            ->get()
            ->groupBy('student_id');

        // Eticheta sumativei diferă pe ciclu (ESS la gimnaziu, teză la liceu) — un singur cod,
        // două nume; borderoul trebuie să-l spună pe cel din clasa deschisă.
        $cycle = SchoolCycle::fromGradeLevel((int) $class->grade_level);

        $rows = [];

        foreach ($students as $student) {
            $studentGrades = $grades->get($student->getKey(), new Collection);
            $studentAbsences = $absences->get($student->getKey(), new Collection);
            $average = $averages->get($student->getKey());

            /** @var list<array{value: string, weighted: bool, tooltip: string, iso: string}> $gradeChips */
            $gradeChips = [];
            /** @var array<string, list<array{value: string, weighted: bool, tooltip: string, iso: string}>> $byDate */
            $byDate = [];

            foreach ($studentGrades as $grade) {
                $displayValue = $grade->value !== null
                    ? (string) (int) (float) $grade->value
                    : (string) ($grade->calificativ ?? '—');

                $chip = [
                    'value' => $displayValue,
                    // Teza/ESI se disting prin culoare; tipul și DATA notei se citesc la survol.
                    // Formulare explicită („Nota 8 · Curentă · 20.07.2026”): prima variantă arăta
                    // doar „Curentă 20.07" și părea o a doua valoare, nu o descriere a notei.
                    'weighted' => $grade->evaluation_type !== EvaluationType::Curenta,
                    'tooltip' => trans('panel.class_register.grade_tooltip', [
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

            $rows[] = [
                'student' => $student,
                'grades' => $gradeChips,
                'gradesByDate' => $byDate,
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
     * Coloanele de dată ale borderoului: zilele distincte din mulțimea FILTRATĂ, cronologic.
     * Antetul și celulele folosesc aceeași listă → nota fiecărui elev cade fix sub ziua ei.
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

    // ── Navigare (resetează introducerea începută — alt context, alt batch) ────────────────

    public function openClass(int $id): void
    {
        $this->classParam = (string) $id;
        $this->subjectParam = null;
        $this->resetEntries();
        // Alt context → alte note: un filtru rămas din clasa precedentă ar arăta „gol" fără
        // nicio explicație (data de ieri nu există la clasa nouă).
        $this->clearGradeFilters();
    }

    public function openSubject(int $id): void
    {
        $this->subjectParam = (string) $id;
        $this->resetEntries();
        $this->clearGradeFilters();
    }

    /**
     * Schimbarea datei mută borderoul în semestrul acelei date — deci rândurile afișate nu mai
     * corespund intrărilor începute. Le golim, ca să nu se salveze note pe alt semestru decât cel
     * pe care profesorul îl are în față.
     */
    public function updatedEntryDate(): void
    {
        $this->resetEntries();
    }

    private function resetEntries(): void
    {
        $this->entries = [];
        $this->resetErrorBag();
    }

    /**
     * Comută marcajul de absență pe un rând: un click marchează elevul absent, încă unul îl
     * readuce prezent. Fără statut aici — profesorul consemnează, dirigintele decide.
     */
    public function toggleAbsence(int $studentId): void
    {
        $current = $this->entries[$studentId]['absence'] ?? null;

        $this->entries[$studentId]['absence'] = $current === self::ABSENCE_MARKED ? null : self::ABSENCE_MARKED;
    }

    // ── Salvarea în masă ────────────────────────────────────────────────────────────────────

    /**
     * TOT batch-ul într-o singură acțiune, ATOMIC: se validează fiecare rând (aceleași gărzi ca
     * formularul clasic — traiturile), iar ORICE eroare anulează tot și se afișează PE RÂND.
     * Nimic parțial: profesorul corectează și reia, fără să ghicească ce a intrat și ce nu.
     */
    public function saveEntries(): void
    {
        $this->resetErrorBag();

        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        abort_unless($user !== null && $class !== null && $subject !== null, 404);

        $canGrade = $this->canEnterGrades();
        $canAbsent = $this->canRecordAbsences();

        abort_unless($canGrade || $canAbsent, 403);

        // Data de după finalul anului: gărzile de pe server ar refuza oricum fiecare rând, dar
        // mesajul ar veni de 25 de ori, pe rânduri. Îl spunem o dată, înainte să scriem ceva.
        if ($this->entryDateState() === self::DATE_AFTER_YEAR) {
            Notification::make()
                ->title(__('panel.class_register.after_year_blocked', [
                    'date' => Carbon::parse($this->entryDate)->format('d.m.Y'),
                ]))
                ->body(__('panel.class_register.after_year_body', [
                    'year' => $this->currentYearLabel() ?? '—',
                    'date' => $this->currentYearEndsOn() ?? '—',
                ]))
                ->danger()
                ->send();

            return;
        }

        $date = $this->entryDate !== '' ? $this->entryDate : Carbon::today()->toDateString();
        $numeric = $this->gradingType() === GradingType::Numeric;

        // Doar elevii AFIȘAȚI pot primi intrări — cheile străine din payload se ignoră.
        $visibleIds = array_map(
            fn (array $row): int => (int) $row['student']->getKey(),
            $this->rows(),
        );

        $batch = [];

        foreach ($this->entries as $studentId => $entry) {
            $studentId = (int) $studentId;

            if (! in_array($studentId, $visibleIds, true)) {
                continue;
            }

            $value = isset($entry['value']) ? trim((string) $entry['value']) : '';
            $absence = $entry['absence'] ?? null;

            // Doar marcajul cunoscut trece; orice altceva din payload = elev prezent.
            if ($absence !== self::ABSENCE_MARKED) {
                $absence = null;
            }

            if ($value === '' && $absence === null) {
                continue;
            }

            $batch[$studentId] = ['value' => $value, 'absence' => $absence];
        }

        if ($batch === []) {
            Notification::make()
                ->title(__('panel.class_register.nothing_to_save'))
                ->warning()
                ->send();

            return;
        }

        $rowErrors = [];
        $createdGrades = 0;
        $createdAbsences = 0;

        DB::beginTransaction();

        try {
            foreach ($batch as $studentId => $entry) {
                if ($entry['value'] !== '' && $canGrade) {
                    // Validare prietenoasă ÎNAINTE de model: 1–10 întreg pentru discipline
                    // numerice; calificativ scurt pentru celelalte. Garda de model rămâne în spate.
                    if ($numeric && (! ctype_digit($entry['value']) || (int) $entry['value'] < 1 || (int) $entry['value'] > 10)) {
                        $rowErrors[$studentId] = __('panel.class_register.invalid_value');
                    } elseif (! $numeric && mb_strlen($entry['value']) > 10) {
                        $rowErrors[$studentId] = __('panel.class_register.invalid_calificativ');
                    } else {
                        try {
                            $data = $this->enforceGradeScope([
                                'student_id' => $studentId,
                                'subject_id' => (int) $subject->getKey(),
                                'school_class_id' => (int) $class->getKey(),
                                'graded_on' => $date,
                                'evaluation_type' => $numeric ? $this->entryType : EvaluationType::Curenta->value,
                                'value' => $numeric ? (int) $entry['value'] : null,
                                'calificativ' => $numeric ? null : $entry['value'],
                                'teacher_id' => $user->teacher?->getKey(),
                            ]);

                            Grade::query()->create($data);
                            $createdGrades++;
                        } catch (ValidationException $exception) {
                            $rowErrors[$studentId] = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                        }
                    }
                }

                if ($entry['absence'] !== null && $canAbsent) {
                    try {
                        $data = $this->enforceAbsenceScope([
                            'student_id' => $studentId,
                            'subject_id' => (int) $subject->getKey(),
                            'school_class_id' => (int) $class->getKey(),
                            'occurred_on' => $date,
                            // FĂRĂ statut: profesorul consemnează, dirigintele decide (motivată/
                            // nemotivată) din secțiunea Absențe. Observerul motivează automat doar
                            // dacă ziua e deja acoperită de o motivare aprobată.
                            'is_motivated' => null,
                            'teacher_id' => $user->teacher?->getKey(),
                        ]);

                        Absence::query()->create($data);
                        $createdAbsences++;
                    } catch (ValidationException $exception) {
                        $rowErrors[$studentId] = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                    }
                }
            }

            if ($rowErrors !== []) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($rowErrors !== []) {
            foreach ($rowErrors as $studentId => $message) {
                $this->addError('entries.'.$studentId, $message);
            }

            Notification::make()
                ->title(__('panel.class_register.batch_failed'))
                ->body(__('panel.class_register.batch_failed_hint', ['count' => count($rowErrors)]))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('panel.class_register.batch_saved'))
            ->body(trans_choice('panel.class_register.saved_grades', $createdGrades, ['count' => $createdGrades])
                .' · '
                .trans_choice('panel.class_register.saved_absences', $createdAbsences, ['count' => $createdAbsences]))
            ->success()
            ->send();

        $this->resetEntries();
    }
}
