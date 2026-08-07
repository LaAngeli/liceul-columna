<?php

namespace App\Console\Commands;

use App\Enums\AcademicRecordPeriod;
use App\Enums\Calificativ;
use App\Enums\GradingType;
use App\Support\Grades;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * FOAIA MATRICOLĂ a școlii demo (cerința beneficiarului, 05.08.2026).
 *
 * Golul găsit înainte de a scrie: cei 203 de elevi demo ai anului curent aveau ZERO înregistrări
 * în `academic_records`, în timp ce arhiva importată are 44.240 — deci secțiunea „Foaie matricolă"
 * arăta goală pe exact conturile pe care se face demonstrația, iar tot ce se sprijină pe istoric
 * (evoluția multi-an din cabinet, media pe treaptă, span-ul de trepte) n-avea ce compara.
 *
 * CONVENȚIA e citită din datele școlii, nu presupusă: la toți cei 400 de elevi reali eșantionați,
 * treapta maximă din foaie e EGALĂ cu clasa curentă — foaia acoperă 1..clasa curentă. Se potrivește
 * și cu momentul: anul 2025–2026 s-a încheiat, deci și treapta curentă e o treaptă încheiată.
 *
 * DE CE traiectorii, nu valori aleatorii: o foaie matricolă se citește pe VERTICALĂ — „cum a
 * evoluat copilul". Zgomotul pur ar fi arătat plauzibil pe un rând și absurd pe coloană (9 la
 * matematică în clasa I, 4 în a II-a, 9 în a III-a). Fiecare elev primește deci o aptitudine de
 * bază, o pantă proprie (urcă / se menține / coboară) și afinități stabile pe disciplină; totul
 * derivat DETERMINIST din id-uri, deci o re-rulare dă exact aceleași date.
 *
 * Media anuală urmează convenția §2.4 — trunchiere la sutimi, fără rotunjire.
 *
 * Scrie prin query builder (fără observers/audit), ca toate seederele demo. CURĂȚARE: manifest
 * propriu (`storage/app/demo/transcripts.json`) cu id-urile inserate; `--remove` le șterge exact.
 */
class SeedDemoTranscripts extends Command
{
    protected $signature = 'app:seed-demo-transcripts
        {--remove : Șterge foile matricole create de această comandă (folosind manifestul)}';

    protected $description = 'Completează foaia matricolă a elevilor demo: istoricul pe trepte, cu traiectorii coerente';

    private const MANIFEST = 'demo/transcripts.json';

    /** @var list<int> */
    private array $manifest = [];

    public function handle(): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        if (File::exists(storage_path('app/'.self::MANIFEST))) {
            $this->components->error('Există deja un manifest ('.self::MANIFEST.'). Rulează întâi `--remove`.');

            return self::FAILURE;
        }

        $students = $this->demoStudents();

        if ($students === []) {
            $this->components->error('Niciun elev demo înmatriculat în anul curent — nimic de completat.');

            return self::FAILURE;
        }

        $subjects = $this->subjectsByLevel();

        $rows = [];
        $now = now();

