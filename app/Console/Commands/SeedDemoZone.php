<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Creează (sau șterge cu --remove) o ZONĂ DE TEST IZOLATĂ: o „școală demo" paralelă cu tot atâtea
 * clase câte are școala reală, populată cu elevi/profesori/note/absențe/corecții demo, ca testerii
 * să lucreze EXCLUSIV pe ea — fără să atingă datele elevilor reali.
 *
 * IZOLARE: tot ce se creează e fie prefixat „[DEMO]" (clase, elevi, profesori), fie legat de entități
 * demo (note/absențe pe elevi demo) → curățarea = „șterge tot ce ține de zona demo", 100% reversibil.
 *
 * CONTURILE DEMO se re-leagă de zona demo (profesor@/diriginte@ → profesori demo; elev@/elev2@ →
 * elevi demo; parinte@ → părinte al lor), ca testerii să aterizeze direct în zonă. Legăturile REALE
 * originale se salvează într-un MANIFEST (storage/app/demo/zone.json) și se restaurează la --remove.
 *
 * Scrie prin query builder → fără observers/audit/notificări (fără efecte secundare la seed). Acțiunile
 * ULTERIOARE ale testerilor prin interfață RĂMÂN auditate (vezi app:demo-audit).
 */
class SeedDemoZone extends Command
{
    protected $signature = 'app:seed-demo-zone
        {--students=8 : Elevi demo per clasă}
        {--remove : Șterge zona demo și restaurează legăturile reale ale conturilor}';

    protected $description = 'Creează/șterge o zonă de test izolată (școală demo) pentru testeri';

    private const MARK = '[DEMO]';

    /** Distribuția claselor pe trepte — sumă 27, ca realitatea. */
    private const DISTRIBUTION = [1 => 2, 2 => 2, 3 => 2, 4 => 3, 5 => 2, 6 => 2, 7 => 2, 8 => 2, 9 => 3, 10 => 3, 11 => 2, 12 => 2];

    /** Discipline folosite la alocări (id-uri din tabelul `subjects`). */
    private const SUBJECT_IDS = [1, 5, 8, 2, 11, 9, 13];

    /**
     * Nume pentru elevi/profesori demo. Interne DELIBERAT — `fake()` (fakerphp/faker) e în require-dev
     * și LIPSEȘTE în producția `--no-dev`, deci o comandă care rulează pe prod nu se poate baza pe el.
     */
    private const LAST_NAMES = ['Popescu', 'Rusu', 'Ciobanu', 'Moraru', 'Ungureanu', 'Rotaru', 'Munteanu', 'Cojocaru', 'Bejan', 'Lungu', 'Cebotari', 'Postolache', 'Sandu', 'Vieru', 'Cazacu', 'Bivol', 'Botnaru', 'Cucu', 'Frunză', 'Zaharia', 'Dragomir', 'Ionescu', 'Balan', 'Croitoru', 'Guțu', 'Melnic', 'Grosu', 'Damian', 'Racu', 'Ursu'];

    private const FIRST_NAMES = ['Andrei', 'Maria', 'Ion', 'Ana', 'Mihai', 'Elena', 'Nicolae', 'Cristina', 'Vasile', 'Daniela', 'Alexandru', 'Natalia', 'Dumitru', 'Irina', 'Sergiu', 'Victoria', 'Petru', 'Tatiana', 'Gheorghe', 'Ludmila', 'Radu', 'Diana', 'Constantin', 'Aliona', 'Valentin', 'Corina', 'Denis', 'Gabriela', 'Marius', 'Nadia'];

    /** Motive scurte pentru corecții/motivări demo. */
    private const REASONS = ['corectare eroare de calcul', 'notă introdusă greșit', 'certificat medical', 'învoire pentru concurs școlar', 'reexaminare după contestație', 'eroare de transcriere din catalog'];

    private string $manifestPath;

    public function handle(): int
    {
        $this->manifestPath = storage_path('app/demo/zone.json');

        return $this->option('remove') ? $this->remove() : $this->seed();
    }

