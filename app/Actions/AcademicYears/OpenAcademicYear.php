<?php

namespace App\Actions\AcademicYears;

use App\Actions\Enrollments\PromoteClass;
use App\Enums\SchoolCycle;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DESCHIDEREA unui an școlar: structura anului precedent urcă o treaptă în anul nou — clasele
 * (cu secția și dirigintele lor) și alocările de predare care mai au sens la treapta nouă.
 *
 * Perechea structurală a promovării elevilor ({@see PromoteClass}):
 * aceea muta OAMENII, dar în clase care trebuiau să existe deja. Fără pasul de față, deschiderea
 * unui an însemna reintroducerea manuală a 52 de clase și a peste 500 de alocări — an de an,
 * aproape identic.
 *
 * Ce NU se preia, deliberat — și de ce:
 *   • clasele de treaptă maximă (a XII-a): promovarea lor e absolvirea, nu o clasă a XIII-a;
 *   • clasa I: cohorta nouă nu are sursă în anul trecut — se adaugă manual, e o decizie de admitere;
 *   • alocările a căror DISCIPLINĂ nu se predă la treapta nouă (nomenclatorul, `grade_levels`,
 *     decide): la granițele de ciclu (IV→V, IX→X) curriculumul se schimbă, iar o copiere oarbă
 *     ar inventa ore care nu există;
 *   • dirigintele arhivat între ani: clasa se creează fără diriginte și apare în „Clase fără diriginte";
 *   • clasele care există deja în anul-țintă (inclusiv arhivate — indexul unic le vede): se sar,
 *     nu se dublează, deci acțiunea se poate relua fără teamă.
 *
 * Anul sursă rămâne NEATINS: e registrul lui, cu istoricul lui.
 */
class OpenAcademicYear
{
    /**
     * Planul deschiderii — se calculează identic pentru previzualizare și pentru execuție, ca
     * ce vede administratorul în modal să fie exact ce se scrie.
     *
     * @return array{
     *     blocked: 'closed'|'same_year'|'not_after'|'no_source'|null,
     *     promoted: list<array{source: SchoolClass, grade: int, assignments: int, dropped: int}>,
     *     existing: list<array{source: SchoolClass, archived: bool}>,
     *     graduating: list<SchoolClass>,
     * }
     */
    public function plan(AcademicYear $target, ?AcademicYear $source): array
    {
        $empty = ['promoted' => [], 'existing' => [], 'graduating' => []];

        if ($target->closed_at !== null) {
            return ['blocked' => 'closed', ...$empty];
        }

        if ($source === null) {
            return ['blocked' => 'no_source', ...$empty];
        }

        if ((int) $source->getKey() === (int) $target->getKey()) {
            return ['blocked' => 'same_year', ...$empty];
        }

        // Cronologia e sensul operațiunii: „o treaptă mai sus" are înțeles doar înainte în timp.
        if ($source->starts_on !== null && $target->starts_on !== null && $source->starts_on >= $target->starts_on) {
            return ['blocked' => 'not_after', ...$empty];
        }

        $sourceClasses = SchoolClass::query()
            ->where('academic_year_id', $source->getKey())
            ->with(['teachingAssignments.subject'])
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get();

        if ($sourceClasses->isEmpty()) {
            return ['blocked' => 'no_source', ...$empty];
        }

        $taken = $this->existingTargetKeys($target);

        $promoted = [];
        $existing = [];
        $graduating = [];

        foreach ($sourceClasses as $class) {
            $grade = (int) $class->grade_level;

            if ($grade >= SchoolCycle::MAX_GRADE_LEVEL) {
                $graduating[] = $class;

                continue;
            }

            $targetGrade = $grade + 1;
            $key = $this->classKey($targetGrade, $class->section);

            if (array_key_exists($key, $taken)) {
                $existing[] = ['source' => $class, 'archived' => $taken[$key]];

                continue;
            }

            [$kept, $dropped] = $this->splitAssignments($class->teachingAssignments, $targetGrade);

            $promoted[] = [
                'source' => $class,
                'grade' => $targetGrade,
                'assignments' => count($kept),
                'dropped' => $dropped,
            ];
        }

        return ['blocked' => null, 'promoted' => $promoted, 'existing' => $existing, 'graduating' => $graduating];
    }

