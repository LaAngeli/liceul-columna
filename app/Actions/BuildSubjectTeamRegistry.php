<?php

namespace App\Actions;

use App\Filament\Resources\Subjects\Widgets\SubjectTeamRegistry;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Support\GradeLevels;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

/**
 * REGISTRUL ECHIPEI unei discipline, pe ANI (v3, 08.08.2026 — „o variantă complet nouă, mai
 * intuitivă", păstrând indicatorii stabiliți: implicit anul curent, nu toți anii deodată,
 * un rând per profesor, alocările retrase vizibile).
 *
 * Acțiunea întoarce EXACT ce are de arătat widget-ul ({@see SubjectTeamRegistry}):
 * lista anilor care CHIAR au alocări la disciplină (filele) + echipa anului ales — un rând per
 * (profesor, grupă), cu clasele lui ca pastile (activă / retrasă) și un rezumat numeric.
 * Istoria vine întreagă (inclusiv alocările retrase și fișele arhivate) — registrul e singurul
 * loc care o arată; scrierea rămâne a formularului ({@see SyncSubjectTeachers}).
 *
 * @phpstan-type RegistryYear array{id: int, name: string, is_current: bool}
 * @phpstan-type RegistryClassChip array{label: string, withdrawn: bool}
 * @phpstan-type RegistryTeacher array{name: string, archived: bool, group: int|null, classes: array<int, RegistryClassChip>, active: int, withdrawn: int}
 */
class BuildSubjectTeamRegistry
{
    /**
     * @return array{
     *     years: array<int, RegistryYear>,
     *     selected: RegistryYear|null,
     *     teachers: array<int, RegistryTeacher>,
     *     stats: array{teachers: int, active: int, withdrawn: int},
     * }
     */
    public function execute(Subject $subject, ?int $yearId = null): array
    {
        $years = $this->years($subject);

        $selected = collect($years)->firstWhere('id', $yearId)
            ?? collect($years)->firstWhere('is_current', true)
            ?? ($years[0] ?? null);

        $assignments = $selected === null ? collect() : $this->assignmentsFor($subject, $selected['id']);

        return [
            'years' => $years,
            'selected' => $selected,
            'teachers' => $this->teacherRows($assignments),
            // Rezumatul spune ce PROMITE eticheta (review 08.08.2026): „profesori" = oameni
            // DISTINCȚI (la engleză, două grupe = două rânduri, dar tot UN profesor), „clase
            // active" = clase DISTINCTE (aceeași clasă pe două grupe nu se numără de două ori);
            // doar „alocările retrase" rămân numărate ca alocări — chiar asta sunt.
            'stats' => [
                'teachers' => $assignments->pluck('teacher_id')->unique()->count(),
                'active' => $assignments->reject(fn (TeachingAssignment $a): bool => $a->trashed())
                    ->pluck('school_class_id')->unique()->count(),
                'withdrawn' => $assignments->filter(fn (TeachingAssignment $a): bool => $a->trashed())->count(),
            ],
        ];
    }

    /**
     * FILELE: doar anii în care disciplina are alocări (vii sau retrase), cei mai noi primii —
     * o filă goală ar fi zgomot, iar „toți anii deodată" e exact ce s-a cerut să dispară.
     *
     * @return array<int, array{id: int, name: string, is_current: bool}>
     */
    private function years(Subject $subject): array
    {
        $classIds = TeachingAssignment::withTrashed()
            ->where('subject_id', $subject->getKey())
            ->distinct()
            ->pluck('school_class_id');

        return AcademicYear::query()
            // Clasele ARHIVATE nu au voie să înghită istoria: alocarea pe o clasă soft-ștearsă
            // e tot istorie legitimă, iar registrul e singurul loc care o arată.
            ->whereIn('id', SchoolClass::withTrashed()->whereIn('id', $classIds)->distinct()->pluck('academic_year_id'))
            ->orderByDesc('starts_on')
            ->get(['id', 'name', 'is_current'])
            ->map(static fn (AcademicYear $year): array => [
                'id' => (int) $year->getKey(),
                'name' => (string) $year->name,
                'is_current' => (bool) $year->is_current,
            ])
            ->values()
            ->all();
    }

    /**
     * Alocările anului (vii + retrase), cu relațiile încărcate INCLUSIV pentru fișele arhivate —
     * clasa soft-ștearsă sau profesorul retras din școală rămân istorie citibilă, nu găuri.
     *
     * @return Collection<int, TeachingAssignment>
     */
    private function assignmentsFor(Subject $subject, int $yearId): Collection
    {
        return TeachingAssignment::withTrashed()
            ->where('subject_id', $subject->getKey())
            ->whereHas(
                'schoolClass',
                // Echivalentul lui withTrashed, dar curat pe builderul generic al lui whereHas.
                fn ($query) => $query->withoutGlobalScopes([SoftDeletingScope::class])->where('academic_year_id', $yearId),
            )
            ->with([
                'schoolClass' => fn ($query) => $query->withTrashed(),
                'teacher' => fn ($query) => $query->withTrashed(),
            ])
            ->get();
    }

    /**
     * ECHIPA anului: un rând per (profesor, grupă) — grupa desparte rândurile doar la engleză,
     * unde chiar e identitate — cu clasele sortate pe treaptă și nume, fiecare cu starea ei.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return array<int, array{name: string, archived: bool, group: int|null, classes: array<int, array{label: string, withdrawn: bool}>, active: int, withdrawn: int}>
     */
    private function teacherRows(Collection $assignments): array
    {
        return $assignments
            ->groupBy(fn (TeachingAssignment $assignment): string => $assignment->teacher_id.'|'.($assignment->english_group ?? ''))
            ->map(function (Collection $group): array {
                /** @var TeachingAssignment $first */
                $first = $group->first();

                $classes = $group
                    ->sortBy([
                        fn (TeachingAssignment $a, TeachingAssignment $b): int => ($a->schoolClass->grade_level ?? 0) <=> ($b->schoolClass->grade_level ?? 0),
                        fn (TeachingAssignment $a, TeachingAssignment $b): int => strnatcasecmp((string) $a->schoolClass?->name, (string) $b->schoolClass?->name),
                    ])
                    ->map(function (TeachingAssignment $assignment): array {
                        $class = $assignment->schoolClass;

                        return [
                            'label' => $class === null
                                ? (string) __('panel.common.dash')
                                : trim($class->name.' '.($class->section ?? '')).' · '.GradeLevels::roman((int) $class->grade_level),
                            'withdrawn' => $assignment->trashed(),
                        ];
                    })
                    ->values()
                    ->all();

                $withdrawn = count(array_filter($classes, static fn (array $chip): bool => $chip['withdrawn']));

                return [
                    // `->` sub `??` e sigur pe lanțul posibil-null — coalescentul înghite și
                    // proprietatea de pe null (fișă de profesor forțat-ștearsă).
                    'name' => (string) ($first->teacher->full_name ?? __('panel.common.dash')),
                    'archived' => (bool) $first->teacher?->trashed(),
                    'group' => $first->english_group,
                    'classes' => $classes,
                    'active' => count($classes) - $withdrawn,
                    'withdrawn' => $withdrawn,
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']),
                fn (array $a, array $b): int => ($a['group'] ?? 0) <=> ($b['group'] ?? 0),
            ])
            ->values()
            ->all();
    }
}
