<?php

namespace App\Console\Commands;

use App\Enums\EvaluationType;
use App\Enums\GradingType;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * AXA DE TIMP a școlii demo (cerința beneficiarului, 04.08.2026): activitate și în TRECUT, și în
 * PREZENT, ca testele să prindă ce se vede doar când există istoric.
 *
 * Golul găsit înainte de a scrie: conturile demo aveau note DOAR în semestrul curent. Semestrul I
 * era complet gol, deci tot ce se sprijină pe istoric arăta neutru sau fals — evoluția din cabinet
 * n-avea de unde compara, media anuală n-avea prima jumătate, iar „semestrul precedent" din
 * sinteze era mereu zero. Un catalog fără trecut nu se poate testa.
 *
 * Ce adaugă, pe TOATE clasele demo ale anului curent (deci pe orice cont demo ai intra):
 *  • TRECUT — semestrul I întreg: note pe fiecare disciplină a clasei, absențe DECISE (un semestru
 *    încheiat n-are cum să aibă absențe „fără statut": termenul lor a expirat demult, iar
 *    consolidarea le-ar fi închis), teme împrăștiate pe semestru;
 *  • PREZENT — ultimele trei săptămâni ale semestrului curent: note proaspete, teme recente și
 *    absențe printre care unele FĂRĂ STATUT, ca dirigintele să aibă mereu coadă de triaj.
 *
 * Scrie prin query builder (fără observers/audit/notificări), ca toate seederele demo; mediile se
 * recalculează la final cu `app:compute-averages`, deci semestrul I capătă și medii, nu doar note.
 *
 * CURĂȚARE: marcaj `[DEMO]` moștenit de la clase/elevi + manifest propriu
 * (`storage/app/demo/timeline.json`) cu id-urile fiecărui rând; `--remove` le șterge exact.
 */
class SeedDemoTimeline extends Command
{
    protected $signature = 'app:seed-demo-timeline
        {--remove : Șterge activitatea creată de această comandă (folosind manifestul)}';

    protected $description = 'Adaugă activitate demo în TRECUT (semestrul I) și în PREZENT (ultimele săptămâni): note, absențe, teme';

    private const MARK = '[DEMO]';

    private const MANIFEST = 'demo/timeline.json';

    /** Câte zile înapoi de la finalul semestrului curent înseamnă „prezent". */
    private const RECENT_DAYS = 21;

    /** Subiecte de temă — scurte, plauzibile, marcate ca demo. */
    private const TOPICS = ['recapitulare', 'exerciții de consolidare', 'lucru individual', 'pregătire pentru evaluare', 'lectură și analiză', 'aplicații practice', 'proiect de grup', 'fișă de lucru'];

    /** @var array<int, string> id profesor → numele complet (sursa pentru `author_name`) */
    private array $teacherNames = [];

    /**
     * Numele profesorului, pentru `author_name`.
     *
     * Snapshot-ul TEXTUAL nu e redundanță: FK-ul `homework_assignments.teacher_id` e
     * `ON DELETE SET NULL`, deci când fișa profesorului dispare (reconstruirea zonei demo, plecarea
     * unui cadru didactic) legătura se pierde. Fără nume salvat, tema rămâne FĂRĂ NICIUN autor —
     * exact starea găsită pe local la 2026-08-07: 932 de teme orfane, afișate în cabinet fără
     * profesor. Modelul o spune de mult („rămâne valabil și dacă fișa profesorului dispare");
     * seeder-ele erau cele care nu scriau câmpul.
     */
    private function teacherName(int $teacherId): string
    {
        if (! array_key_exists($teacherId, $this->teacherNames)) {
            $row = DB::table('teachers')->where('id', $teacherId)->first(['last_name', 'first_name']);

            $this->teacherNames[$teacherId] = $row === null
                ? '—'
                : trim(((string) $row->last_name).' '.((string) $row->first_name));
        }

        return $this->teacherNames[$teacherId];
    }

    /** @var array<string, list<int>> */
    private array $manifest = ['grades' => [], 'absences' => [], 'homework' => []];

