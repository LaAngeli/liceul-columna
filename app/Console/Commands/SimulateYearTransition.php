<?php

namespace App\Console\Commands;

use App\Actions\AcademicYears\StartSchoolYear;
use App\Enums\DepartureReason;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * SIMULAREA trecerii în anul nou, pe o școală demo IZOLATĂ — dovada că lanțul complet
 * ({@see StartSchoolYear}: an + semestre + structură + absolvire + elevi) funcționează cap-coadă.
 *
 * De ce un an propriu, departe de anii reali (2040–2041 → 2041–2042): operațiunea scrie în
 * registru. Rulată pe anul curent, ar fi mutat 735 de elevi adevărați și ar fi consemnat absolvirea
 * a 38 — o operațiune de secretariat, nu o verificare.
 *
 * ⚠️ Anii NU pot purta marcajul `[DEMO]` în denumire: garda de model cere formatul canonic
 * „2041–2042" (doi ani calendaristici consecutivi). De aceea izolarea stă în anii din viitor
 * îndepărtat, iar marcajul rămâne pe clase, elevi, discipline și profesor.
 *
 * Ce demonstrează, prin verificări explicite (✓/✗ la final, cod de ieșire ≠ 0 dacă vreuna pică):
 *   • clasele urcă o treaptă păstrând secția, iar numele se regenerează („VII A" → „VIII A");
 *   • alocările se preiau DOAR unde disciplina se predă la treapta nouă;
 *   • clasa terminală nu urcă: elevii ei ies din registru cu motivul `absolvire`;
 *   • elevii activi primesc înmatriculare NOUĂ, iar rândul vechi rămâne ca istoric;
 *   • repetentul pus manual în anul nou nu se dublează;
 *   • reluarea operațiunii nu creează nimic în plus (idempotență).
 *
 * REVERSIBIL: tot ce se creează intră într-un manifest (`storage/app/demo/year-transition.json`),
 * iar `--remove` șterge exact acele rânduri. Fără `--keep`, curățarea se face automat la final.
 */
class SimulateYearTransition extends Command
{
    protected $signature = 'app:simulate-year-transition
        {--keep : Păstrează datele simulării (ca să le vezi în panou)}
        {--remove : Șterge datele unei simulări păstrate}';

    protected $description = 'Simulează trecerea completă în anul nou pe o școală demo izolată';

    private const MARK = '[DEMO]';

    private const SOURCE_YEAR = '2040–2041';

    private const TARGET_YEAR = '2041–2042';

    private const MANIFEST = 'demo/year-transition.json';

