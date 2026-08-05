<?php

namespace App\Filament\Pages;

use App\Enums\AbsenceStatus;
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
use App\Support\SchoolCalendar;
use BackedEnum;
use Filament\Actions\Action;
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
     * VACANȚA DINTRE ANI: anul vechi s-a încheiat, anul nou E DESCHIS dar nu a început încă.
     * Salvarea pe această dată rămâne refuzată (ziua nu aparține niciunui semestru), dar nu mai
     * e nimic de reparat — se așteaptă 1 septembrie, iar semestrul curent comută singur
     * (`app:sync-current-term`). Fără starea asta, banda de rollover ar fi mințit („anul nou nu
     * are semestre definite") imediat DUPĂ deschiderea anului — prins pe 04.08.2026, la prima
     * deschidere reală prin „Trecerea în anul nou".
     */
    public const DATE_BETWEEN_YEARS = 'between_years';

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

        if ($yearEndsOn === null || ! $date->isAfter($yearEndsOn->startOfDay())) {
            return self::DATE_VACATION;
        }

        // După finalul anului curent: dacă există deja un semestru care ÎNCEPE după data aleasă,
        // anul următor e deschis — suntem doar în golul dintre ani, nu într-un rollover lipsă.
        return $this->nextTermAfter($date) !== null
            ? self::DATE_BETWEEN_YEARS
            : self::DATE_AFTER_YEAR;
    }

    /** Primul semestru care începe DUPĂ data dată — proba că anul următor există. */
    public function nextTermAfter(Carbon $date): ?Term
    {
        return Term::query()
            ->whereDate('starts_on', '>', $date->toDateString())
            ->orderBy('starts_on')
            ->first();
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

        if (! $numeric && mb_strlen($value) > 10) {
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
                'calificativ' => $numeric ? null : $value,
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
     * Consemnează o absență NOUĂ din panoul zilei, pe ORA aleasă — calea prin care ziua primește
     * a doua absență la aceeași disciplină (ore consecutive). Trece prin ACEEAȘI gardă ca
     * borderoul și formularul clasic ({@see EnforcesAbsenceScope}): fără viitor, semestru derivat
     * din dată, anti-duplicat pe slot (elev + zi + disciplină + oră).
     */
    public function addDayAbsence(int $studentId, string $iso, ?int $lesson = null): void
    {
        $user = $this->viewer();
        $class = $this->activeClass();
        $subject = $this->activeSubject();

        if ($user === null || $class === null || $subject === null
            || ! $this->canRecordAbsences()
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1
            || ($lesson !== null && ($lesson < 1 || $lesson > 8))) {
            $this->denyDayAction();

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
                    ->minValue(1)
                    ->maxValue(10)
                    ->visible(fn (Action $action): bool => $this->dayActionGrade($action->getArguments())?->subject?->grading_type === GradingType::Numeric)
                    ->requiredWithout('new_calificativ'),
                TextInput::make('new_calificativ')
                    ->label(__('panel.actions.request_correction.new_calificativ'))
                    ->validationAttribute(__('panel.actions.request_correction.new_calificativ'))
                    ->maxLength(10)
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
     * Antetul unei zile mută DATA de introducere pe ziua aceea („selectarea directă a zilei",
     * cerința 05.08.2026): coloana de introducere rapidă scrie de-acum acolo. Intrările începute
     * se golesc — alt semestru posibil, alt batch.
     */
    public function setEntryDay(string $iso): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return;
        }

        $this->entryDate = $iso;
        $this->resetEntries();
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

        // Dată din afara oricărui semestru al vreunui an (rollover lipsă SAU golul dintre ani):
        // gărzile de pe server ar refuza oricum fiecare rând, dar mesajul ar veni de 25 de ori,
        // pe rânduri. Îl spunem o dată, înainte să scriem ceva — pe limba stării reale.
        $dateState = $this->entryDateState();

        if (in_array($dateState, [self::DATE_AFTER_YEAR, self::DATE_BETWEEN_YEARS], true)) {
            $blockedDate = Carbon::parse($this->entryDate);
            $next = $this->nextTermAfter($blockedDate);

            Notification::make()
                ->title(__('panel.class_register.after_year_blocked', [
                    'date' => $blockedDate->format('d.m.Y'),
                ]))
                ->body($dateState === self::DATE_BETWEEN_YEARS
                    ? __('panel.class_register.between_years_body', [
                        'year' => $this->currentYearLabel() ?? '—',
                        // În starea „între ani", $next există prin definiție (ea A DECIS starea);
                        // anul lui poate lipsi doar teoretic (withTrashed pe relații).
                        'next' => $next?->academicYear->name ?? '—',
                        'start' => $next?->starts_on?->format('d.m.Y') ?? '—',
                    ])
                    : __('panel.class_register.after_year_body', [
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