    /** @var list<int>|null elevii conturilor demo, rezolvați o singură dată */
    private ?array $accountStudents = null;

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        if (File::exists(storage_path('app/'.self::MANIFEST))) {
            $this->components->error('Există deja un manifest ('.self::MANIFEST.'). Rulează întâi `--remove`.');

            return self::FAILURE;
        }

        $current = SchoolCalendar::currentTerm();

        if ($current === null) {
            $this->components->error('Nu există un semestru curent — axa de timp nu poate fi stabilită.');

            return self::FAILURE;
        }

        // Semestrul PRECEDENT din același an: „trecutul" pe care îl caută testele (evoluție,
        // medie anuală, comparație între semestre). Fără el, comanda tot completează prezentul.
        $previous = Term::query()
            ->where('academic_year_id', $current->academic_year_id)
            ->whereKeyNot($current->getKey())
            ->where('starts_on', '<', $current->starts_on)
            ->orderByDesc('starts_on')
            ->first();

        if ($previous === null) {
            $this->components->warn('Anul curent n-are semestru precedent — se completează doar prezentul.');
        }

        /** @var list<array{id: int, grade_level: int, section: string|null}> $classes */
        $classes = DB::table('school_classes')
            ->where('academic_year_id', $current->academic_year_id)
            ->where('name', 'like', self::MARK.'%')
            ->whereNull('deleted_at')
            ->get(['id', 'grade_level', 'section'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'grade_level' => (int) $row->grade_level,
                'section' => $row->section === null ? null : (string) $row->section,
            ])
            ->all();

        if ($classes === []) {
            $this->components->error('Nicio clasă demo în anul curent. Rulează întâi `app:seed-demo-zone` și `app:seed-demo-curriculum`.');

            return self::FAILURE;
        }

        $counts = ['past' => 0, 'present' => 0, 'absences' => 0, 'homework' => 0];

        DB::transaction(function () use ($classes, $current, $previous, &$counts): void {
            $now = Carbon::now();

            foreach ($classes as $class) {
                $assignments = $this->assignmentsOf($class['id']);
                $students = $this->studentsOf($class['id']);

                if ($assignments === [] || $students === []) {
                    continue;
                }

                if ($previous instanceof Term) {
                    $counts['past'] += $this->seedTerm($class, $assignments, $students, $previous, $now, past: true);
                    $counts['absences'] += $this->seedAbsences($class, $assignments, $students, $previous, $now, past: true);
                    $counts['homework'] += $this->seedHomework($class, $assignments, $previous, $now, past: true);
                }

                $counts['present'] += $this->seedTerm($class, $assignments, $students, $current, $now, past: false);
                $counts['absences'] += $this->seedAbsences($class, $assignments, $students, $current, $now, past: false);
                $counts['homework'] += $this->seedHomework($class, $assignments, $current, $now, past: false);

                // Elevii legați de CONTURI demo nu pot rămâne la mila zarului: pe ei se intră la
                // testare, deci li se garantează absențe în ambele semestre (cerința beneficiarului).
                $focus = array_values(array_intersect($students, $this->accountStudentIds()));

                if ($focus !== []) {
                    if ($previous instanceof Term) {
                        $counts['absences'] += $this->seedAbsences($class, $assignments, $focus, $previous, $now, past: true, guaranteed: true);
                    }

                    $counts['absences'] += $this->seedAbsences($class, $assignments, $focus, $current, $now, past: false, guaranteed: true);
                }
            }
        });

        File::ensureDirectoryExists(storage_path('app/demo'));
        File::put(
            storage_path('app/'.self::MANIFEST),
            (string) json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        $this->components->info('Axa de timp a școlii demo a fost populată.');

        $this->table(['Ce s-a adăugat', 'Rânduri'], [
            ['Note în semestrul precedent (trecut)', (string) $counts['past']],
            ['Note în ultimele '.self::RECENT_DAYS.' zile (prezent)', (string) $counts['present']],
            ['Absențe (ambele semestre)', (string) $counts['absences']],
            ['Teme (ambele semestre)', (string) $counts['homework']],
        ]);

        $this->line('  <fg=gray>Manifest: storage/app/'.self::MANIFEST.'</>');
        $this->line('  <fg=gray>Curățare: php artisan app:seed-demo-timeline --remove</>');

        $this->components->info('Recalculez mediile semestriale…');
        $this->call('app:compute-averages');

        return self::SUCCESS;
    }