    private function seed(): int
    {
        if (File::exists($this->manifestPath)) {
            $this->error('Zona demo există deja. Rulează întâi `php artisan app:seed-demo-zone --remove`.');

            return self::FAILURE;
        }

        $perClass = max(1, (int) $this->option('students'));
        $yearId = (int) DB::table('terms')->where('is_current', true)->value('academic_year_id');
        $termId = (int) DB::table('terms')->where('is_current', true)->value('id');
        if ($yearId === 0) {
            $yearId = (int) DB::table('academic_years')->min('id');
            $termId = (int) DB::table('terms')->min('id');
        }

        // Conturile demo pe care le re-legăm (email → user).
        $accounts = User::query()
            ->whereIn('email', ['profesor@columna.test', 'diriginte@columna.test', 'elev@columna.test', 'elev2@columna.test', 'parinte@columna.test'])
            ->pluck('id', 'email');

        $manifest = ['teachers' => [], 'classes' => [], 'students' => [], 'restore' => []];

        DB::transaction(function () use ($perClass, $yearId, $termId, $accounts, &$manifest): void {
            $now = Carbon::now();

            // ---- 1. Profesori demo ----------------------------------------------------------
            // Re-legăm profesor@ și diriginte@ de profesori demo NOI (salvăm legătura reală veche).
            $profTeacherId = $this->makeTeacher('Profesor Demonstrativ', $now, $manifest);
            $dirigTeacherId = $this->makeTeacher('Diriginte Demonstrativ', $now, $manifest);
            $this->relinkTeacherAccount((int) $accounts['profesor@columna.test'], $profTeacherId, $manifest);
            $this->relinkTeacherAccount((int) $accounts['diriginte@columna.test'], $dirigTeacherId, $manifest);

            // Fond de diriginți demo pentru restul claselor.
            $pool = [$dirigTeacherId];
            foreach (range(1, 12) as $n) {
                $pool[] = $this->makeTeacher("Diriginte {$n}", $now, $manifest);
            }

            // ---- 2. Clase demo (27) + elevi + alocări ---------------------------------------
            $classIndex = 0;
            $assignments = []; // class_id => [ [subject_id, teacher_id], ... ]
            foreach (self::DISTRIBUTION as $grade => $count) {
                foreach (range(0, $count - 1) as $s) {
                    $section = chr(65 + $s); // A, B, C
                    $homeroomTeacherId = $pool[$classIndex % count($pool)];

                    $classId = (int) DB::table('school_classes')->insertGetId([
                        'academic_year_id' => $yearId,
                        'grade_level' => $grade,
                        'name' => self::MARK." {$grade}{$section}",
                        'section' => $section,
                        'homeroom_teacher_id' => $homeroomTeacherId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $manifest['classes'][] = $classId;

                    // Elevi + înscrieri.
                    foreach (range(1, $perClass) as $ignored) {
                        $sid = $this->makeStudent($now, $manifest);
                        DB::table('enrollments')->insert([
                            'student_id' => $sid,
                            'school_class_id' => $classId,
                            'academic_year_id' => $yearId,
                            'enrolled_on' => $now->copy()->subMonths(9)->toDateString(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Alocări: 4-5 discipline. profesor@ predă în primele 6 clase; restul, profesorului diriginte.
                    $subjects = array_slice(self::SUBJECT_IDS, 0, random_int(4, 5));
                    foreach ($subjects as $i => $subjectId) {
                        $teacherId = ($classIndex < 6 && $i === 0) ? $profTeacherId : $pool[($classIndex + $i) % count($pool)];
                        DB::table('teaching_assignments')->insert([
                            'teacher_id' => $teacherId,
                            'subject_id' => $subjectId,
                            'school_class_id' => $classId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $assignments[$classId][] = [$subjectId, $teacherId];
                    }

                    $classIndex++;
                }
            }

            // ---- 3. Re-legăm conturile de familie de zona demo ------------------------------
            $firstClassId = $manifest['classes'][0];
            $elevStudentId = $this->relinkStudentAccount((int) $accounts['elev@columna.test'], $firstClassId, $yearId, $now, $manifest);
            $elev2StudentId = $this->relinkStudentAccount((int) $accounts['elev2@columna.test'], $firstClassId, $yearId, $now, $manifest);
            $this->relinkParentAccount((int) $accounts['parinte@columna.test'], [$elevStudentId, $elev2StudentId], $now, $manifest);

            // ---- 4. Activitate (note / absențe / corecții / motivări) ------------------------
            $this->seedActivity($manifest, $assignments, $termId, (int) $accounts['profesor@columna.test'], (int) $accounts['diriginte@columna.test'], (int) $accounts['parinte@columna.test']);
        });

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->report($manifest);

        return self::SUCCESS;
    }

    /**
     * Creează un profesor demo, întoarce id-ul.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function makeTeacher(string $label, Carbon $now, array &$manifest): int
    {
        $id = (int) DB::table('teachers')->insertGetId([
            'last_name' => self::MARK,
            'first_name' => $label,
            'sex' => ['m', 'f'][random_int(0, 1)],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $manifest['teachers'][] = $id;

        return $id;
    }

    /**
     * Creează un elev demo, întoarce id-ul.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function makeStudent(Carbon $now, array &$manifest): int
    {
        $id = (int) DB::table('students')->insertGetId([
            'last_name' => self::MARK.' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)],
            'first_name' => self::FIRST_NAMES[array_rand(self::FIRST_NAMES)],
            'sex' => ['m', 'f'][random_int(0, 1)],
            'register_number' => 'D'.random_int(10000, 99999),
            'second_language' => ['nu', 'gm', 'fr'][random_int(0, 2)],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $manifest['students'][] = $id;

        return $id;
    }

    /**
     * Re-leagă un cont de profesor de o fișă demo; salvează fișa reală veche pentru restaurare.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function relinkTeacherAccount(int $userId, int $demoTeacherId, array &$manifest): void
    {
        $oldTeacherId = (int) DB::table('teachers')->where('user_id', $userId)->value('id');
        if ($oldTeacherId !== 0) {
            DB::table('teachers')->where('id', $oldTeacherId)->update(['user_id' => null]);
            $manifest['restore']['teacher_user'][] = ['teacher_id' => $oldTeacherId, 'user_id' => $userId];
        }
        DB::table('teachers')->where('id', $demoTeacherId)->update(['user_id' => $userId]);
    }

    /**
     * Re-leagă un cont de elev de un elev demo nou în clasa dată; salvează fișa reală veche.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function relinkStudentAccount(int $userId, int $classId, int $yearId, Carbon $now, array &$manifest): int
    {
        $oldStudentId = (int) DB::table('students')->where('user_id', $userId)->value('id');
        if ($oldStudentId !== 0) {
            DB::table('students')->where('id', $oldStudentId)->update(['user_id' => null]);
            $manifest['restore']['student_user'][] = ['student_id' => $oldStudentId, 'user_id' => $userId];
        }
        $demoStudentId = $this->makeStudent($now, $manifest);
        DB::table('students')->where('id', $demoStudentId)->update(['user_id' => $userId]);
        DB::table('enrollments')->insert([
            'student_id' => $demoStudentId,
            'school_class_id' => $classId,
            'academic_year_id' => $yearId,
            'enrolled_on' => $now->copy()->subMonths(9)->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $demoStudentId;
    }

    /**
     * Re-leagă contul de părinte de copiii demo; salvează copiii reali pentru restaurare.
     *
     * @param  list<int>  $demoStudentIds
     * @param  array<string, mixed>  $manifest
     */
    private function relinkParentAccount(int $userId, array $demoStudentIds, Carbon $now, array &$manifest): void
    {
        $oldChildren = DB::table('guardian_student')->where('guardian_user_id', $userId)->pluck('student_id')->all();
        if ($oldChildren !== []) {
            DB::table('guardian_student')->where('guardian_user_id', $userId)->delete();
            $manifest['restore']['parent_children'] = ['user_id' => $userId, 'student_ids' => $oldChildren];
        }
        foreach ($demoStudentIds as $sid) {
            DB::table('guardian_student')->insert([
                'guardian_user_id' => $userId,
                'student_id' => $sid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Note/absențe pentru fiecare elev demo (din alocările clasei), plus corecții cerute de profesor@
     * și motivări revizuite de diriginte@ pe primele clase (unde se face testarea densă).
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<int, list<array{0: int, 1: int}>>  $assignments
     */
    private function seedActivity(array $manifest, array $assignments, int $termId, int $profUserId, int $dirigUserId, int $parentUserId): void
    {
        $studentsByClass = DB::table('enrollments')
            ->whereIn('school_class_id', $manifest['classes'])
            ->get(['school_class_id', 'student_id'])
            ->groupBy('school_class_id');

        $gradeIds = [];
        foreach ($manifest['classes'] as $idx => $classId) {
            $pairs = $assignments[$classId] ?? [];
            $students = ($studentsByClass[$classId] ?? collect())->pluck('student_id')->all();
            if ($pairs === [] || $students === []) {
                continue;
            }

            foreach ($students as $studentId) {
                foreach (range(1, random_int(3, 6)) as $ignored) {
                    [$subjectId, $teacherId] = $pairs[array_rand($pairs)];
                    $ts = Carbon::now()->subDays(random_int(1, 150));
                    $gradeIds[] = (int) DB::table('grades')->insertGetId([
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'school_class_id' => $classId,
                        'term_id' => $termId,
                        'teacher_id' => $teacherId,
                        'graded_on' => $ts->toDateString(),
                        'type' => 1,
                        'evaluation_type' => random_int(1, 12) === 1 ? 'teza' : 'curenta',
                        // ÎNTREG, nu zecimal: `random_int(50,100)/10` producea 6,5 / 7,3 — note pe care
                        // scala 1–10 nu le cunoaște (zecimalele aparțin mediilor). Cele 52.228 de
                        // note reale importate din sistemul școlii sunt TOATE întregi.
                        'value' => random_int(5, 10),
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ]);
                }
                foreach (range(1, random_int(0, 3)) as $ignored) {
                    [$subjectId, $teacherId] = $pairs[array_rand($pairs)];
                    $ts = Carbon::now()->subDays(random_int(1, 150));
                    DB::table('absences')->insert([
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'school_class_id' => $classId,
                        'term_id' => $termId,
                        'teacher_id' => $teacherId,
                        'occurred_on' => $ts->toDateString(),
                        'is_motivated' => random_int(0, 1),
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ]);
                }
            }

            // Corecții + motivări doar pe primele 6 clase (testarea densă a fluxurilor de aprobare).
            // La acest punct $gradeIds e mereu nevid (bucla de note de mai sus rulează pe elevi nevizi).
            if ($idx < 6) {
                foreach (range(1, 3) as $ignored) {
                    $old = random_int(40, 90) / 10;
                    DB::table('grade_corrections')->insert([
                        'grade_id' => $gradeIds[array_rand($gradeIds)],
                        'requested_by_user_id' => $profUserId,
                        'old_value' => number_format($old, 2, '.', ''),
                        'new_value' => number_format(min(10, $old + 1), 2, '.', ''),
                        'reason' => self::MARK.' '.self::REASONS[array_rand(self::REASONS)],
                        'status' => 'pending',
                        'created_at' => Carbon::now()->subDays(random_int(1, 30)),
                        'updated_at' => Carbon::now(),
                    ]);
                }
                foreach (($studentsByClass[$classId] ?? collect())->take(2) as $row) {
                    DB::table('absence_motivations')->insert([
                        'student_id' => $row->student_id,
                        'requested_by_user_id' => $parentUserId,
                        'reason' => self::MARK.' '.self::REASONS[array_rand(self::REASONS)],
                        'period_start' => Carbon::now()->subDays(10)->toDateString(),
                        'period_end' => Carbon::now()->subDays(7)->toDateString(),
                        'status' => 'pending',
                        'is_exception' => false,
                        'created_at' => Carbon::now()->subDays(random_int(1, 6)),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }
    }

    private function remove(): int
    {
        if (! File::exists($this->manifestPath)) {
            $this->error('Fără manifest (storage/app/demo/zone.json) — nu pot identifica zona demo.');

            return self::FAILURE;
        }

        /** @var array{teachers?: list<int>, classes?: list<int>, students?: list<int>, restore?: array<string, mixed>} $m */
        $m = json_decode((string) File::get($this->manifestPath), true);
        $students = $m['students'] ?? [];
        $classes = $m['classes'] ?? [];
        $teachers = $m['teachers'] ?? [];
        $restore = $m['restore'] ?? [];

        DB::transaction(function () use ($students, $classes, $teachers, $restore): void {
            // 1. Activitatea (ordine FK: corecțiile referă note).
            $gradeIds = DB::table('grades')->whereIn('student_id', $students)->pluck('id');
            DB::table('grade_corrections')->whereIn('grade_id', $gradeIds)->delete();
            DB::table('absence_motivations')->whereIn('student_id', $students)->delete();
            DB::table('absences')->whereIn('student_id', $students)->delete();
            DB::table('grades')->whereIn('student_id', $students)->delete();

            // 2. Legături + structură.
            DB::table('guardian_student')->whereIn('student_id', $students)->delete();
            DB::table('enrollments')->whereIn('student_id', $students)->delete();
            DB::table('teaching_assignments')->whereIn('school_class_id', $classes)->delete();
            DB::table('school_classes')->whereIn('id', $classes)->delete();
            DB::table('students')->whereIn('id', $students)->delete();
            DB::table('teachers')->whereIn('id', $teachers)->delete();

            // 3. Restaurarea legăturilor REALE ale conturilor (după ce fișele demo au dispărut).
            foreach ($restore['teacher_user'] ?? [] as $r) {
                DB::table('teachers')->where('id', $r['teacher_id'])->update(['user_id' => $r['user_id']]);
            }
            foreach ($restore['student_user'] ?? [] as $r) {
                DB::table('students')->where('id', $r['student_id'])->update(['user_id' => $r['user_id']]);
            }
            if (isset($restore['parent_children'])) {
                $now = Carbon::now();
                foreach ($restore['parent_children']['student_ids'] as $sid) {
                    DB::table('guardian_student')->insert([
                        'guardian_user_id' => $restore['parent_children']['user_id'],
                        'student_id' => $sid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        File::delete($this->manifestPath);

        $this->info('Zonă demo ștearsă: '.count($classes).' clase, '.count($students).' elevi, '.count($teachers).' profesori. Legăturile reale ale conturilor au fost restaurate.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function report(array $manifest): void
    {
        $classes = DB::table('school_classes')
            ->whereIn('id', $manifest['classes'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get(['id', 'name', 'grade_level']);

        $this->newLine();
        $this->info('✅ Zonă de test izolată creată: '.count($manifest['classes']).' clase, '.count($manifest['students']).' elevi, '.count($manifest['teachers']).' profesori.');
        $this->newLine();

        $this->line('CLASE DEMO (spune testerilor: lucrați DOAR pe clase care încep cu [DEMO]):');
        $this->line('  '.$classes->pluck('name')->implode(' · '));
        $this->newLine();

        $firstClassId = $manifest['classes'][0];
        $firstClassName = (string) DB::table('school_classes')->where('id', $firstClassId)->value('name');

        $this->line("CONTURILE DEMO — unde aterizează (clasa de testare densă: {$firstClassName}):");
        $this->table(
            ['Cont', 'Rol în zona demo'],
            [
                ['profesor@columna.test', 'predă în primele 6 clase demo (note, corecții)'],
                ['diriginte@columna.test', "diriginte la {$firstClassName}"],
                ['elev@columna.test', 'elev demo în '.$firstClassName],
                ['elev2@columna.test', 'elev demo în '.$firstClassName],
                ['parinte@columna.test', 'părinte al celor 2 elevi demo de mai sus'],
            ],
        );

        $sampleStudents = DB::table('enrollments')
            ->where('enrollments.school_class_id', $firstClassId)
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->limit(10)
            ->get(['students.last_name', 'students.first_name']);

        $this->newLine();
        $this->line("ELEVI DEMO din {$firstClassName} (exemple pentru testeri):");
        foreach ($sampleStudents as $row) {
            $this->line("  • {$row->last_name} {$row->first_name}");
        }
        $this->newLine();
        $this->line('Curățare (restaurează și legăturile reale ale conturilor): php artisan app:seed-demo-zone --remove');
    }
}
