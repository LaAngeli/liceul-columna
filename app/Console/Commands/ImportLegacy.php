<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ImportLegacy extends Command
{
    protected $signature = 'app:import-legacy
        {--fresh : Rulează migrate:fresh --seed înainte de import}
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
        foreach ([1 => 'Semestrul I', 2 => 'Semestrul II'] as $num => $name) {
            $this->termMap[$num] = DB::table('terms')->insertGetId([
                'academic_year_id' => $this->yearId,
                'number' => $num,
                'name' => $name,
                'is_current' => $num === 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
                    'occurred_on' => $n->date,
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

        $this->info('8/9 Gata inserările.');
        $this->info('9/9 Rezumat:');
        $this->table(['Entitate', 'Rânduri'], [
            ['Discipline', count($this->subjectMap)],
            ['Profesori', count($this->teacherMap)],
            ['Clase', count($this->classMap)],
            ['Elevi', count($this->studentMap)],
            ['Înmatriculări', DB::table('enrollments')->count()],
            ['Repartizări', DB::table('teaching_assignments')->count()],
            ['Note', DB::table('grades')->count()],
            ['Absențe', DB::table('absences')->count()],
            ['Rânduri sărite (note)', $skipped],
        ]);

        return self::SUCCESS;
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
            'graded_on' => $n->date,
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
}