    /**
     * Alocările clasei — sursa disciplinelor și a autorilor. Fără ele nu se scrie nimic: o notă la
     * o disciplină nepredată e exact ce curăța `app:seed-demo-curriculum`.
     *
     * @return list<array{subject_id: int, teacher_id: int, grading_type: string}>
     */
    private function assignmentsOf(int $classId): array
    {
        /** @var list<array{subject_id: int, teacher_id: int, grading_type: string}> $rows */
        $rows = DB::table('teaching_assignments')
            ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->where('teaching_assignments.school_class_id', $classId)
            ->whereNull('teaching_assignments.deleted_at')
            ->get(['teaching_assignments.subject_id', 'teaching_assignments.teacher_id', 'subjects.grading_type'])
            ->map(fn (object $row): array => [
                'subject_id' => (int) $row->subject_id,
                'teacher_id' => (int) $row->teacher_id,
                'grading_type' => (string) $row->grading_type,
            ])
            ->values()
            ->all();

        return $rows;
    }

    /**
     * Elevii legați de CONTURI demo (`[DEMO] …`) — cei pe care se intră efectiv la testare.
     * Memoizat: se cere o dată per clasă, dar răspunsul e același pentru toată rularea.
     *
     * @return list<int>
     */
    private function accountStudentIds(): array
    {
        if ($this->accountStudents !== null) {
            return $this->accountStudents;
        }

        /** @var list<int> $ids */
        $ids = DB::table('students')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('users.name', 'like', self::MARK.'%')
            ->whereNull('students.deleted_at')
            ->pluck('students.id')
            ->map(intval(...))
            ->values()
            ->all();

        return $this->accountStudents = $ids;
    }

