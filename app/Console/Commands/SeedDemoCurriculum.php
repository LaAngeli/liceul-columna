<?php

namespace App\Console\Commands;

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Enums\SchoolCycle;
use App\Models\Term;
use App\Observers\GradeObserver;
use App\Support\SchoolCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Aduce ȘCOALA DEMO la scara unei școli reale (cerința beneficiarului, 04.08.2026).
 *
 * Ce lipsea: zona demo aloca 4–5 discipline pe clasă, mereu ACELEAȘI ({@see SeedDemoZone}) — și,
 * mai rău, discipline de gimnaziu (Fizică, Istoria) puse peste clase primare, unde nu se predau.
 * Un diriginte care intra să testeze vedea o clasă cu 5 discipline improbabile, fără orar, iar în
 * anul nou (deschis prin „Trecerea în anul nou") clasele promovate rămâneau fără nicio alocare,
 * fiindcă disciplinele vechi nu se predau la treapta nouă.
 *
 * Ce face comanda:
 *  1. CLASELE I ale anului nou + bobocii — singurul lucru pe care trecerea în anul nou NU-l poate
 *     face singură (elevii de clasa I nu vin din promovare, ci din înscriere).
 *  2. GAMA COMPLETĂ de discipline, pe treaptă, din planul-cadru de mai jos: fiecare clasă demo
 *     (an curent ȘI an nou) primește disciplinele care se predau LA TREAPTA EI, cu catedră proprie
 *     — la primar învățătorul ține trunchiul, specialiștii vin peste (muzică, sport, engleză…),
 *     exact ca într-o școală adevărată.
 *  3. VIAȚA anului curent: note pe fiecare disciplină (cu tipul de notare corect — cifre unde se
 *     notează cu cifre, calificative unde se notează cu calificative), absențe în TOATE cele trei
 *     stări (inclusiv „fără statut", ca dirigintele să aibă ce tria), teme și ORAR săptămânal.
 *
 * CURĂȚARE LA GO-LIVE — tot ce se creează aici e marcat sau înregistrat:
 *  • marcaj textual `[DEMO]` pe clase, elevi, profesori și subiectul temelor;
 *  • MANIFEST exact (`storage/app/demo/curriculum.json`) cu id-urile fiecărui rând creat;
 *  • `--remove` șterge fix acele rânduri, în ordine FK-safe.
 * În plus, ADOPTĂ clasele demo promovate în anul nou: trecerea le derivă numele din treaptă
 * („[DEMO] 1A" → „II"), deci ele PIERD marcajul și ar fi rămas în producție după curățare. Comanda
 * le redenumește cu marcaj și le trece în manifest, ca `--remove` să le poată șterge.
 *
 * Scrie prin query builder (fără observers/audit/notificări), ca toate seederele demo. Mediile se
 * recalculează la final prin `app:compute-averages`.
 */
class SeedDemoCurriculum extends Command
{
    protected $signature = 'app:seed-demo-curriculum
        {--first-graders=10 : Câți boboci în fiecare clasă I}
        {--remove : Șterge tot ce a creat comanda (folosind manifestul)}';

    protected $description = 'Completează școala demo: clasele I ale anului nou + gama completă de discipline, note, absențe, teme și orar';

    private const MARK = '[DEMO]';

    private const MANIFEST = 'demo/curriculum.json';

    /**
     * PLANUL-CADRU, pe cicluri, în ordinea din catalog. Numele sunt cele CANONICE din nomenclator:
     * potrivirea se face pe nume + treaptă, deci o disciplină care nu există (sau nu se predă la
     * treapta clasei) e sărită, nu inventată. Lista scrisă de mână, nu „toate disciplinele din
     * tabel", tocmai ca rândurile de test rămase prin nomenclator să nu ajungă în catalog.
     *
     * `true` = ținută de ÎNVĂȚĂTOR la primar (trunchiul clasei); `false` = specialist.
     *
     * @var array<string, array<string, bool>>
     */
    private const CURRICULUM = [
        'primar' => [
            'Limba și literatura română' => true,
            'Matematică' => true,
            'Științe' => true,
            'Istoria românilor și universală' => true,
            'Dezvoltare personală' => true,
            'Educație moral-spirituală' => true,
            'În împărăția lui Mate' => true,
            'Tainele comunicării' => true,
            'Limba străină 1 (engleza)' => false,
            'Educație muzicală' => false,
            'Educație plastică' => false,
            'Educație fizică' => false,
            'Educație tehnologică' => false,
            'Educație digitală' => false,
        ],
        'gimnaziu' => [
            'Limba și literatura română' => false,
            'Limba străină 1 (engleza)' => false,
            'Matematică' => false,
            'Istoria românilor și universală' => false,
            'Geografie' => false,
            'Biologie' => false,
            'Fizică' => false,
            'Chimie' => false,
            'Informatică' => false,
            'Științe' => false,
            'Educație pentru societate' => false,
            'Educație tehnologică' => false,
            'Educație muzicală' => false,
            'Educație plastică' => false,
            'Educație fizică' => false,
        ],
        'liceu' => [
            'Limba și literatura română' => false,
            'Limba străină 1 (engleza)' => false,
            'Matematică' => false,
            'Istoria românilor și universală' => false,
            'Geografie' => false,
            'Biologie' => false,
            'Fizică' => false,
            'Chimie' => false,
            'Informatică' => false,
            'Literatura universală' => false,
            'Educație pentru societate' => false,
            'Educație pentru sănătate' => false,
            'Educație fizică' => false,
        ],
    ];

    /** Nume pentru boboci — interne, ca în {@see SeedDemoZone} (faker lipsește în producție). */
    private const LAST_NAMES = ['Popescu', 'Rusu', 'Ciobanu', 'Moraru', 'Ungureanu', 'Rotaru', 'Munteanu', 'Cojocaru', 'Bejan', 'Lungu', 'Cebotari', 'Sandu', 'Vieru', 'Cazacu', 'Bivol', 'Botnaru', 'Frunză', 'Zaharia', 'Dragomir', 'Balan', 'Croitoru', 'Guțu', 'Melnic', 'Grosu', 'Damian', 'Racu', 'Ursu'];

    private const FIRST_NAMES = ['Andrei', 'Maria', 'Ion', 'Ana', 'Mihai', 'Elena', 'Nicolae', 'Cristina', 'Vasile', 'Daniela', 'Alexandru', 'Natalia', 'Dumitru', 'Irina', 'Sergiu', 'Victoria', 'Petru', 'Tatiana', 'Radu', 'Diana', 'Aliona', 'Valentin', 'Corina', 'Denis', 'Gabriela', 'Marius', 'Nadia'];

    /** Subiecte de temă, per ciclu — scurte, plauzibile, marcate. */
    private const TOPICS = ['recapitulare', 'exerciții de consolidare', 'lucru individual', 'pregătire pentru evaluare', 'lectură și analiză', 'aplicații practice'];

    /** Alocări imposibile șterse (disciplină care nu se predă la treapta clasei). */
    private int $pruned = 0;

    /** @var array<string, mixed> */
    private array $manifest = [
        'classes' => [], 'students' => [], 'teachers' => [], 'assignments' => [],
        'grades' => [], 'absences' => [], 'homework' => [], 'lessons' => [],
        'adopted_classes' => [],
    ];

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        if (File::exists(storage_path('app/'.self::MANIFEST))) {
            $this->components->error('Există deja un manifest ('.self::MANIFEST.'). Rulează întâi `--remove`.');

            return self::FAILURE;
        }

        $currentTerm = SchoolCalendar::currentTerm();

        if ($currentTerm === null) {
            $this->components->error('Nu există un semestru curent — anul de lucru nu poate fi stabilit.');

            return self::FAILURE;
        }

        $currentYearId = (int) $currentTerm->academic_year_id;
        $newYearId = $this->nextYearId($currentYearId);

        DB::transaction(function () use ($currentYearId, $newYearId, $currentTerm): void {
            $now = Carbon::now();

            // ── 1. Clasele demo de lucrat ────────────────────────────────────────────────────
            $currentClasses = DB::table('school_classes')
                ->where('academic_year_id', $currentYearId)
                ->where('name', 'like', self::MARK.'%')
                ->whereNull('deleted_at')
                ->get(['id', 'grade_level', 'homeroom_teacher_id', 'section'])
                ->map(fn (object $row): array => self::classShape($row))
                ->all();

            $newClasses = $newYearId === null ? [] : $this->adoptPromotedClasses($newYearId, $now);

            // ── 2. Clasele I ale anului nou + boboci ─────────────────────────────────────────
            $firstGrade = $newYearId === null
                ? []
                : $this->makeFirstGradeClasses($newYearId, $now);

            // ── 3. Gama completă de discipline, pe fiecare clasă demo ────────────────────────
            $catedra = [];
            $assignmentsByClass = [];

            // Întâi CURĂȚĂM alocările imposibile: zona demo veche punea aceleași 7 discipline peste
            // toate clasele, inclusiv Fizică sau Istoria la clasa I — unde nu se predau. Rămase, ele
            // ar fi stat lângă disciplinele corecte, iar borderoul unei clase primare ar fi arătat
            // 19 discipline, dintre care 5 imposibile.
            foreach ([...$currentClasses, ...$newClasses] as $class) {
                $this->pruneImpossibleAssignments($class);
            }

            foreach ([...$currentClasses, ...$newClasses, ...$firstGrade] as $class) {
                $assignmentsByClass[$class['id']] = $this->assignCurriculum($class, $catedra, $now);
            }

            // ── 4. Viața anului CURENT: note, absențe, teme, orar ────────────────────────────
            foreach ($currentClasses as $class) {
                /** @var list<int> $students */
                $students = DB::table('enrollments')
                    ->where('school_class_id', $class['id'])
                    ->whereNull('left_on')
                    ->whereNull('deleted_at')
                    ->pluck('student_id')
                    ->map(intval(...))
                    ->values()
                    ->all();

                if ($students === []) {
                    continue;
                }

                $this->seedGrades($class, $assignmentsByClass[$class['id']], $students, (int) $currentTerm->id, $currentTerm, $now);
                $this->seedAbsences($class, $assignmentsByClass[$class['id']], $students, (int) $currentTerm->id, $now);
                $this->seedHomework($class, $assignmentsByClass[$class['id']], $now);
            }

            // Orarul: și în anul curent, și în cel nou — fără el, cabinetul familiei nu poate
            // calcula riscul de amânare („orarul clasei nu cuprinde încă aceste discipline").
            foreach ($currentClasses as $class) {
                $this->seedTimetable($class, $assignmentsByClass[$class['id']], $currentYearId, $now);
            }

            foreach ([...$newClasses, ...$firstGrade] as $class) {
                $this->seedTimetable($class, $assignmentsByClass[$class['id']], (int) $newYearId, $now);
            }
        });

        File::ensureDirectoryExists(storage_path('app/demo'));
        File::put(
            storage_path('app/'.self::MANIFEST),
            (string) json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $this->report();

        $this->components->info('Recalculez mediile semestriale…');
        $this->call('app:compute-averages');

        return self::SUCCESS;
    }

    /** Anul care începe DUPĂ anul curent — cel deschis prin „Trecerea în anul nou". */
    private function nextYearId(int $currentYearId): ?int
    {
        $currentEnd = DB::table('academic_years')->where('id', $currentYearId)->value('ends_on');

        $id = DB::table('academic_years')
            ->whereNull('deleted_at')
            ->when($currentEnd !== null, fn ($q) => $q->where('starts_on', '>', $currentEnd))
            ->orderBy('starts_on')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Clasele demo PROMOVATE în anul nou: trecerea le derivă numele din treaptă, deci pierd
     * marcajul „[DEMO]" și ar rămâne în producție după curățare. Le redăm marcajul (cu numele
     * vechi în manifest) și le trecem în manifest ca șterse la `--remove`.
     *
     * Prudență: se adoptă DOAR clasele în care TOȚI elevii înmatriculați sunt elevi demo — o clasă
     * cu fie și un elev real nu e demo și nu se atinge.
     *
     * @return list<array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}>
     */
    private function adoptPromotedClasses(int $newYearId, Carbon $now): array
    {
        $classes = DB::table('school_classes')
            ->where('academic_year_id', $newYearId)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'section', 'grade_level', 'homeroom_teacher_id']);

        $adopted = [];

        foreach ($classes as $class) {
            $total = DB::table('enrollments')
                ->where('school_class_id', $class->id)
                ->whereNull('deleted_at')
                ->count();

            if ($total === 0) {
                continue;
            }

            $demo = DB::table('enrollments')
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->where('enrollments.school_class_id', $class->id)
                ->whereNull('enrollments.deleted_at')
                ->where('students.last_name', 'like', self::MARK.'%')
                ->count();

            if ($demo !== $total) {
                continue;
            }

            if (! str_starts_with((string) $class->name, self::MARK)) {
                $this->manifest['adopted_classes'][] = ['id' => (int) $class->id, 'name' => (string) $class->name];

                DB::table('school_classes')->where('id', $class->id)->update([
                    'name' => self::MARK.' '.$class->name,
                    'updated_at' => $now,
                ]);
            }

            $adopted[] = self::classShape($class);
        }

        return $adopted;
    }

    /**
     * Rândul brut al unei clase → forma pe care o folosește comanda. Query builder-ul întoarce
     * `stdClass` fără tipuri; forma explicită ține codul (și analiza statică) pe teren solid.
     *
     * @return array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}
     */
    private static function classShape(object $row): array
    {
        /** @var array<string, mixed> $values */
        $values = get_object_vars($row);

        return [
            'id' => (int) ($values['id'] ?? 0),
            'grade_level' => (int) ($values['grade_level'] ?? 0),
            'homeroom_teacher_id' => isset($values['homeroom_teacher_id']) ? (int) $values['homeroom_teacher_id'] : null,
            'section' => isset($values['section']) ? (string) $values['section'] : null,
        ];
    }

    /**
     * Clasele I ale anului nou + bobocii. Nu vin din promovare (n-au de unde), deci sunt exact
     * pasul manual pe care ecranul „Trecerea în anul nou" îl anunță ca rămas de făcut.
     *
     * @return list<array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}>
     */
    private function makeFirstGradeClasses(int $newYearId, Carbon $now): array
    {
        $perClass = max(1, (int) $this->option('first-graders'));
        $yearStart = DB::table('academic_years')->where('id', $newYearId)->value('starts_on');
        $created = [];

        foreach (['A', 'B'] as $section) {
            // Învățătoarea clasei — la primar ea e și diriginte, și profesor de trunchi.
            $homeroomId = $this->makeTeacher('Învățătoare I'.$section, $now);

            $classId = (int) DB::table('school_classes')->insertGetId([
                'academic_year_id' => $newYearId,
                'grade_level' => 1,
                'name' => self::MARK.' I',
                'section' => $section,
                'homeroom_teacher_id' => $homeroomId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->manifest['classes'][] = $classId;

            foreach (range(1, $perClass) as $ignored) {
                $studentId = (int) DB::table('students')->insertGetId([
                    'last_name' => self::MARK.' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                    'first_name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
                    'sex' => ['m', 'f'][random_int(0, 1)],
                    'register_number' => 'D'.random_int(10000, 99999),
                    'second_language' => 'nu',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->manifest['students'][] = $studentId;

                DB::table('enrollments')->insert([
                    'student_id' => $studentId,
                    'school_class_id' => $classId,
                    'academic_year_id' => $newYearId,
                    // Înscrierea bobocilor se face în vara dinaintea anului: data de start a anului.
                    'enrolled_on' => $yearStart !== null ? Carbon::parse($yearStart)->toDateString() : $now->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $created[] = [
                'id' => $classId,
                'grade_level' => 1,
                'homeroom_teacher_id' => $homeroomId,
                'section' => $section,
            ];
        }

        return $created;
    }

    /**
     * Șterge alocările (și evaluările lor) pentru discipline care NU se predau la treapta clasei.
     *
     * ⚠️ Ireversibil prin `--remove`: rândurile șterse nu se pot reînvia dintr-un manifest de
     * id-uri. E o alegere conștientă — sunt date demo INVALIDE (o notă la Fizică într-a I-a n-ar fi
     * putut fi introdusă vreodată prin interfață, garda de treaptă o refuză). Curățarea completă a
     * zonei demo rămâne oricum `app:seed-demo-zone --remove`.
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     */
    private function pruneImpossibleAssignments(array $class): void
    {
        $grade = $class['grade_level'];

        $impossible = DB::table('teaching_assignments')
            ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->where('teaching_assignments.school_class_id', $class['id'])
            ->whereNull('teaching_assignments.deleted_at')
            ->where(fn ($q) => $q->where('subjects.min_grade', '>', $grade)->orWhere('subjects.max_grade', '<', $grade))
            ->pluck('subjects.id')
            ->all();

        if ($impossible === []) {
            return;
        }

        // Mediile calculate din notele imposibile trebuie să plece odată cu ele: o medie fără note
        // rămâne pe ecran ca o afirmație despre ceva ce nu s-a întâmplat (copil de clasa I cu medie
        // la Fizică). `app:compute-averages` le retrage acum și el, ca plasă generală.
        DB::table('term_averages')
            ->whereIn('subject_id', $impossible)
            ->whereIn('student_id', fn ($query) => $query
                ->select('student_id')
                ->from('enrollments')
                ->where('school_class_id', $class['id'])
                ->whereNull('deleted_at'))
            ->delete();

        DB::table('grades')->where('school_class_id', $class['id'])->whereIn('subject_id', $impossible)->delete();
        DB::table('absences')->where('school_class_id', $class['id'])->whereIn('subject_id', $impossible)->delete();
        DB::table('lessons')->where('school_class_id', $class['id'])->whereIn('subject_id', $impossible)->delete();
        DB::table('teaching_assignments')->where('school_class_id', $class['id'])->whereIn('subject_id', $impossible)->delete();

        $this->pruned += count($impossible);
    }

    /**
     * Alocă disciplinele treptei clasei. La primar trunchiul merge la învățător (dirigintele
     * clasei), restul la catedra de specialitate — un singur profesor pe disciplină în toată
     * școala demo, ca într-o școală mică reală.
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  array<string, int>  $catedra  disciplină → teacher_id (se completează pe parcurs)
     * @return list<array{subject_id: int, teacher_id: int, grading_type: string}>
     */
    private function assignCurriculum(array $class, array &$catedra, Carbon $now): array
    {
        $grade = $class['grade_level'];
        $cycle = SchoolCycle::fromGradeLevel($grade)->value;
        $plan = self::CURRICULUM[$cycle];
        $out = [];

        foreach ($plan as $subjectName => $byHomeroom) {
            $subject = DB::table('subjects')
                ->whereNull('deleted_at')
                ->where('name', $subjectName)
                ->where(fn ($q) => $q->whereNull('min_grade')->orWhere('min_grade', '<=', $grade))
                ->where(fn ($q) => $q->whereNull('max_grade')->orWhere('max_grade', '>=', $grade))
                ->orderBy('min_grade')
                ->first(['id', 'grading_type']);

            if ($subject === null) {
                continue; // nu se predă la treapta asta — plan-cadru, nu inventar
            }

            $teacherId = $byHomeroom && $class['homeroom_teacher_id'] !== null
                ? $class['homeroom_teacher_id']
                : ($catedra[$subjectName] ??= $this->makeTeacher('Prof. '.$subjectName, $now));

            $existing = DB::table('teaching_assignments')
                ->where('school_class_id', $class['id'])
                ->where('subject_id', $subject->id)
                ->whereNull('deleted_at')
                ->first(['id', 'teacher_id']);

            if ($existing !== null) {
                // Alocarea există deja (zona demo veche): o păstrăm ca atare, ca să nu dublăm.
                $out[] = ['subject_id' => (int) $subject->id, 'teacher_id' => (int) $existing->teacher_id, 'grading_type' => (string) $subject->grading_type];

                continue;
            }

            $id = (int) DB::table('teaching_assignments')->insertGetId([
                'teacher_id' => $teacherId,
                'subject_id' => $subject->id,
                'school_class_id' => $class['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->manifest['assignments'][] = $id;

            $out[] = ['subject_id' => (int) $subject->id, 'teacher_id' => $teacherId, 'grading_type' => (string) $subject->grading_type];
        }

        return $out;
    }

    private function makeTeacher(string $label, Carbon $now): int
    {
        $id = (int) DB::table('teachers')->insertGetId([
            'last_name' => self::MARK,
            'first_name' => $label,
            'sex' => ['m', 'f'][random_int(0, 1)],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->manifest['teachers'][] = $id;

        return $id;
    }

    /**
     * Note pe fiecare disciplină a clasei, în semestrul curent: 2–5 pe elev, cu tipul de notare
     * al disciplinei (cifre / calificative). Sumativa (teză/ESS) se pune DOAR unde există
     * desemnare — aceeași regulă ca garda din {@see GradeObserver}.
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     * @param  list<int>  $students
     */
    private function seedGrades(array $class, array $assignments, array $students, int $termId, Term $term, Carbon $now): void
    {
        $from = Carbon::parse((string) $term->starts_on);
        $to = Carbon::parse((string) $term->ends_on)->min(Carbon::today());
        $span = max(1, (int) $from->diffInDays($to));

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($assignments as $assignment) {
            // Desemnările nu au soft delete — de aceea nu se filtrează pe `deleted_at`.
            $isSummative = DB::table('summative_designations')
                ->where('school_class_id', $class['id'])
                ->where('subject_id', $assignment['subject_id'])
                ->exists();

            $summativeType = $class['grade_level'] >= 10 ? EvaluationType::Teza : EvaluationType::Esi;

            foreach ($students as $studentId) {
                foreach (range(1, random_int(2, 5)) as $i) {
                    $rows[] = $this->gradeRow(
                        $class, $assignment, $studentId, $termId,
                        $from->copy()->addDays(random_int(0, $span)),
                        EvaluationType::Curenta, $now,
                    );
                }

                if ($isSummative) {
                    $rows[] = $this->gradeRow(
                        $class, $assignment, $studentId, $termId,
                        $to->copy()->subDays(random_int(0, 10)),
                        $summativeType, $now,
                    );
                }
            }
        }

        $this->insertAndRemember('grades', $rows, 'grades');
    }

    /**
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  array{subject_id: int, teacher_id: int, grading_type: string}  $assignment
     * @return array<string, mixed>
     */
    private function gradeRow(array $class, array $assignment, int $studentId, int $termId, Carbon $on, EvaluationType $type, Carbon $now): array
    {
        $numeric = $assignment['grading_type'] === GradingType::Numeric->value;

        return [
            'student_id' => $studentId,
            'subject_id' => $assignment['subject_id'],
            'school_class_id' => $class['id'],
            'term_id' => $termId,
            'teacher_id' => $assignment['teacher_id'],
            'graded_on' => $on->toDateString(),
            'evaluation_type' => $type->value,
            // Distribuție plauzibilă: majoritatea 7–10, rar sub 6 (o școală, nu un generator uniform).
            'value' => $numeric ? [5, 6, 7, 7, 8, 8, 8, 9, 9, 10][random_int(0, 9)] : null,
            'calificativ' => $numeric ? null : ['FB', 'FB', 'B', 'B', 'S'][random_int(0, 4)],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Absențe în TOATE cele trei stări. Cele „fără statut" sunt datate în ultimele zile de curs,
     * ca dirigintele să aibă o coadă de triaj reală (badge-ul din meniu + acțiunile ✓/✗).
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     * @param  list<int>  $students
     */
    private function seedAbsences(array $class, array $assignments, array $students, int $termId, Carbon $now): void
    {
        if ($assignments === []) {
            return;
        }

        $lastSchoolDay = Carbon::parse((string) DB::table('terms')->where('id', $termId)->value('ends_on'))->min(Carbon::today());

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        // ~40% dintre elevi au absențe; fiecare 1–4, împrăștiate pe ultimele două luni.
        foreach ($students as $studentId) {
            if (random_int(1, 10) > 4) {
                continue;
            }

            foreach (range(1, random_int(1, 4)) as $ignored) {
                $assignment = $assignments[array_rand($assignments)];
                $on = $lastSchoolDay->copy()->subDays(random_int(0, 60));

                // 1/3 rămân FĂRĂ STATUT (coada dirigintelui), restul se împart motivate/nemotivate.
                $status = [null, null, true, false, false][random_int(0, 4)];

                $rows[] = [
                    'student_id' => $studentId,
                    'subject_id' => $assignment['subject_id'],
                    'school_class_id' => $class['id'],
                    'term_id' => $termId,
                    'teacher_id' => $assignment['teacher_id'],
                    'occurred_on' => $on->toDateString(),
                    'is_motivated' => $status,
                    'motivation_deadline' => $status === true ? null : $on->copy()->addWeekdays(5)->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Ziua de triaj: ultima zi de curs, câțiva elevi fără statut — badge-ul dirigintelui pornește
        // de la ceva vizibil, nu de la o coadă împrăștiată prin semestru.
        foreach (array_slice($students, 0, 3) as $studentId) {
            $assignment = $assignments[array_rand($assignments)];

            $rows[] = [
                'student_id' => $studentId,
                'subject_id' => $assignment['subject_id'],
                'school_class_id' => $class['id'],
                'term_id' => $termId,
                'teacher_id' => $assignment['teacher_id'],
                'occurred_on' => $lastSchoolDay->toDateString(),
                'is_motivated' => null,
                'motivation_deadline' => $lastSchoolDay->copy()->addWeekdays(5)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Anti-duplicat (elev + zi + disciplină) — aceeași regulă ca EnforcesAbsenceScope.
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<array<string, mixed>> $unique */
        $unique = [];

        foreach ($rows as $row) {
            $key = $row['student_id'].'|'.$row['occurred_on'].'|'.$row['subject_id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $row;
        }

        $this->insertAndRemember('absences', $unique, 'absences');
    }

    /**
     * Teme recente, câte una pe disciplină — subiectul poartă marcajul, ca la curățare să se vadă
     * din listă ce e demo.
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     */
    private function seedHomework(array $class, array $assignments, Carbon $now): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($assignments as $assignment) {
            if (random_int(1, 10) > 6) {
                continue; // nu fiecare disciplină are temă în fereastra afișată
            }

            $subjectName = (string) DB::table('subjects')->where('id', $assignment['subject_id'])->value('name');

            $rows[] = [
                'subject_id' => $assignment['subject_id'],
                'teacher_id' => $assignment['teacher_id'],
                'subject_name' => $subjectName,
                'grade_level' => $class['grade_level'],
                'section' => $class['section'],
                'assigned_on' => Carbon::today()->subDays(random_int(0, 20))->toDateString(),
                'topic' => self::MARK.' '.self::TOPICS[array_rand(self::TOPICS)],
                'required_task' => 'Exercițiile de la lecția curentă.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertAndRemember('homework_assignments', $rows, 'homework');
    }

    /**
     * Orar săptămânal: disciplinele clasei împrăștiate luni–vineri, în ordine, fără ferestre.
     * Fără orar, cabinetul familiei nu poate calcula riscul de amânare (îi lipsește numărul de ore
     * programate) și o spune explicit pe ecran — un gol care se vedea în demo.
     *
     * @param  array{id: int, grade_level: int, homeroom_teacher_id: int|null, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     */
    private function seedTimetable(array $class, array $assignments, int $yearId, Carbon $now): void
    {
        if ($assignments === []) {
            return;
        }

        // `lessons` nu are soft delete (orarul se rescrie, nu se arhivează) — fără filtru pe deleted_at.
        $existing = DB::table('lessons')
            ->where('school_class_id', $class['id'])
            ->where('academic_year_id', $yearId)
            ->exists();

        if ($existing) {
            return; // orarul clasei e deja definit — nu-l rescriem
        }

        // 5 zile × 5 lecții: disciplinele se rotesc, deci fiecare apare de 1–3 ori pe săptămână.
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $index = 0;

        foreach (range(1, 5) as $day) {
            foreach (range(1, 5) as $lesson) {
                $assignment = $assignments[$index % count($assignments)];
                $index++;

                $rows[] = [
                    'academic_year_id' => $yearId,
                    'school_class_id' => $class['id'],
                    'subject_id' => $assignment['subject_id'],
                    'teacher_id' => $assignment['teacher_id'],
                    'day_of_week' => $day,
                    'lesson_number' => $lesson,
                    'room' => (string) random_int(101, 320),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertAndRemember('lessons', $rows, 'lessons');
    }

    /**
     * Inserează în bucăți și reține id-urile în manifest. Fără `insertGetId` per rând: la ~10.000
     * de note ar însemna 10.000 de interogări; aici sunt câteva zeci.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertAndRemember(string $table, array $rows, string $manifestKey): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $firstId = (int) DB::table($table)->insertGetId($chunk[0]);
            $this->manifest[$manifestKey][] = $firstId;

            $rest = array_slice($chunk, 1);

            if ($rest === []) {
                continue;
            }

            DB::table($table)->insert($rest);

            // Id-urile inserate în bloc sunt consecutive (AUTO_INCREMENT, o singură instrucțiune):
            // le reconstituim din primul id, ca manifestul să rămână exact fără interogări în plus.
            $ids = DB::table($table)
                ->where('id', '>', $firstId)
                ->orderBy('id')
                ->limit(count($rest))
                ->pluck('id');

            foreach ($ids as $id) {
                $this->manifest[$manifestKey][] = (int) $id;
            }
        }
    }

    private function report(): void
    {
        $this->components->info('Școala demo completată.');

        $this->table(['Ce s-a creat', 'Rânduri'], [
            ['Clase noi (I, anul nou)', (string) count($this->manifest['classes'])],
            ['Boboci înscriși', (string) count($this->manifest['students'])],
            ['Profesori (catedră demo)', (string) count($this->manifest['teachers'])],
            ['Alocări de discipline', (string) count($this->manifest['assignments'])],
            ['Note', (string) count($this->manifest['grades'])],
            ['Absențe', (string) count($this->manifest['absences'])],
            ['Teme', (string) count($this->manifest['homework'])],
            ['Ore de orar', (string) count($this->manifest['lessons'])],
            ['Clase promovate re-marcate [DEMO]', (string) count($this->manifest['adopted_classes'])],
            ['Alocări imposibile curățate', (string) $this->pruned],
        ]);

        $this->line('  <fg=gray>Manifest: storage/app/'.self::MANIFEST.'</>');
        $this->line('  <fg=gray>Curățare: php artisan app:seed-demo-curriculum --remove</>');
    }

    private function remove(): int
    {
        $path = storage_path('app/'.self::MANIFEST);

        if (! File::exists($path)) {
            $this->components->warn('Fără manifest ('.self::MANIFEST.') — nimic de șters.');

            return self::SUCCESS;
        }

        /** @var array<string, mixed> $m */
        $m = json_decode((string) File::get($path), true) ?: [];

        DB::transaction(function () use ($m): void {
            // Ordine FK-safe: întâi rândurile care REFERĂ, apoi cele referite.
            $this->deleteIds('grades', $m['grades'] ?? []);
            $this->deleteIds('absences', $m['absences'] ?? []);
            $this->deleteIds('homework_assignments', $m['homework'] ?? []);
            $this->deleteIds('lessons', $m['lessons'] ?? []);
            $this->deleteIds('teaching_assignments', $m['assignments'] ?? []);

            // Clasele I create aici: întâi înmatriculările + activitatea elevilor lor.
            $students = array_map(intval(...), $m['students'] ?? []);

            if ($students !== []) {
                DB::table('grades')->whereIn('student_id', $students)->delete();
                DB::table('absences')->whereIn('student_id', $students)->delete();
                DB::table('enrollments')->whereIn('student_id', $students)->delete();
            }

            $this->deleteIds('school_classes', $m['classes'] ?? []);
            $this->deleteIds('students', $students);
            $this->deleteIds('teachers', $m['teachers'] ?? []);

            // Clasele promovate doar ADOPTATE (nu create aici): li se redă numele original.
            // Ștergerea lor e treaba curățării zonei demo — ele conțin elevi din zona veche.
            foreach ($m['adopted_classes'] ?? [] as $row) {
                DB::table('school_classes')->where('id', $row['id'])->update(['name' => $row['name']]);
            }
        });

        File::delete($path);

        $this->components->info('Datele demo de curriculum au fost șterse; numele claselor promovate au fost restaurate.');

        return self::SUCCESS;
    }

    /** @param  array<int, int|string>  $ids */
    private function deleteIds(string $table, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        foreach (array_chunk(array_map(intval(...), $ids), 1000) as $chunk) {
            DB::table($table)->whereIn('id', $chunk)->delete();
        }
    }
}
