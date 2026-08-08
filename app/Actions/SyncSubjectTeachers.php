<?php

namespace App\Actions;

use App\Filament\Resources\Users\RelationManagers\TeachingAssignmentsRelationManager;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

/**
 * ECHIPA disciplinei, dintr-o singură mișcare (cerința beneficiarului, 07.08.2026): fișa
 * disciplinei declară profesorii ei, fiecare cu clasele LUI — iar acțiunea aduce alocările
 * didactice (`teaching_assignments`) la exact această declarație.
 *
 * Perimetrul = ANUL CURENT. Alocările anilor trecuți sunt istorie (autorii notelor de atunci)
 * și nu se ating, orice ar spune formularul de azi. Retragerea e soft delete — nota rămâne cu
 * autorul ei, profesorul pierde doar scoping-ul viitor ({@see TeachingAssignmentsRelationManager}).
 *
 * ⚠️ Indexul unic al alocărilor vede ȘI rândurile șterse (tiparul din [[restore-center]]):
 * o pereche re-adăugată după retragere se RESTAUREAZĂ, nu se recreează — altfel insertul ar
 * pica pe duplicat, iar istoricul de audit al alocării s-ar rupe în două vieți separate.
 */
class SyncSubjectTeachers
{
    /**
     * Starea CURENTĂ, ca rânduri de formular — prefill-ul fișei la editare: alocările vii ale
     * anului curent, grupate (profesor, grupă), cu clasele fiecăruia adunate la un loc și
     * rândurile sortate pe numele profesorului.
     *
     * @return list<array{teacher_id: int, english_group: int|null, class_ids: list<int>}>
     */
    public function rowsFor(Subject $subject): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        if ($year === null) {
            return [];
        }

        $assignments = TeachingAssignment::query()
            ->where('subject_id', $subject->getKey())
            ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $year->getKey()))
            ->with(['teacher' => fn ($query) => $query->withTrashed()])
            ->get();

        $rows = [];

        foreach ($assignments as $assignment) {
            $key = $assignment->teacher_id.'|'.($assignment->english_group ?? '');

            $rows[$key] ??= [
                'teacher_id' => (int) $assignment->teacher_id,
                'english_group' => $assignment->english_group,
                'class_ids' => [],
                'sort' => mb_strtolower((string) $assignment->teacher->full_name),
            ];

            $rows[$key]['class_ids'][] = (int) $assignment->school_class_id;
        }

        usort($rows, static fn (array $a, array $b): int => [$a['sort'], $a['english_group'] ?? 0] <=> [$b['sort'], $b['english_group'] ?? 0]);

        return array_map(static function (array $row): array {
            sort($row['class_ids']);
            unset($row['sort']);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int|string, mixed>  $rows  rândurile repeater-ului, cum vin din Livewire (nefiltrate)
     * @return array{created: int, restored: int, withdrawn: int}
     */
    public function execute(Subject $subject, array $rows): array
    {
        $summary = ['created' => 0, 'restored' => 0, 'withdrawn' => 0];

        $year = AcademicYear::query()->where('is_current', true)->first();

        if ($year === null) {
            return $summary;
        }

        // Clasele anului curent cu treapta lor — perimetrul diff-ului ȘI filtrul de apărare:
        // o clasă din alt an sau de pe o treaptă nemarcată nu intră, orice ar purta payload-ul
        // (validarea din formular e dublată aici, pe singura cale de scriere).
        /** @var array<int, int> $classGrades */
        $classGrades = SchoolClass::query()
            ->where('academic_year_id', $year->getKey())
            ->pluck('grade_level', 'id')
            ->map(static fn ($grade): int => (int) $grade)
            ->all();

        $desired = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['teacher_id'] ?? null)) {
                continue;
            }

            $teacherId = (int) $row['teacher_id'];
            $group = $row['english_group'] ?? null;
            // Grupa are sens DOAR la limba engleză — pe orice altă disciplină se ignoră aici,
            // exact cum ar anula-o și garda de pe model (TeachingAssignmentObserver::saving).
            $group = $subject->isEnglishLanguage() && is_numeric($group) ? (int) $group : null;

            foreach ((array) ($row['class_ids'] ?? []) as $classId) {
                if (! is_numeric($classId)) {
                    continue;
                }

                $classId = (int) $classId;
                $grade = $classGrades[$classId] ?? null;

                if ($grade === null || ! $subject->coversGrade($grade)) {
                    continue;
                }

                $desired[$teacherId.'|'.$classId.'|'.($group ?? '')] = [
                    'teacher_id' => $teacherId,
                    'school_class_id' => $classId,
                    'english_group' => $group,
                ];
            }
        }

        DB::transaction(function () use ($subject, $classGrades, $desired, &$summary): void {
            $existing = TeachingAssignment::withTrashed()
                ->where('subject_id', $subject->getKey())
                ->whereIn('school_class_id', array_keys($classGrades))
                ->get();

            foreach ($existing as $assignment) {
                $key = $assignment->teacher_id.'|'.$assignment->school_class_id.'|'.($assignment->english_group ?? '');

                if (array_key_exists($key, $desired)) {
                    if ($assignment->trashed()) {
                        $assignment->restore();
                        $summary['restored']++;
                    }

                    // Perechea există (vie sau tocmai restaurată) — nu mai e de creat.
                    unset($desired[$key]);

                    continue;
                }

                if (! $assignment->trashed()) {
                    $assignment->delete();
                    $summary['withdrawn']++;
                }
            }

            foreach ($desired as $attributes) {
                TeachingAssignment::query()->create($attributes + ['subject_id' => $subject->getKey()]);
                $summary['created']++;
            }
        });

        return $summary;
    }
}