    /** @return list<int> */
    private function studentsOf(int $classId): array
    {
        /** @var list<int> $ids */
        $ids = DB::table('enrollments')
            ->where('school_class_id', $classId)
            ->whereNull('left_on')
            ->whereNull('deleted_at')
            ->pluck('student_id')
            ->map(intval(...))
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Note într-un semestru. În TRECUT se acoperă tot intervalul (semestrul s-a consumat); în
     * PREZENT doar ultimele săptămâni — restul semestrului e deja populat de
     * `app:seed-demo-curriculum`, iar scopul aici e activitatea RECENTĂ.
     *
     * @param  array{id: int, grade_level: int, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     * @param  list<int>  $students
     */
    private function seedTerm(array $class, array $assignments, array $students, Term $term, Carbon $now, bool $past): int
    {
        [$from, $to] = $this->window($term, $past);

        if ($from->greaterThan($to)) {
            return 0;
        }

        $span = max(1, (int) $from->diffInDays($to));

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($assignments as $assignment) {
            $isSummative = $past && DB::table('summative_designations')
                ->where('school_class_id', $class['id'])
                ->where('subject_id', $assignment['subject_id'])
                ->exists();

            $numeric = $assignment['grading_type'] === GradingType::Numeric->value;

            foreach ($students as $studentId) {
                // Trecutul e un semestru întreg (3–6 note); prezentul, o fereastră scurtă (1–3).
                foreach (range(1, $past ? random_int(3, 6) : random_int(1, 3)) as $ignored) {
                    $rows[] = $this->gradeRow(
                        $class, $assignment, $studentId, (int) $term->id,
                        $from->copy()->addDays(random_int(0, $span)),
                        EvaluationType::Curenta, $numeric, $now,
                    );
                }

                if ($isSummative) {
                    $rows[] = $this->gradeRow(
                        $class, $assignment, $studentId, (int) $term->id,
                        $to->copy()->subDays(random_int(0, 10)),
                        $class['grade_level'] >= 10 ? EvaluationType::Teza : EvaluationType::Esi,
                        $numeric, $now,
                    );
                }
            }
        }

        $this->insertAndRemember('grades', $rows, 'grades');

        return count($rows);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(Term $term, bool $past): array
    {
        $start = Carbon::parse((string) $term->starts_on)->startOfDay();
        $end = Carbon::parse((string) $term->ends_on)->startOfDay()->min(Carbon::today());

        return $past
            ? [$start, $end]
            : [$end->copy()->subDays(self::RECENT_DAYS)->max($start), $end];
    }

    /**
     * @param  array{id: int, grade_level: int, section: string|null}  $class
     * @param  array{subject_id: int, teacher_id: int, grading_type: string}  $assignment
     * @return array<string, mixed>
     */
    private function gradeRow(array $class, array $assignment, int $studentId, int $termId, Carbon $on, EvaluationType $type, bool $numeric, Carbon $now): array
    {
        return [
            'student_id' => $studentId,
            'subject_id' => $assignment['subject_id'],
            'school_class_id' => $class['id'],
            'term_id' => $termId,
            'teacher_id' => $assignment['teacher_id'],
            'graded_on' => $on->toDateString(),
            'evaluation_type' => $type->value,
            'value' => $numeric ? [4, 5, 6, 7, 7, 8, 8, 9, 9, 10][random_int(0, 9)] : null,
            'calificativ' => $numeric ? null : ['FB', 'FB', 'B', 'B', 'S'][random_int(0, 4)],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Absențe. În TRECUT toate sunt DECISE (motivate/nemotivate), iar cele nemotivate poartă și
     * `motivation_locked_at`: un semestru încheiat nu poate avea absențe „fără statut", fiindcă
     * termenul de motivare a expirat demult și consolidarea zilnică le-ar fi închis. În PREZENT,
     * o parte rămân fără statut — coada de triaj a dirigintelui.
     *
     * @param  array{id: int, grade_level: int, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     * @param  list<int>  $students
     */
    private function seedAbsences(array $class, array $assignments, array $students, Term $term, Carbon $now, bool $past, bool $guaranteed = false): int
    {
        [$from, $to] = $this->window($term, $past);

        if ($from->greaterThan($to) || $assignments === []) {
            return 0;
        }

        $span = max(1, (int) $from->diffInDays($to));

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($students as $index => $studentId) {
            // Aproape jumătate din clasă lipsește măcar o dată pe semestru — restul, deloc.
            // Elevii „garantați" (conturile demo) sar peste zar: ei trebuie să AIBĂ ce arăta.
            if (! $guaranteed && random_int(1, 10) > 4) {
                continue;
            }

            $howMany = match (true) {
                $guaranteed && $past => random_int(4, 7),
                $guaranteed => random_int(2, 4),
                $past => random_int(1, 5),
                default => random_int(1, 2),
            };

            foreach (range(1, $howMany) as $nth) {
                $assignment = $assignments[array_rand($assignments)];
                $on = $from->copy()->addDays(random_int(0, $span));

                $status = $past
                    ? [true, true, false, false, false][random_int(0, 4)]
                    : [null, null, true, false, false][random_int(0, 4)];

                // În prezent, primul rând al unui elev garantat rămâne FĂRĂ STATUT: coada de triaj
                // a dirigintelui nu are voie să depindă de noroc.
                if ($guaranteed && ! $past && $nth === 1) {
                    $status = null;
                }

                $rows[] = [
                    'student_id' => $studentId,
                    'subject_id' => $assignment['subject_id'],
                    'school_class_id' => $class['id'],
                    'term_id' => $term->id,
                    'teacher_id' => $assignment['teacher_id'],
                    'occurred_on' => $on->toDateString(),
                    'is_motivated' => $status,
                    'motivation_deadline' => $status === true ? null : $on->copy()->addWeekdays(5)->toDateString(),
                    // Trecutul e închis: nemotivatele au trecut prin consolidarea zilnică.
                    'motivation_locked_at' => $past && $status === false ? $on->copy()->addWeekdays(6) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $this->insertUnique($rows, fn (array $row): string => $row['student_id'].'|'.$row['occurred_on'].'|'.$row['subject_id']);
    }

    /**
     * Teme: 2–5 pe disciplină în trecut, 0–2 în prezent. Marcate în subiect, ca la curățare să se
     * vadă din listă ce e demo.
     *
     * @param  array{id: int, grade_level: int, section: string|null}  $class
     * @param  list<array{subject_id: int, teacher_id: int, grading_type: string}>  $assignments
     */
    private function seedHomework(array $class, array $assignments, Term $term, Carbon $now, bool $past): int
    {
        [$from, $to] = $this->window($term, $past);

        if ($from->greaterThan($to)) {
            return 0;
        }

        $span = max(1, (int) $from->diffInDays($to));

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($assignments as $assignment) {
            $subjectName = (string) DB::table('subjects')->where('id', $assignment['subject_id'])->value('name');

            foreach (range(1, $past ? random_int(2, 5) : random_int(0, 2)) as $ignored) {
                $rows[] = [
                    'subject_id' => $assignment['subject_id'],
                    'teacher_id' => $assignment['teacher_id'],
                    'subject_name' => $subjectName,
                    // Numele autorului, salvat ca TEXT — vezi `teacherName()`.
                    'author_name' => $this->teacherName((int) $assignment['teacher_id']),
                    'grade_level' => $class['grade_level'],
                    'section' => $class['section'],
                    'assigned_on' => $from->copy()->addDays(random_int(0, $span))->toDateString(),
                    'topic' => self::MARK.' '.self::TOPICS[array_rand(self::TOPICS)],
                    'required_task' => 'Exercițiile de la lecția curentă.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertAndRemember('homework_assignments', $rows, 'homework');

        return count($rows);
    }

    /**
     * Inserează absențele fără duplicate (elev + zi + disciplină) — aceeași regulă ca garda de pe
     * server, ca datele demo să fie de-un fel cu cele introduse prin interfață.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): string  $key
     */
    private function insertUnique(array $rows, callable $key): int
    {
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<array<string, mixed>> $unique */
        $unique = [];

        foreach ($rows as $row) {
            $id = $key($row);

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $unique[] = $row;
        }

        // Zilele deja ocupate în bază (rulări anterioare ale altor seedere) se sar la fel.
        $unique = array_values(array_filter($unique, fn (array $row): bool => ! DB::table('absences')
            ->where('student_id', $row['student_id'])
            ->where('subject_id', $row['subject_id'])
            ->whereDate('occurred_on', $row['occurred_on'])
            ->exists()));

        $this->insertAndRemember('absences', $unique, 'absences');

        return count($unique);
    }

    /**
     * Inserare în bucăți, cu id-urile reținute în manifest. Id-urile unui bloc sunt consecutive
     * (AUTO_INCREMENT, o singură instrucțiune), deci se reconstituie din primul — fără o
     * interogare pe rând.
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

    private function remove(): int
    {
        $path = storage_path('app/'.self::MANIFEST);

        if (! File::exists($path)) {
            $this->components->warn('Fără manifest ('.self::MANIFEST.') — nimic de șters.');

            return self::SUCCESS;
        }

        /** @var array<string, list<int>> $m */
        $m = json_decode((string) File::get($path), true) ?: [];

        DB::transaction(function () use ($m): void {
            foreach (['grades' => 'grades', 'absences' => 'absences', 'homework' => 'homework_assignments'] as $key => $table) {
                foreach (array_chunk($m[$key] ?? [], 1000) as $chunk) {
                    DB::table($table)->whereIn('id', array_map(intval(...), $chunk))->delete();
                }
            }
        });

        File::delete($path);

        $this->components->info('Activitatea demo de pe axa timpului a fost ștearsă.');
        $this->call('app:compute-averages');

        return self::SUCCESS;
    }
}