    /**
     * Execuția, într-o singură tranzacție: o cădere la mijloc nu lasă jumătate de an deschis.
     * Scrierea trece prin MODELE — clasele și alocările au observeri (rolul de diriginte, membria
     * de profesor, normalizarea grupei) pe care un insert în masă i-ar ocoli tăcut.
     *
     * @return array{blocked: 'closed'|'same_year'|'not_after'|'no_source'|null, classes: int, assignments: int, dropped: int, existing: int, graduating: int, homeroom_missing: int}
     */
    public function handle(AcademicYear $target, AcademicYear $source, bool $withAssignments = true): array
    {
        $plan = $this->plan($target, $source);

        $result = [
            'blocked' => $plan['blocked'],
            'classes' => 0,
            'assignments' => 0,
            'dropped' => 0,
            'existing' => count($plan['existing']),
            'graduating' => count($plan['graduating']),
            'homeroom_missing' => 0,
        ];

        if ($plan['blocked'] !== null || $plan['promoted'] === []) {
            return $result;
        }

        DB::transaction(function () use ($plan, $target, $withAssignments, &$result): void {
            foreach ($plan['promoted'] as $row) {
                /** @var SchoolClass $source */
                $source = $row['source'];

                // Dirigintele arhivat între ani nu se transferă — clasa se naște fără titular și
                // intră în widget-ul „Clase fără diriginte", unde numirea se face pe loc.
                $homeroomId = $this->survivingHomeroomId($source);

                if ($homeroomId === null && $source->homeroom_teacher_id !== null) {
                    $result['homeroom_missing']++;
                }

                $class = SchoolClass::query()->create([
                    'academic_year_id' => $target->getKey(),
                    'grade_level' => $row['grade'],
                    'section' => $source->section,
                    'homeroom_teacher_id' => $homeroomId,
                ]);

                $result['classes']++;

                [$kept, $dropped] = $this->splitAssignments($source->teachingAssignments, $row['grade']);
                $result['dropped'] += $dropped;

                if (! $withAssignments) {
                    continue;
                }

                foreach ($kept as $assignment) {
                    TeachingAssignment::query()->create([
                        'teacher_id' => $assignment->teacher_id,
                        'subject_id' => $assignment->subject_id,
                        'school_class_id' => $class->getKey(),
                        'english_group' => $assignment->english_group,
                    ]);

                    $result['assignments']++;
                }
            }
        });

        return $result;
    }

    /**
     * Anii care pot fi SURSĂ pentru un an-țintă: cei dinaintea lui care au clase. Fără ei
     * acțiunea nu are ce prelua și nici nu se oferă.
     *
     * @return array<int, string> id => nume
     */
    public function sourceYearsFor(AcademicYear $target): array
    {
        $years = AcademicYear::query()
            ->whereKeyNot($target->getKey())
            ->whereHas('schoolClasses')
            ->orderByDesc('starts_on')
            ->orderByDesc('name')
            ->get();

        $options = [];

        foreach ($years as $year) {
            if ($target->starts_on !== null && $year->starts_on !== null && $year->starts_on >= $target->starts_on) {
                continue;
            }

            $options[(int) $year->getKey()] = (string) $year->name;
        }

        return $options;
    }

    /** Anul-sursă implicit: cel mai recent an anterior cu clase. */
    public function defaultSourceFor(AcademicYear $target): ?int
    {
        $options = $this->sourceYearsFor($target);

        return $options === [] ? null : (int) array_key_first($options);
    }

    /**
     * Alocările care urcă vs. cele care rămân: decide NOMENCLATORUL (intervalul de trepte al
     * disciplinei), nu o presupunere despre curriculum.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return array{0: list<TeachingAssignment>, 1: int}
     */
    private function splitAssignments(Collection $assignments, int $targetGrade): array
    {
        $kept = [];
        $dropped = 0;

        foreach ($assignments as $assignment) {
            $subject = $assignment->subject;

            // Profesorul sau disciplina arhivate între ani: alocarea nu se reînvie într-un an nou.
            if ($subject === null || $assignment->teacher === null) {
                $dropped++;

                continue;
            }

            if (! $subject->coversGrade($targetGrade)) {
                $dropped++;

                continue;
            }

            $kept[] = $assignment;
        }

        return [$kept, $dropped];
    }

    private function survivingHomeroomId(SchoolClass $class): ?int
    {
        $teacher = $class->homeroomTeacher;

        return $teacher === null ? null : (int) $teacher->getKey();
    }

    /**
     * Clasele deja prezente în anul-țintă, cheie (treaptă, secție) => arhivată?. Indexul unic
     * din DB vede ȘI rândurile arhivate, deci recrearea uneia arhivate ar pica — o raportăm
     * separat, ca administratorul să știe că acolo se RESTAUREAZĂ, nu se creează.
     *
     * @return array<string, bool>
     */
    private function existingTargetKeys(AcademicYear $target): array
    {
        $keys = [];

        $classes = SchoolClass::withTrashed()
            ->where('academic_year_id', $target->getKey())
            ->get(['grade_level', 'section', 'deleted_at']);

        foreach ($classes as $class) {
            $keys[$this->classKey((int) $class->grade_level, $class->section)] = $class->trashed();
        }

        return $keys;
    }

    private function classKey(int $gradeLevel, ?string $section): string
    {
        return $gradeLevel.'|'.mb_strtoupper(trim((string) $section));
    }
}
