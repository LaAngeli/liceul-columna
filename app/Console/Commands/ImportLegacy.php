<?php

namespace App\Console\Commands;

use App\Actions\ImportLegacyUsers;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ImportLegacy extends Command
{
    protected $signature = 'app:import-legacy
        {--fresh : Rulează migrate:fresh --seed înainte de import}
        {--with-users : Creează și conturile de login (bdn_users) la final — cutover complet}
        {--year=2025–2026 : Denumirea anului școlar creat}';

    protected $description = 'Importă datele din baza veche (conexiunea legacy) în schema nouă.';

    /** @var array<int, int> */
    private array $subjectMap = [];

    /** @var array<int, int> */
    private array $teacherMap = [];

    /** @var array<int, int> */
    private array $studentMap = [];

    /** @var array<int, int> legacy clase.id => school_class_id */
    private array $classMap = [];

    /** @var array<int, int> student_id => school_class_id */
    private array $classByStudent = [];

    /** @var array<int, int> sem => term_id */
    private array $termMap = [];

    /** @var array<int, array{0: string, 1: string}> term_id => [starts_on, ends_on] — fereastra de normalizare a datelor */
    private array $termRange = [];

    private string $yearStart = '2025-09-01';

    private string $yearEnd = '2026-06-30';

    private int $yearId = 0;

    public function handle(): int
    {
        $legacy = DB::connection('legacy');

        if ($this->option('fresh')) {
            $this->warn('migrate:fresh --seed …');
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $this->info('Bază resetată.');
        }

        $now = Carbon::now();

        $this->info('1/9 An școlar + semestre…');
        $this->yearId = DB::table('academic_years')->insertGetId([
            'name' => (string) $this->option('year'),
            'is_current' => true,
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // Interval per semestru — necesar pentru derivarea semestrului din data unei note/absențe.
        $termDates = [1 => ['2025-09-01', '2025-12-31'], 2 => ['2026-01-01', '2026-06-30']];
        foreach ([1 => 'Semestrul I', 2 => 'Semestrul II'] as $num => $name) {
            $this->termMap[$num] = DB::table('terms')->insertGetId([
                'academic_year_id' => $this->yearId,
                'number' => $num,
                'name' => $name,
                'starts_on' => $termDates[$num][0],
                'ends_on' => $termDates[$num][1],
                'is_current' => $num === 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Fereastra pe term_id, pentru normalizarea datelor corupte din sursă (vezi clampDate).
            $this->termRange[$this->termMap[$num]] = $termDates[$num];
        }

        $this->info('2/9 Discipline…');
        foreach ($legacy->table('bdn_disc')->get() as $d) {
            $this->subjectMap[(int) $d->id] = DB::table('subjects')->insertGetId([
                'name' => $d->n_d,
                'abbreviation' => $d->abr ?: null,
                'min_grade' => (int) $d->de_la ?: null,
                'max_grade' => (int) $d->pana_la ?: null,
                'grading_type' => in_array($d->notare, ['n', 'c', 'cd', 'd'], true) ? $d->notare : 'n',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info('3/9 Profesori…');
        foreach ($legacy->table('bdn_profi')->get() as $p) {
            $this->teacherMap[(int) $p->id_pr] = DB::table('teachers')->insertGetId([
                'last_name' => $p->name_1 ?: null,
                'first_name' => $p->name_2 ?: null,
                'sex' => in_array($p->sex, ['f', 'm'], true) ? $p->sex : null,
                'email' => $p->email_prof ?: null,
                'position' => match ((string) $p->func) {
                    '3' => 'Diriginte',
                    default => 'Profesor',
                },
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info('4/9 Clase…');
        foreach ($legacy->table('clase')->get() as $c) {
            $this->classMap[(int) $c->id] = DB::table('school_classes')->insertGetId([
                'academic_year_id' => $this->yearId,
                'grade_level' => (int) $c->cl_rang,
                'name' => $c->den_cl,
                'section' => $c->prim_id ?: null,
                'homeroom_teacher_id' => $this->teacherMap[(int) $c->id_dir] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info('5/9 Elevi + înmatriculări…');
        foreach ($legacy->table('bdn_elevi')->get() as $e) {
            $studentId = DB::table('students')->insertGetId([
                'last_name' => $e->name_1 ?: null,
                'first_name' => $e->name_2 ?: null,
                'sex' => in_array($e->sex, ['f', 'm'], true) ? $e->sex : null,
                'register_number' => $e->id_reg ? (string) $e->id_reg : null,
                'english_group' => in_array((int) $e->engl_gr, [1, 2, 3], true) ? (int) $e->engl_gr : null,
                'second_language' => in_array($e->str_2, ['fr', 'gm', 'nu'], true) ? $e->str_2 : 'nu',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->studentMap[(int) $e->id_el] = $studentId;

            // Înmatriculare: găsește clasa după treaptă + literă.
            $classId = DB::table('school_classes')
                ->where('academic_year_id', $this->yearId)
                ->where('grade_level', (int) $e->class_rang)
                ->where('section', $e->prim_id)
                ->value('id');

            if ($classId) {
                $this->classByStudent[$studentId] = (int) $classId;
                DB::table('enrollments')->insert([
                    'student_id' => $studentId,
                    'school_class_id' => $classId,
                    'academic_year_id' => $this->yearId,
                    // Sursa legacy nu are data înmatriculării → coloana „Înmatriculat la" apărea goală
                    // pe toate rândurile. Implicit = începutul anului școlar (înmatriculare la start).
                    'enrolled_on' => $this->yearStart,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->info('6/9 Repartizări profesor-clasă…');
        $assignments = [];
        foreach ($legacy->table('bdn_prof_cl')->get() as $a) {
            $teacherId = $this->teacherMap[(int) $a->id_pr] ?? null;
            $subjectId = $this->subjectMap[(int) $a->id_dsc] ?? null;
            $classId = $this->classMap[(int) $a->id_cl] ?? null;
            if ($teacherId && $subjectId && $classId) {
                $assignments[] = [
                    'teacher_id' => $teacherId,
                    'subject_id' => $subjectId,
                    'school_class_id' => $classId,
                    'english_group' => in_array((int) $a->engl_gr, [1, 2, 3], true) ? (int) $a->engl_gr : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->bulk('teaching_assignments', $assignments);

        $this->info('7/9 Note + absențe…');
        $grades = [];
        $absences = [];
        $skipped = 0;
        foreach ($legacy->table('bdn_note')->orderBy('id')->cursor() as $n) {
            $studentId = $this->studentMap[(int) $n->id_el] ?? null;
            $subjectId = $this->subjectMap[(int) $n->id_d] ?? null;
            $classId = $studentId ? ($this->classByStudent[$studentId] ?? null) : null;
            $termId = $this->termMap[(int) $n->sem] ?? $this->termMap[1];

            if (! $studentId || ! $subjectId || ! $classId) {
                $skipped++;

                continue;
            }

            $abs = trim((string) $n->abs);
            if ($abs !== '' && $abs !== '0') {
                $absences[] = [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'school_class_id' => $classId,
                    'term_id' => $termId,
                    'teacher_id' => null,
                    'occurred_on' => $this->clampDate($n->date, ...$this->termRange[$termId]),
                    'is_motivated' => $abs === 'p',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } elseif ((float) $n->nota > 0) {
                $grades[] = $this->gradeRow($studentId, $subjectId, $classId, $termId, $n, (float) $n->nota, null, $now);
            } elseif (trim((string) $n->calif) !== '') {
                $grades[] = $this->gradeRow($studentId, $subjectId, $classId, $termId, $n, null, trim((string) $n->calif), $now);
            } else {
                $skipped++;

                continue;
            }

            if (count($grades) >= 2000) {
                $this->bulk('grades', $grades);
                $grades = [];
            }
            if (count($absences) >= 2000) {
                $this->bulk('absences', $absences);
                $absences = [];
            }
        }
        $this->bulk('grades', $grades);
        $this->bulk('absences', $absences);

        $this->info('8/11 Foaie matricolă (medii istorice)…');
        $dosarSkipped = $this->importAcademicRecords($legacy, $now);

        $this->info('9/11 Teme academice…');
        $this->importHomework($legacy, $now);

        $this->info('10/11 Gata inserările.');
        $this->info('11/11 Rezumat:');
        $this->table(['Entitate', 'Rânduri'], [
            ['Discipline', count($this->subjectMap)],
            ['Profesori', count($this->teacherMap)],
            ['Clase', count($this->classMap)],
            ['Elevi', count($this->studentMap)],
            ['Înmatriculări', DB::table('enrollments')->count()],
            ['Repartizări', DB::table('teaching_assignments')->count()],
            ['Note', DB::table('grades')->count()],
            ['Absențe', DB::table('absences')->count()],
            ['Foaie matricolă', DB::table('academic_records')->count()],
            ['Teme', DB::table('homework_assignments')->count()],
            ['Rânduri sărite (note)', $skipped],
            ['Rânduri sărite (matricolă)', $dosarSkipped],
        ]);

        if ($this->option('with-users')) {
            $this->info('Conturi de login (bdn_users → users)…');
            $userStats = app(ImportLegacyUsers::class)->execute();
            $this->table(['Conturi', 'Valoare'], ImportLegacyUsers::summaryRows($userStats));
            $this->warn('Conturi migrate cu `must_change_password=true` — schimbă parola la prima logare.');
        }

        return self::SUCCESS;
    }

    /**
     * Foaie matricolă: bdn_dosar → academic_records (media pe treaptă + perioadă).
     * Disciplinele dosarului folosesc aceleași id-uri ca lista curentă (bdn_disc).
     */
    private function importAcademicRecords(Connection $legacy, Carbon $now): int
    {
        $rows = [];
        $skipped = 0;

        foreach ($legacy->table('bdn_dosar')->orderBy('id')->cursor() as $d) {
            $studentId = $this->studentMap[(int) $d->id_el] ?? null;
            $subjectId = $this->subjectMap[(int) $d->id_d] ?? null;
            $period = (int) $d->sem;

            if (! $studentId || ! $subjectId || ! in_array($period, [1, 2, 3], true)) {
                $skipped++;

                continue;
            }

            $nota = (float) $d->nota;
            $calif = trim((string) $d->calif);

            if ($nota > 0) {
                $value = round($nota, 2);
                $calif = null;
            } elseif ($calif !== '') {
                $value = null;
                $calif = mb_substr($calif, 0, 10);
            } else {
                $skipped++;

                continue;
            }

            $rows[] = [
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'grade_level' => (int) $d->cl,
                'period' => $period,
                'value' => $value,
                'calificativ' => $calif,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 2000) {
                $this->bulk('academic_records', $rows);
                $rows = [];
            }
        }

        $this->bulk('academic_records', $rows);

        return $skipped;
    }

    /**
     * Teme academice: bdn_teme_ac → homework_assignments. Autorul rămâne text
     * (legacy nu îl leagă de o fișă de profesor); id_disc se mapează la disciplină.
     */
    private function importHomework(Connection $legacy, Carbon $now): void
    {
        $rows = [];

        foreach ($legacy->table('bdn_teme_ac')->orderBy('id')->cursor() as $t) {
            $links = array_values(array_filter([
                trim((string) $t->link1),
                trim((string) $t->link2),
                trim((string) $t->link3),
            ], static fn (string $l): bool => $l !== ''));

            $subjectName = trim((string) $t->name_discipl);

            $rows[] = [
                'subject_id' => $this->subjectMap[(int) $t->id_disc] ?? null,
                'teacher_id' => null,
                'subject_name' => $subjectName !== '' ? $subjectName : '—',
                'author_name' => trim((string) $t->autor) ?: null,
                'grade_level' => (int) $t->class_rang,
                'section' => trim((string) $t->prim_cl) ?: null,
                'assigned_on' => $this->clampDate($t->date_dat, $this->yearStart, $this->yearEnd),
                'topic' => trim((string) $t->subiect) ?: null,
                'required_task' => trim((string) $t->s_o) ?: null,
                'optional_task' => trim((string) $t->s_s) ?: null,
                'links' => $links !== [] ? json_encode($links) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 2000) {
                $this->bulk('homework_assignments', $rows);
                $rows = [];
            }
        }

        $this->bulk('homework_assignments', $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeRow(int $studentId, int $subjectId, int $classId, int $termId, \stdClass $n, ?float $value, ?string $calif, Carbon $now): array
    {
        return [
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'school_class_id' => $classId,
            'term_id' => $termId,
            'teacher_id' => null,
            'graded_on' => $this->clampDate($n->date, ...($this->termRange[$termId] ?? [$this->yearStart, $this->yearEnd])),
            'type' => (int) $n->st_n ?: null,
            'value' => $value !== null ? round($value, 2) : null,
            'calificativ' => $calif ? mb_substr($calif, 0, 10) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function bulk(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Normalizează o dată-sursă legacy într-o fereastră plauzibilă [start, end]. Sursa veche conține
     * date corupte (an 2205 la note/absențe, an 0026 la teme — typo-uri de operator în vechiul sistem)
     * pe care importul le copia verbatim, iar catalogul le afișa (ex. „16.09.2205" domina capul listei
     * de note, sortată descrescător). Regula: dacă data cade în fereastră, o păstrăm; dacă nu, corectăm
     * ANUL la cel al ferestrei (păstrând ziua/luna — 2205-09-16 → 2025-09-16); altfel clamp la marginea
     * cea mai apropiată. Data goală/neparsabilă → începutul ferestrei.
     */
    private function clampDate(mixed $raw, string $start, string $end): string
    {
        $startC = Carbon::parse($start)->startOfDay();
        $endC = Carbon::parse($end)->startOfDay();

        try {
            $date = ($raw === null || $raw === '') ? null : Carbon::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            $date = null;
        }

        if ($date === null) {
            return $startC->toDateString();
        }

        if ($date->gte($startC) && $date->lte($endC)) {
            return $date->toDateString();
        }

        foreach (array_unique([$startC->year, $endC->year]) as $year) {
            $candidate = $date->copy()->setDate($year, (int) $date->month, (int) $date->day);
            if ($candidate->gte($startC) && $candidate->lte($endC)) {
                return $candidate->toDateString();
            }
        }

        return $date->lt($startC) ? $startC->toDateString() : $endC->toDateString();
    }
}
