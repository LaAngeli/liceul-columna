<?php

namespace App\Actions;

use App\Enums\AcademicRecordPeriod;
use App\Jobs\ArchiveYearJob;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\CorigentaExam;
use App\Models\Enrollment;
use App\Models\TermAverage;
use App\Observers\CorigentaExamObserver;
use App\Support\Grades;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ÎNCHIDEREA ANULUI (spec §2.4/§2.5): arhivează mediile semestriale ale unui an școlar în foaia
 * matricolă (`academic_records`) — până acum, matricola creștea DOAR din importul legacy și din
 * corigențe, deci anii noi nu ajungeau niciodată în arhiva oficială, iar tabul Istoric al
 * cabinetului „pierdea" anul închis.
 *
 * Reguli (Regulamentul intern, §2.4 — identice pe cicluri la nivel anual):
 *  - Sem I / Sem II = mediile semestriale (MS) din `term_averages`, verbatim;
 *  - ANUALĂ = media aritmetică a celor două MS, sutimi FĂRĂ rotunjire — doar când AMBELE există
 *    (cu un singur semestru, situația e nedefinitivată — „amânat" — și anuala lipsește);
 *  - CORIGENȚA are prioritate (§2.5): dacă există examen de corigență CU NOTĂ pe (elev, disciplină)
 *    în anul arhivat, nota examenului = rezultatul oficial anual (aceeași regulă ca
 *    {@see CorigentaExamObserver}) — arhivarea nu o rescrie cu media picată.
 *
 * Idempotent (scriere pe cheia logică) — re-rularea după corecții reîmprospătează arhiva.
 * Treapta vine din înmatricularea elevului în anul arhivat; elevii fără înmatriculare sunt săriți
 * și numărați (situație de semnalat operatorului).
 *
 * EXECUȚIE (2026-07-16, după timeout-ul de 30s din panou): totul rulează într-o TRANZACȚIE
 * (o cădere la mijloc nu mai lasă matricola pe jumătate rescrisă), rândurile existente sunt
 * PREÎNCĂRCATE (o interogare, nu un SELECT per rând), iar rândurile NESCHIMBATE se sar de tot
 * (fără UPDATE, fără rând de audit) — `records` numără doar rândurile SCRISE efectiv. Din panou
 * se rulează pe QUEUE ({@see ArchiveYearJob}); sincron rămâne doar CLI-ul.
 *
 * DECIZII DOCUMENTATE (#36, 2026-07-12):
 *  - Disciplinele notate prin CALIFICATIV nu se arhivează (filtrul `whereNotNull('value')`):
 *    Regulamentul (§2.4) definește agregarea anuală doar pentru scala numerică — nu inventăm o
 *    formulă „admis + admis = admis". Amânat până când școala stabilește regula oficială.
 *  - Cheia matricolei rămâne (elev, disciplină, TREAPTĂ, perioadă), fără dimensiune de an:
 *    repetenția e eliminată prin regulament (reintegrarea corigenței nepromise → treapta se reia
 *    doar prin re-înmatriculare), deci treapta identifică unic anul pentru un elev. O coloană de
 *    an ar dubla cheia fără să adauge informație.
 */
class ArchiveYearToTranscript
{
    /**
     * @return array{records: int, students: int, skipped: int}
     */
    public function run(AcademicYear $year): array
    {
        // Atomic: dacă orice scriere pică, matricola rămâne EXACT cum era (nu pe jumătate rescrisă).
        return DB::transaction(fn (): array => $this->archive($year));
    }

    /**
     * @return array{records: int, students: int, skipped: int}
     */
    private function archive(AcademicYear $year): array
    {
        // Semestrele anului, pe număr (I/II). withTrashed: un semestru arhivat nu ascunde istoricul.
        $termIdsByNumber = $year->terms()->withTrashed()->pluck('id', 'number');

        if ($termIdsByNumber->isEmpty()) {
            return ['records' => 0, 'students' => 0, 'skipped' => 0];
        }

        // Treapta fiecărui elev în anul arhivat (înmatricularea + clasa, inclusiv arhivate).
        $gradeLevelByStudent = Enrollment::withTrashed()
            ->with('schoolClass')
            ->where('academic_year_id', $year->id)
            ->get()
            ->mapWithKeys(fn (Enrollment $e) => [$e->student_id => $e->schoolClass?->grade_level])
            ->filter();

        // Rezultatele de corigență CU NOTĂ din anul arhivat — prioritare la media anuală (§2.5).
        $corigentaMarks = CorigentaExam::query()
            ->whereIn('term_id', $termIdsByNumber->values())
            ->whereNotNull('mark')
            ->get()
            ->keyBy(fn (CorigentaExam $e): string => $e->student_id.'-'.$e->subject_id);

        $semesterByTermId = $termIdsByNumber
            ->mapWithKeys(fn ($id, $number) => [(int) $id => (int) $number]);

        // Rândurile de matricolă existente ale elevilor implicați, PREÎNCĂRCATE (o interogare,
        // doar coloanele necesare — zeci de mii de modele complete ar umfla memoria) — înainte,
        // fiecare rând însemna un SELECT separat (×3 per pereche elev–disciplină).
        $existingRecords = AcademicRecord::query()
            ->whereIn('student_id', $gradeLevelByStudent->keys()->all())
            ->get(['id', 'student_id', 'subject_id', 'grade_level', 'period', 'value'])
            ->keyBy(fn (AcademicRecord $r): string => self::recordKey($r->student_id, $r->subject_id, $r->grade_level, $r->period->value));

        $records = 0;
        $skipped = 0;
        $students = [];

        TermAverage::query()
            ->whereIn('term_id', $termIdsByNumber->values())
            ->whereNotNull('value')
            ->get()
            ->groupBy(fn (TermAverage $ta): string => $ta->student_id.'-'.$ta->subject_id)
            ->each(function ($group, string $key) use ($gradeLevelByStudent, $corigentaMarks, $semesterByTermId, $existingRecords, &$records, &$skipped, &$students): void {
                /** @var TermAverage $first */
                $first = $group->first();
                $gradeLevel = $gradeLevelByStudent->get($first->student_id);

                if ($gradeLevel === null) {
                    $skipped++;

                    return;
                }

                $students[$first->student_id] = true;

                $byNumber = [];
                foreach ($group as $average) {
                    $byNumber[$semesterByTermId->get((int) $average->term_id)] = (float) $average->value;
                }

                foreach ([1 => AcademicRecordPeriod::SemesterI, 2 => AcademicRecordPeriod::SemesterII] as $number => $period) {
                    if (! isset($byNumber[$number])) {
                        continue;
                    }

                    $records += $this->writeRecord($existingRecords, (int) $first->student_id, (int) $first->subject_id, (int) $gradeLevel, $period, $byNumber[$number]);
                }

                // Anuala: corigența promovată/ dată bate media; altfel media celor DOUĂ semestre.
                $corigenta = $corigentaMarks->get($key);
                $annual = $corigenta !== null
                    ? (float) $corigenta->mark
                    : (isset($byNumber[1], $byNumber[2]) ? Grades::truncate2(($byNumber[1] + $byNumber[2]) / 2) : null);

                if ($annual !== null) {
                    $records += $this->writeRecord($existingRecords, (int) $first->student_id, (int) $first->subject_id, (int) $gradeLevel, AcademicRecordPeriod::Annual, $annual);
                }
            });

        return ['records' => $records, 'students' => count($students), 'skipped' => $skipped];
    }

    /**
     * Scrie un rând de matricolă pe cheia logică: neschimbat → sărit (fără UPDATE, fără audit);
     * diferit → actualizat; inexistent → creat. Întoarce 1 doar când s-a SCRIS ceva.
     *
     * @param  Collection<string, AcademicRecord>  $existingRecords
     */
    private function writeRecord(Collection $existingRecords, int $studentId, int $subjectId, int $gradeLevel, AcademicRecordPeriod $period, float $value): int
    {
        $key = self::recordKey($studentId, $subjectId, $gradeLevel, $period->value);
        $existing = $existingRecords->get($key);

        if ($existing !== null) {
            if ((float) $existing->value === $value) {
                return 0;
            }

            $existing->update(['value' => $value]);

            return 1;
        }

        $created = AcademicRecord::create([
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'grade_level' => $gradeLevel,
            'period' => $period,
            'value' => $value,
        ]);

        $existingRecords->put($key, $created);

        return 1;
    }

    private static function recordKey(int|string $studentId, int|string $subjectId, int|string $gradeLevel, int $period): string
    {
        return $studentId.'-'.$subjectId.'-'.$gradeLevel.'-'.$period;
    }
}