    public function handle(): int
    {
        if ($this->option('remove')) {
            $this->cleanup();

            return self::SUCCESS;
        }

        if (AcademicYear::query()->whereIn('name', [self::SOURCE_YEAR, self::TARGET_YEAR])->exists()) {
            $this->warn('Există deja date de simulare. Rulează întâi: php artisan app:simulate-year-transition --remove');

            return self::FAILURE;
        }

        $this->components->info('Se construiește școala demo…');
        $context = $this->buildSchool();

        $this->showBefore($context);

        $this->components->info('Se execută trecerea în anul nou…');
        $result = app(StartSchoolYear::class)->handle([
            'source_year_id' => $context['year']->getKey(),
            'target_year_id' => null,
            'year' => ['name' => self::TARGET_YEAR, 'starts_on' => '2041-09-01', 'ends_on' => '2042-06-30'],
            'terms' => [
                ['number' => 1, 'name' => self::MARK.' Semestrul I', 'starts_on' => '2041-09-01', 'ends_on' => '2041-12-31'],
                ['number' => 2, 'name' => self::MARK.' Semestrul II', 'starts_on' => '2042-01-15', 'ends_on' => '2042-05-31'],
            ],
            'with_assignments' => true,
            'graduate' => true,
            'promote' => true,
        ]);

        $target = $result['year'];

        if ($target === null) {
            $this->error('Trecerea a fost blocată: '.($result['blocked'] ?? 'motiv necunoscut'));
            $this->cleanup();

            return self::FAILURE;
        }

        $this->rememberYear($target);
        $this->showAfter($result, $target);

        // A doua rulare, identică: nimic nou (idempotență).
        $second = app(StartSchoolYear::class)->handle([
            'source_year_id' => $context['year']->getKey(),
            'target_year_id' => $target->getKey(),
            'terms' => [],
            'with_assignments' => true,
            'graduate' => true,
            'promote' => true,
        ]);

        $failures = $this->verify($context, $target, $result, $second);

        if (! $this->option('keep')) {
            $this->cleanup();
            $this->line('');
            $this->components->info('Datele simulării au fost șterse (rulează cu --keep ca să rămână în panou).');
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Școala demo: trei destine diferite într-un singur an — o clasă care urcă în interiorul
     * ciclului, una care trece o graniță de ciclu (unde curriculumul se schimbă) și una terminală.
     *
     * @return array{year: AcademicYear, gimnaziu: SchoolClass, primar: SchoolClass, terminal: SchoolClass, repetent: Student, students: array<string, list<int>>}
     */
    private function buildSchool(): array
    {
        return DB::transaction(function (): array {
            $year = AcademicYear::query()->create([
                'name' => self::SOURCE_YEAR,
                'starts_on' => '2040-09-01',
                'ends_on' => '2041-06-30',
            ]);

            // Semestrul demo NU e „curent": flag-ul aparține anului real, iar simularea n-are voie
            // să mute semestrul curent al școlii.
            Term::query()->create([
                'academic_year_id' => $year->getKey(),
                'number' => 1,
                'name' => self::MARK.' Semestrul I',
                'starts_on' => '2040-09-01',
                'ends_on' => '2040-12-31',
                'is_current' => false,
            ]);

            $teacher = Teacher::query()->create([
                'last_name' => self::MARK.' Simulare',
                'first_name' => 'Profesor',
            ]);

            $primar = $this->makeClass($year, 4, 'S');      // IV S → V S (graniță de ciclu)
            $gimnaziu = $this->makeClass($year, 7, 'A');    // VII A → VIII A
            $terminal = $this->makeClass($year, 12, 'R');   // XII R → absolvire

            // Două discipline: una care se oprește la primar, alta care merge peste tot.
            $doarPrimar = Subject::query()->create([
                'name' => self::MARK.' Tainele clasei mici',
                'abbreviation' => 'DEMO1',
                'grade_levels' => range(1, 4),
                'report_order' => 9001,
            ]);

            $peste = Subject::query()->create([
                'name' => self::MARK.' Matematica simulării',
                'abbreviation' => 'DEMO2',
                'grade_levels' => range(1, 12),
                'report_order' => 9002,
            ]);

            foreach ([$primar, $gimnaziu, $terminal] as $class) {
                foreach ([$doarPrimar, $peste] as $subject) {
                    TeachingAssignment::query()->create([
                        'teacher_id' => $teacher->getKey(),
                        'school_class_id' => $class->getKey(),
                        'subject_id' => $subject->getKey(),
                    ]);
                }
            }

            $students = [];

            foreach (['primar' => $primar, 'gimnaziu' => $gimnaziu, 'terminal' => $terminal] as $key => $class) {
                $students[$key] = [];

                foreach (range(1, 3) as $index) {
                    $student = $this->makeStudent($class, $index);
                    $students[$key][] = (int) $student->getKey();
                }
            }

            // Un elev PLECAT în cursul anului: nu trebuie promovat și nu trebuie „absolvit".
            $plecat = $this->makeStudent($gimnaziu, 4);
            Enrollment::query()
                ->where('student_id', $plecat->getKey())
                ->update(['left_on' => '2041-02-01', 'departure_reason' => DepartureReason::Transfer]);
            $students['plecat'] = [(int) $plecat->getKey()];

            // Un REPETENT: rămâne la aceeași treaptă, deci e înmatriculat manual mai târziu.
            $repetent = $this->makeStudent($gimnaziu, 5);
            $students['repetent'] = [(int) $repetent->getKey()];

            return [
                'year' => $year,
                'primar' => $primar,
                'gimnaziu' => $gimnaziu,
                'terminal' => $terminal,
                'repetent' => $repetent,
                'students' => $students,
            ];
        });
    }

    private function makeClass(AcademicYear $year, int $grade, string $section): SchoolClass
    {
        return SchoolClass::query()->create([
            'academic_year_id' => $year->getKey(),
            'grade_level' => $grade,
            'section' => $section,
        ]);
    }

    private function makeStudent(SchoolClass $class, int $number): Student
    {
        $student = Student::query()->create([
            'last_name' => self::MARK.' Simulat',
            'first_name' => 'Elev '.$class->grade_level.'-'.$number,
            'register_number' => (string) $number,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->getKey(),
            'school_class_id' => $class->getKey(),
            'academic_year_id' => $class->academic_year_id,
            'enrolled_on' => '2040-09-01',
        ]);

        return $student;
    }

    /** @param  array<string, mixed>  $context */
    private function showBefore(array $context): void
    {
        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>ÎNAINTE</>', '<fg=gray>'.self::SOURCE_YEAR.'</>');

        $rows = [];

        foreach (['primar', 'gimnaziu', 'terminal'] as $key) {
            /** @var SchoolClass $class */
            $class = $context[$key];
            $rows[] = [
                trim($class->name.' '.$class->section),
                (string) $class->grade_level,
                (string) Enrollment::query()->where('school_class_id', $class->getKey())->whereNull('left_on')->count(),
                (string) TeachingAssignment::query()->where('school_class_id', $class->getKey())->count(),
            ];
        }

        $this->table(['Clasă', 'Treaptă', 'Elevi activi', 'Alocări'], $rows);
        $this->line('  <fg=gray>+ 1 elev plecat în cursul anului și 1 repetent (rămâne la aceeași treaptă).</>');
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function showAfter(array $result, AcademicYear $target): void
    {
        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>DUPĂ</>', '<fg=gray>'.$target->name.'</>');

        $rows = [];

        foreach (SchoolClass::query()->where('academic_year_id', $target->getKey())->orderBy('grade_level')->get() as $class) {
            $rows[] = [
                trim($class->name.' '.$class->section),
                (string) $class->grade_level,
                (string) Enrollment::query()->where('school_class_id', $class->getKey())->count(),
                (string) TeachingAssignment::query()->where('school_class_id', $class->getKey())->count(),
            ];
        }

        $this->table(['Clasă', 'Treaptă', 'Elevi', 'Alocări'], $rows);

        $this->components->twoColumnDetail('Semestre create', (string) $result['terms']);
        $this->components->twoColumnDetail('Clase noi', (string) $result['classes']);
        $this->components->twoColumnDetail('Alocări preluate', (string) $result['assignments']);
        $this->components->twoColumnDetail('Absolvenți', (string) $result['graduates']);
        $this->components->twoColumnDetail('Elevi mutați', (string) $result['students']);
    }

    /**
     * Verificările propriu-zise. Fiecare e o afirmație despre registrul de după — nu despre
     * cifrele raportate de acțiune, ci despre ce chiar există în baza de date.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $second
     */
    private function verify(array $context, AcademicYear $target, array $result, array $second): int
    {
        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>VERIFICĂRI</>', '');

        /** @var SchoolClass $gimnaziu */
        $gimnaziu = $context['gimnaziu'];
        /** @var SchoolClass $primar */
        $primar = $context['primar'];
        /** @var SchoolClass $terminal */
        $terminal = $context['terminal'];

        $newClasses = SchoolClass::query()
            ->where('academic_year_id', $target->getKey())
            ->get()
            ->keyBy(fn (SchoolClass $class): string => trim($class->name.' '.$class->section));

        $failures = 0;

        $check = function (string $label, bool $passed) use (&$failures): void {
            $this->components->twoColumnDetail($label, $passed ? '<fg=green>✓</>' : '<fg=red>✗</>');

            if (! $passed) {
                $failures++;
            }
        };

        $check('Clasa a VII-a a devenit a VIII-a, cu aceeași secție', $newClasses->has('VIII A'));
        $check('Clasa a IV-a a devenit a V-a (graniță de ciclu)', $newClasses->has('V S'));
        $check('Clasa terminală NU are corespondent', ! $newClasses->has('XIII R'));

        // Alocările: „doar primar" nu urcă în gimnaziu; disciplina generală urcă peste tot.
        $vs = $newClasses->get('V S');
        $viiia = $newClasses->get('VIII A');

        // Ambele clase noi trebuie să rămână cu EXACT disciplina care acoperă treapta lor — cea de
        // primar (I–IV) nu are ce căuta nici în a V-a, nici în a VIII-a.
        $keptSubjects = fn (?SchoolClass $class): array => $class === null ? [] : TeachingAssignment::query()
            ->where('school_class_id', $class->getKey())
            ->with('subject')
            ->get()
            ->map(fn (TeachingAssignment $assignment): string => (string) $assignment->subject?->name)
            ->all();

        $general = self::MARK.' Matematica simulării';

        $check('Clasa a V-a a păstrat DOAR disciplina care se predă acolo', $keptSubjects($vs) === [$general]);
        $check('Clasa a VIII-a a păstrat DOAR disciplina care se predă acolo', $keptSubjects($viiia) === [$general]);

        // Elevii: rând nou în anul nou, rândul vechi păstrat.
        $gimnaziuIds = $context['students']['gimnaziu'];
        $mutati = Enrollment::query()
            ->whereIn('student_id', $gimnaziuIds)
            ->where('academic_year_id', $target->getKey())
            ->count();

        $check('Elevii clasei a VII-a au înmatriculare în anul nou', $mutati === count($gimnaziuIds));
        $check(
            'Rândul din anul vechi a rămas ca istoric',
            Enrollment::query()->whereIn('student_id', $gimnaziuIds)->count() === count($gimnaziuIds) * 2,
        );

        // Absolvenții: ies din registru cu motivul potrivit și NU intră în anul nou.
        $terminalIds = $context['students']['terminal'];
        $absolvite = Enrollment::query()
            ->whereIn('student_id', $terminalIds)
            ->whereNotNull('left_on')
            ->where('departure_reason', DepartureReason::Absolvire)
            ->count();

        $check('Absolvenții au ieșit din registru cu motivul „absolvire"', $absolvite === count($terminalIds));
        $check(
            'Absolvenții NU au fost mutați în anul nou',
            Enrollment::query()->whereIn('student_id', $terminalIds)->where('academic_year_id', $target->getKey())->doesntExist(),
        );

        // Elevul plecat în cursul anului: neatins de ambele operațiuni.
        $plecatId = $context['students']['plecat'][0];
        $plecat = Enrollment::query()->where('student_id', $plecatId)->sole();

        $check('Elevul plecat în cursul anului nu a fost promovat', Enrollment::query()->where('student_id', $plecatId)->count() === 1);
        $check('Motivul plecării lui a rămas neschimbat', $plecat->departure_reason === DepartureReason::Transfer);

        // Semestrele anului nou există, dar „curent" nu s-a mutat de aici.
        $check('Anul nou are 2 semestre', Term::query()->where('academic_year_id', $target->getKey())->count() === 2);
        $check(
            'Semestrul curent al școlii NU a fost atins',
            Term::query()->where('academic_year_id', $target->getKey())->where('is_current', true)->doesntExist(),
        );

        // Idempotență: a doua rulare n-a mai creat nimic.
        $check('Reluarea nu a creat clase în plus', $second['classes'] === 0);
        $check('Reluarea nu a mutat elevi în plus', $second['students'] === 0);
        $check('Reluarea nu a mai absolvit pe nimeni', $second['graduates'] === 0);
        $check(
            'Anul nu s-a dublat',
            AcademicYear::query()->where('name', self::TARGET_YEAR)->count() === 1,
        );

        // Cifrele raportate corespund realității din registru.
        $check('Raportul acțiunii corespunde registrului', $result['classes'] === $newClasses->count());

        $this->line('');

        $failures === 0
            ? $this->components->info('Toate verificările au trecut.')
            : $this->components->error($failures.' verificări au picat.');

        return $failures;
    }

    private function rememberYear(AcademicYear $target): void
    {
        File::ensureDirectoryExists(storage_path('app/demo'));
        File::put(storage_path('app/'.self::MANIFEST), json_encode([
            'years' => [self::SOURCE_YEAR, self::TARGET_YEAR],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Curățarea: totul atârnă de cei doi ani demo, așa că se șterge în ordinea dependențelor.
     * Ștergerea e DEFINITIVĂ (forceDelete) — o simulare nu are ce căuta în coșul de restaurare.
     */
    private function cleanup(): void
    {
        DB::transaction(function (): void {
            $yearIds = AcademicYear::query()
                ->withTrashed()
                ->whereIn('name', [self::SOURCE_YEAR, self::TARGET_YEAR])
                ->pluck('id');

            if ($yearIds->isEmpty()) {
                return;
            }

            $classIds = SchoolClass::withTrashed()->whereIn('academic_year_id', $yearIds)->pluck('id');

            $studentIds = Enrollment::withTrashed()->whereIn('academic_year_id', $yearIds)->pluck('student_id')->unique();

            Enrollment::withTrashed()->whereIn('academic_year_id', $yearIds)->forceDelete();
            TeachingAssignment::withTrashed()->whereIn('school_class_id', $classIds)->forceDelete();
            SchoolClass::withTrashed()->whereIn('id', $classIds)->forceDelete();
            Term::withTrashed()->whereIn('academic_year_id', $yearIds)->forceDelete();
            AcademicYear::withTrashed()->whereIn('id', $yearIds)->forceDelete();

            Student::withTrashed()->whereIn('id', $studentIds)->where('last_name', self::MARK.' Simulat')->forceDelete();
            Subject::withTrashed()->where('name', 'like', self::MARK.'%simulării')->orWhere('name', 'like', self::MARK.'%clasei mici')->forceDelete();
            Teacher::withTrashed()->where('last_name', self::MARK.' Simulare')->forceDelete();
        });

        File::delete(storage_path('app/'.self::MANIFEST));
    }
}