        foreach ($students as $student) {
            for ($level = 1; $level <= $student['grade_level']; $level++) {
                foreach ($subjects[$level] ?? [] as $subject) {
                    foreach ($this->recordsFor($student['id'], $subject, $level) as $record) {
                        $rows[] = $record + [
                            'student_id' => $student['id'],
                            'subject_id' => $subject['id'],
                            'grade_level' => $level,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        $this->insertAndRemember($rows);

        File::ensureDirectoryExists(storage_path('app/demo'));
        File::put(
            storage_path('app/'.self::MANIFEST),
            (string) json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        $this->components->info(sprintf(
            'Foaie matricolă demo: %s înregistrări pentru %d elevi.',
            number_format(count($this->manifest)),
            count($students),
        ));
        $this->line('  <fg=gray>Manifest: storage/app/'.self::MANIFEST.'</>');

        return self::SUCCESS;
    }

    /**
     * Elevii demo ai anului CURENT, cu treapta clasei în care sunt înmatriculați.
     *
     * @return list<array{id: int, grade_level: int}>
     */
    private function demoStudents(): array
    {
        $rows = DB::table('enrollments as e')
            ->join('school_classes as c', 'c.id', '=', 'e.school_class_id')
            ->join('academic_years as y', 'y.id', '=', 'c.academic_year_id')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->where('y.is_current', true)
            ->where('c.name', 'like', '%[DEMO]%')
            ->whereNull('e.left_on')
            ->whereNull('c.deleted_at')
            ->whereNull('s.deleted_at')
            ->select('s.id', 'c.grade_level')
            ->distinct()
            ->orderBy('s.id')
            ->get();

        $students = [];

        foreach ($rows as $row) {
            $students[] = [
                'id' => (int) $row->id,
                'grade_level' => (int) $row->grade_level,
            ];
        }

        return $students;
    }

    /**
     * Disciplinele valabile pe fiecare treaptă 1–12, după setul propriu (`grade_levels` =
     * treptele la care se predă, NU limite de notă).
     *
     * @return array<int, list<array{id: int, grading_type: GradingType}>>
     */
    private function subjectsByLevel(): array
    {
        // Disciplinele NICIODATĂ predate se exclud: nici alocare, nici notă, nici rând de arhivă
        // nicăieri în școală. Așa a rămas în tabel „doloremque at" — o scurgere de factory (fără
        // nicio urmă de folosire) care ar fi apărut pe fiecare foaie matricolă a demonstrației.
        $subjects = DB::table('subjects')
            ->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('teaching_assignments')->whereColumn('teaching_assignments.subject_id', 'subjects.id'))
            ->select('id', 'grade_levels', 'grading_type')
            ->orderBy('id')
            ->get();

        $byLevel = [];

        foreach (range(1, 12) as $level) {
            foreach ($subjects as $subject) {
                /** @var list<int>|null $levels set discret; NULL = nomenclator incomplet, nu limitează */
                $levels = $subject->grade_levels === null
                    ? null
                    : array_map(intval(...), (array) json_decode((string) $subject->grade_levels, true));

                if ($levels !== null && ! in_array($level, $levels, true)) {
                    continue;
                }

                $byLevel[$level][] = [
                    'id' => (int) $subject->id,
                    'grading_type' => GradingType::tryFrom((string) $subject->grading_type) ?? GradingType::Numeric,
                ];
            }
        }

        return $byLevel;
    }

    /**
     * Cele trei rânduri ale unei (disciplină × treaptă): Sem. I, Sem. II și media anuală.
     *
     * La disciplinele numerice, anuala e media semestrelor, trunchiată la sutimi (§2.4) — exact ce
     * verifică cititorul cu ochiul pe document. La celelalte, anuala e simbolul care corespunde
     * nivelului mediu al semestrelor, ca să nu apară „FB, S → FB" fără explicație.
     *
     * @param  array{id: int, grading_type: GradingType}  $subject
     * @return list<array{period: int, value: string|null, calificativ: string|null}>
     */
    private function recordsFor(int $studentId, array $subject, int $level): array
    {
        $sem1 = $this->ability($studentId, $subject['id'], $level, semester: 1);
        $sem2 = $this->ability($studentId, $subject['id'], $level, semester: 2);

        if ($subject['grading_type'] === GradingType::Numeric) {
            $annual = Grades::truncate2(($sem1 + $sem2) / 2);

            return [
                ['period' => AcademicRecordPeriod::SemesterI->value, 'value' => number_format($sem1, 2, '.', ''), 'calificativ' => null],
                ['period' => AcademicRecordPeriod::SemesterII->value, 'value' => number_format($sem2, 2, '.', ''), 'calificativ' => null],
                ['period' => AcademicRecordPeriod::Annual->value, 'value' => number_format($annual, 2, '.', ''), 'calificativ' => null],
            ];
        }

        $descriptiv = $subject['grading_type'] === GradingType::Descriptiv
            || $subject['grading_type'] === GradingType::CalificativDescriptiv;

        return [
            ['period' => AcademicRecordPeriod::SemesterI->value, 'value' => null, 'calificativ' => $this->symbol($sem1, $descriptiv, $subject['id'])],
            ['period' => AcademicRecordPeriod::SemesterII->value, 'value' => null, 'calificativ' => $this->symbol($sem2, $descriptiv, $subject['id'])],
            ['period' => AcademicRecordPeriod::Annual->value, 'value' => null, 'calificativ' => $this->symbol(($sem1 + $sem2) / 2, $descriptiv, $subject['id'])],
        ];
    }

    /**
     * Media unui elev la o disciplină, pe o treaptă și un semestru — pe scala 1–10.
     *
     * Formula compune patru straturi, toate DETERMINISTE (aceleași id-uri → aceleași valori):
     * aptitudinea de bază a elevului, panta lui în timp (urcă / se menține / coboară), afinitatea
     * stabilă pentru disciplină și o oscilație mică între semestre. Rezultatul se citește coerent
     * pe verticală, ceea ce e tot rostul unei foi matricole.
     */
    private function ability(int $studentId, int $subjectId, int $level, int $semester): float
    {
        // Aptitudine de bază: 5,40 … 9,60.
        $base = 5.4 + $this->noise('baza', $studentId) * 4.2;

        // Panta pe an: −0,26 … +0,26 pe treaptă, raportată la mijlocul parcursului.
        $slope = ($this->noise('panta', $studentId) - 0.5) * 0.52;
        $trend = $slope * ($level - 6);

        // Afinitate stabilă pe disciplină: −0,9 … +0,9 (unele materii îi merg mereu mai bine).
        $affinity = ($this->noise('afinitate', $studentId, $subjectId) - 0.5) * 1.8;

        // Diferența dintre semestre: −0,55 … +0,55 — două semestre nu ies niciodată identice.
        $wobble = ($this->noise('semestru', $studentId, $subjectId, $level, $semester) - 0.5) * 1.1;

        return round(min(10.0, max(2.0, $base + $trend + $affinity + $wobble)), 2);
    }

    /**
     * Simbolul care corespunde unui nivel numeric. Școala folosește AMBELE scale și pe aceleași
     * discipline (vezi {@see Calificativ}), deci la cele descriptive alternăm între ele — altfel
     * demonstrația n-ar arăta niciodată descriptorii.
     */
    private function symbol(float $level, bool $descriptiv, int $subjectId): string
    {
        // Scala se alege pe DISCIPLINĂ, o dată — nu pe rând. Prima versiune dădea semințe diferite
        // pentru Sem. I / Sem. II / anuală, iar documentul ieșea „i, FB, FB" pe același rând: un
        // învățător nu comută între descriptori și calificative de la un semestru la altul.
        if ($descriptiv && $this->noise('scala', $subjectId) < 0.45) {
            return match (true) {
                $level >= 8.5 => Calificativ::Independent->value,
                $level >= 6.5 => Calificativ::Ghidat->value,
                default => Calificativ::CuSprijin->value,
            };
        }

        return match (true) {
            $level >= 8.5 => Calificativ::FoarteBine->value,
            $level >= 6.5 => Calificativ::Bine->value,
            default => Calificativ::Suficient->value,
        };
    }

    /**
     * Zgomot determinist în [0, 1), pe „fire" independente numite.
     *
     * NU folosim `random_int`/`fake()`: o foaie matricolă regenerată trebuie să arate la fel, ca
     * beneficiarul să poată reveni la același elev și să regăsească aceleași cifre.
     *
     * ⚠️ Prima versiune folosea `(sămânță × multiplicator) % modul` — o funcție LINIARĂ, deci
     * semințe vecine dădeau valori vecine. Rezultatul măsurat: Sem. I 6,90 și Sem. II 6,92 la toate
     * disciplinele, fiindcă cele două semințe diferă exact cu 1. `crc32` amestecă biții, deci
     * intrări apropiate dau ieșiri necorelate — iar firul din prefix ține străturile independente.
     */
    private function noise(string $stream, int ...$parts): float
    {
        return (crc32($stream.':'.implode(':', $parts)) % 1000000) / 1000000;
    }

    /**
     * Inserare în bucăți, cu id-urile reținute în manifest — id-urile unui bloc sunt consecutive
     * de la primul returnat (MySQL, InnoDB, insert în lot).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertAndRemember(array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            $firstId = (int) DB::table('academic_records')->insertGetId($chunk[0]);
            $this->manifest[] = $firstId;

            $rest = array_slice($chunk, 1);

            if ($rest === []) {
                continue;
            }

            DB::table('academic_records')->insert($rest);

            $ids = DB::table('academic_records')
                ->where('id', '>', $firstId)
                ->orderBy('id')
                ->limit(count($rest))
                ->pluck('id');

            foreach ($ids as $id) {
                $this->manifest[] = (int) $id;
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

        /** @var list<int> $ids */
        $ids = json_decode((string) File::get($path), true) ?: [];

        DB::transaction(function () use ($ids): void {
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('academic_records')->whereIn('id', array_map(intval(...), $chunk))->delete();
            }
        });

        File::delete($path);

        $this->components->info('Foile matricole demo au fost șterse.');

        return self::SUCCESS;
    }
}
