<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\ComputeStudentDynamics;
use App\Actions\ComputeTermAverage;
use App\Enums\SchoolCycle;
use App\Http\Controllers\CabinetCatalogController;
use App\Http\Controllers\CabinetController;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\TermAverage;
use App\Support\ContentTranslator;
use App\Support\Grades;
use App\Support\SchoolCalendar;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SURSA UNICĂ a datelor de catalog ale unui elev pentru cabinet: note pe discipline, medii,
 * absențe, motivări, orar publicat și teme. Folosită de DOUĂ suprafețe — fișa elevului
 * ({@see CabinetController::student()}, taburi) și modulele dedicate din
 * meniu ({@see CabinetCatalogController}: Note / Absențe / Orar / Teme) —
 * ca orice schimbare de regulă (anulări, trunchieri, traduceri) să se reflecte AUTOMAT peste tot.
 */
trait BuildsStudentCatalogData
{
    /**
     * Notele active grupate pe disciplină + media semestrială (MS) cu componentele ei.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function gradesBySubject(Student $student): array
    {
        $averages = $this->semesterAverages($student);

        // Notele anulate (§1) nu apar în cabinet.
        $activeGrades = $student->grades->whereNull('annulled_at');

        $subjects = [];
        // Grupare pe subject_id, NU pe nume: legacy-ul are discipline duplicate legitime (același
        // nume, id-uri diferite) — pe nume, notele lor s-ar contopi și MS-ul afișat ar fi al
        // uneia alese arbitrar.
        foreach ($activeGrades->groupBy('subject_id') as $subjectId => $items) {
            $ms = $averages->get((int) $subjectId);
            $subjects[] = [
                'subject' => ContentTranslator::subject((string) $items->first()->subject->name),
                'average' => $ms !== null && $ms->value !== null ? (float) $ms->value : null,
                // Componentele MS, pentru transparență (§1.3): media curentelor + sumativa semestrială.
                'mc' => $ms !== null && $ms->mc_value !== null ? (float) $ms->mc_value : null,
                'summative' => $ms !== null && $ms->summative_value !== null ? (float) $ms->summative_value : null,
                'items' => $items->map(fn (Grade $grade): array => [
                    // Id-ul permite „Contestă această notă" din chip (pre-completarea cererii).
                    'id' => $grade->id,
                    'value' => $grade->value,
                    'calificativ' => $grade->calificativ,
                    'date' => $grade->graded_on->format('d.m.Y'),
                    'term' => $grade->term->number,
                    // Tipul notei cu etichetă pe ciclu (ESS/teză) + dacă e sumativa ponderată — badge distinct.
                    'type' => $grade->evaluation_type->value,
                    'typeLabel' => $grade->evaluation_type->labelForCycle(
                        SchoolCycle::fromGradeLevel((int) $grade->schoolClass->grade_level)
                    ),
                    'isSummative' => $grade->evaluation_type->isWeighted(),
                ])->all(),
            ];
        }

        return $subjects;
    }

    /**
     * Mediile semestriale (cache term_averages) pentru semestrul curent, indexate pe subject_id.
     * Modelele COMPLETE (nu doar valoarea) — ca să putem expune și componentele MC/sumativă.
     *
     * @return Collection<int, TermAverage>
     */
    protected function semesterAverages(Student $student): Collection
    {
        $currentTermId = Term::query()->where('is_current', true)->value('id');

        if ($currentTermId === null) {
            return collect();
        }

        return TermAverage::query()
            ->where('student_id', $student->id)
            ->where('term_id', $currentTermId)
            ->get()
            ->keyBy('subject_id');
    }

    /**
     * Matricea mediilor semestriale ale ANULUI curent (modulul Note › „Medii semestriale"):
     * pe disciplină, MS la fiecare semestru + media generală pe semestru (media aritmetică a
     * MS-urilor, trunchiată la sutimi — regula §2.4, fără rotunjire).
     *
     * @return array{terms: array<int, int>, rows: array<int, array{subject: string, values: array<int, float|null>}>, general: array<int, float|null>}
     */
    protected function semesterAveragesMatrix(Student $student): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first();

        if ($currentTerm === null) {
            return ['terms' => [], 'rows' => [], 'general' => []];
        }

        $terms = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('number')
            ->get();

        $averages = TermAverage::query()
            ->where('student_id', $student->id)
            ->whereIn('term_id', $terms->pluck('id'))
            ->with('subject')
            ->get();

        $termNumberById = $terms->pluck('number', 'id');

        $rows = [];
        foreach ($averages->groupBy('subject_id') as $items) {
            $values = [];
            foreach ($items as $average) {
                $number = (int) $termNumberById->get($average->term_id);
                $values[$number] = $average->value !== null ? (float) $average->value : null;
            }
            $rows[] = [
                'subject' => ContentTranslator::subject((string) $items->first()->subject->name),
                'values' => $values,
            ];
        }
        usort($rows, fn (array $a, array $b): int => strcmp($a['subject'], $b['subject']));

        // Media generală a fiecărui semestru — doar din MS-urile existente (null dacă niciuna).
        $general = [];
        foreach ($terms as $term) {
            $termValues = array_values(array_filter(array_map(
                fn (array $row): ?float => $row['values'][(int) $term->number] ?? null,
                $rows,
            ), fn (?float $value): bool => $value !== null));
            $general[(int) $term->number] = $termValues !== []
                ? Grades::truncate2(array_sum($termValues) / count($termValues))
                : null;
        }

        return [
            'terms' => $terms->pluck('number')->map(fn ($n): int => (int) $n)->all(),
            'rows' => $rows,
            'general' => $general,
        ];
    }

    /**
     * CATALOGUL familiei pentru ANUL școlar curent — sursa modulului „Note".
     *
     * Trei lucruri pe care vederea dinainte nu le făcea, deși avea datele:
     *   • **scopare pe semestru.** Chip-urile din Sem. I și Sem. II stăteau amestecate sub o medie
     *     care descria doar semestrul curent (la un elev real: 58 + 64 de note, o singură medie).
     *     Aici fiecare notă își poartă semestrul, iar sinteza se calculează PER semestru.
     *   • **data notei în payload, nu în tooltip.** Vechiul UI o ascundea într-un `title`, adică
     *     invizibilă pe telefon — exact publicul modulului.
     *   • **sinteza pe server.** Media generală, tendința și disciplinele sub prag sunt reguli de
     *     business (§2.4/§3), nu decorațiuni de UI: se calculează aici, unde sunt testabile.
     *
     * Mediile rămân cele OFICIALE din `term_averages` (calculate de {@see ComputeTermAverage}) —
     * niciodată recalculate din note pe traseul de afișare, ca să nu apară două adevăruri.
     *
     * @return array{
     *     terms: list<array{number: int, label: string, current: bool}>,
     *     currentTerm: int|null,
     *     subjects: list<array<string, mixed>>,
     *     grades: list<array<string, mixed>>,
     *     summary: array<int, array<string, mixed>>,
     * }
     */
    protected function gradeBook(Student $student): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first();

        if ($currentTerm === null) {
            return ['terms' => [], 'currentTerm' => null, 'subjects' => [], 'grades' => [], 'summary' => []];
        }

        $terms = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('number')
            ->get();

        $termIds = $terms->pluck('id');
        /** @var array<int, int> $termNumberById */
        $termNumberById = $terms->pluck('number', 'id')->map(fn ($n): int => (int) $n)->all();

        // Notele ANULUI, nu doar ale semestrului curent: comutarea Sem. I ↔ Sem. II rămâne
        // instantă (client-side), fără încă un tur la server. Volumul e mic (~120 note/elev/an).
        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->whereNull('annulled_at') // §1: nota anulată rămâne în istoric, dar nu în cabinet
            ->whereIn('term_id', $termIds)
            ->with(['subject', 'teacher', 'schoolClass'])
            ->orderByDesc('graded_on')
            ->orderByDesc('id')
            ->get();

        $averages = TermAverage::query()
            ->where('student_id', $student->id)
            ->whereIn('term_id', $termIds)
            ->with('subject')
            ->get();

        /** @var array<int, array<string, mixed>> $subjects indexat pe subject_id */
        $subjects = [];
        // Pe subject_id, NU pe nume: legacy-ul are discipline omonime legitime, iar pe nume
        // notele lor s-ar contopi sub o medie aleasă arbitrar.
        $register = function (int $subjectId, string $name) use (&$subjects): void {
            $subjects[$subjectId] ??= [
                'id' => $subjectId,
                'name' => ContentTranslator::subject($name),
                'terms' => [],
                'teachers' => [],
            ];
        };

        $subjectTeachers = $this->subjectTeachersFor($grades);

        $rows = [];
        foreach ($grades as $grade) {
            $subjectId = (int) $grade->subject_id;
            $register($subjectId, (string) $grade->subject->name);

            $subjectTeacher = $subjectTeachers[$grade->school_class_id.'-'.$subjectId] ?? null;
            if ($subjectTeacher !== null && ! in_array($subjectTeacher, $subjects[$subjectId]['teachers'], true)) {
                $subjects[$subjectId]['teachers'][] = $subjectTeacher;
            }

            $rows[] = [
                'id' => $grade->id,
                'subjectId' => $subjectId,
                'subject' => $subjects[$subjectId]['name'],
                'term' => $termNumberById[$grade->term_id] ?? 0,
                // `label` = ce se AFIȘEAZĂ (nota sau calificativul din primar), `value` = ce se
                // poate calcula. Separate, ca UI-ul să nu reinventeze regula „FB nu are medie".
                'label' => $grade->value !== null ? (string) (float) $grade->value : ($grade->calificativ ?? '—'),
                'value' => $grade->value !== null ? (float) $grade->value : null,
                'date' => $grade->graded_on->format('d.m.Y'),
                'iso' => $grade->graded_on->toDateString(),
                'weekday' => $grade->graded_on->translatedFormat('l'),
                'monthLabel' => ucfirst($grade->graded_on->translatedFormat('F Y')),
                'typeLabel' => $grade->evaluation_type->labelForCycle(
                    SchoolCycle::fromGradeLevel((int) $grade->schoolClass->grade_level)
                ),
                'isSummative' => $grade->evaluation_type->isWeighted(),
                // Cine a CONSEMNAT nota — doar când nota însăși o poartă. Nu se completează din
                // alocare: aceea spune cine predă, nu cine a pus nota asta.
                'teacher' => $grade->teacher?->full_name,
                // Când a intrat nota în sistem — ziua evaluării și ziua consemnării pot diferi,
                // iar familia întreabă exact despre diferența asta. DOAR ziua: ora exactă a
                // tastării nu spune nimic despre situația școlară a copilului.
                'recordedAt' => SchoolCalendar::local($grade->created_at)?->format('d.m.Y'),
            ];
        }

        // Mediile oficiale pe (disciplină, semestru) + componentele MS (§1.3: MC + sumativă).
        foreach ($averages as $average) {
            $subjectId = (int) $average->subject_id;
            $register($subjectId, (string) $average->subject->name);
            $number = $termNumberById[$average->term_id] ?? 0;

            $subjects[$subjectId]['terms'][$number] = [
                'average' => $average->value !== null ? (float) $average->value : null,
                'mc' => $average->mc_value !== null ? (float) $average->mc_value : null,
                'summative' => $average->summative_value !== null ? (float) $average->summative_value : null,
            ];
        }

        foreach ($subjects as $subjectId => $subject) {
            foreach ($terms as $term) {
                $number = (int) $term->number;
                $termGrades = array_values(array_filter(
                    $rows,
                    fn (array $row): bool => $row['subjectId'] === $subjectId && $row['term'] === $number,
                ));

                // Disciplina fără nicio urmă în semestru (nici notă, nici medie) nu apare deloc:
                // altfel Sem. I ar afișa discipline care încep abia în Sem. II.
                if ($termGrades === [] && ! isset($subject['terms'][$number])) {
                    continue;
                }

                // Seria cronologică ASC a notelor NUMERICE — baza sparkline-ului și a tendinței.
                // Calificativele (primar) nu intră: nu sunt cantități.
                $series = array_values(array_filter(array_map(
                    fn (array $row): ?float => $row['value'],
                    array_reverse($termGrades),
                ), fn (?float $value): bool => $value !== null));

                $stats = $subject['terms'][$number] ?? ['average' => null, 'mc' => null, 'summative' => null];
                $stats['count'] = count($termGrades);
                $stats['series'] = $series;
                $stats['trend'] = $this->seriesTrend($series);
                $stats['lastDate'] = $termGrades[0]['date'] ?? null;
                // Sub pragul de promovare (§3) → disciplina intră în riscul de corigență.
                $stats['risk'] = $stats['average'] !== null && $stats['average'] < Grades::PASS;

                $subjects[$subjectId]['terms'][$number] = $stats;
            }
        }

        $subjects = array_values(array_filter($subjects, fn (array $subject): bool => $subject['terms'] !== []));
        usort($subjects, fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $termList = [];
        foreach ($terms as $term) {
            $termList[] = [
                'number' => (int) $term->number,
                // Numele semestrului vine din BAZĂ, scris în RO de școală — trece prin dicționarul
                // de conținut ca și numele disciplinelor, altfel interfața RU/EN afișa „Semestrul I".
                'label' => ContentTranslator::string((string) $term->name),
                'current' => (bool) $term->is_current,
            ];
        }

        return [
            'terms' => $termList,
            'currentTerm' => (int) $currentTerm->number,
            'subjects' => $subjects,
            'grades' => $rows,
            'summary' => $this->gradeBookSummary($subjects, $rows, $terms),
        ];
    }

    /**
     * Profesorul fiecărei perechi (clasă, disciplină), din ALOCĂRI — nu din nota în sine.
     *
     * Motivul: `grades.teacher_id` e populat doar pe notele introduse din panou (importul legacy
     * nu aducea profesorul — 135 din ~52.000 de note). Alocarea răspunde totuși la întrebarea reală
     * a familiei, „cine predă disciplina asta", iar nota își păstrează separat `teacher` = cine a
     * consemnat-o efectiv, acolo unde se știe.
     *
     * Unde alocarea e AMBIGUĂ (grupe de engleză: aceeași pereche clasă+disciplină, profesori
     * diferiți) nu se întoarce nimic: un nume greșit lângă o notă e mai rău decât niciun nume.
     *
     * Cheia hărții e `school_class_id`-`subject_id`.
     *
     * @template TRecord of Grade|Absence
     *
     * @param  Collection<int, TRecord>  $records  rânduri cu (school_class_id, subject_id)
     * @return array<string, string>
     */
    private function subjectTeachersFor(Collection $records): array
    {
        $classIds = $records->pluck('school_class_id')->filter()->unique()->values();

        if ($classIds->isEmpty()) {
            return [];
        }

        $map = [];
        $assignments = TeachingAssignment::query()
            ->whereIn('school_class_id', $classIds)
            ->whereIn('subject_id', $records->pluck('subject_id')->filter()->unique()->values())
            ->with('teacher')
            ->get()
            ->groupBy(fn (TeachingAssignment $a): string => $a->school_class_id.'-'.$a->subject_id);

        foreach ($assignments as $key => $group) {
            $names = $group->map(fn (TeachingAssignment $a): ?string => $a->teacher?->full_name)
                ->filter()
                ->unique()
                ->values();

            if ($names->count() === 1) {
                $map[(string) $key] = (string) $names->first();
            }
        }

        return $map;
    }

    /**
     * Sinteza fiecărui semestru: media generală (media aritmetică a MS-urilor, trunchiată — §2.4),
     * câte note, câte discipline, câte sub prag, ultima notă și tendința față de semestrul anterior.
     *
     * @param  list<array<string, mixed>>  $subjects
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, Term>  $terms
     * @return array<int, array<string, mixed>>
     */
    private function gradeBookSummary(array $subjects, array $rows, Collection $terms): array
    {
        $summary = [];
        $previousAverage = null;

        foreach ($terms as $term) {
            $number = (int) $term->number;

            $values = [];
            $risk = 0;
            $subjectCount = 0;
            foreach ($subjects as $subject) {
                $stats = $subject['terms'][$number] ?? null;
                if ($stats === null) {
                    continue;
                }
                $subjectCount++;
                if ($stats['average'] !== null) {
                    $values[] = (float) $stats['average'];
                }
                if ($stats['risk'] === true) {
                    $risk++;
                }
            }

            $average = $values !== [] ? Grades::truncate2(array_sum($values) / count($values)) : null;
            $termRows = array_values(array_filter($rows, fn (array $row): bool => $row['term'] === $number));

            $summary[$number] = [
                'average' => $average,
                // Tendința semestrului = față de media generală a semestrului ANTERIOR din același
                // an; la primul semestru nu există termen de comparație, deci rămâne null.
                'trend' => $average !== null && $previousAverage !== null
                    ? Grades::trend($previousAverage, $average)
                    : null,
                'previousAverage' => $previousAverage,
                'gradesCount' => count($termRows),
                'subjectsCount' => $subjectCount,
                'riskCount' => $risk,
                'lastDate' => $termRows[0]['date'] ?? null,
            ];

            if ($average !== null) {
                $previousAverage = $average;
            }
        }

        return $summary;
    }

    /**
     * Tendința unei serii de note dintr-un semestru: prima jumătate vs ultima jumătate (nota din
     * mijloc, la număr impar, nu intră în niciuna). Sub 4 note nu se pronunță — două note nu fac
     * o traiectorie, iar o săgeată falsă e mai rea decât absența ei.
     *
     * @param  list<float>  $series
     */
    private function seriesTrend(array $series): ?string
    {
        $count = count($series);

        if ($count < 4) {
            return null;
        }

        $half = intdiv($count, 2);
        $first = array_slice($series, 0, $half);
        $last = array_slice($series, $count - $half);

        return Grades::trend(
            array_sum($first) / count($first),
            array_sum($last) / count($last),
        );
    }

    /**
     * EVOLUȚIA rezultatelor: mediile semestriale ale anului curent (matricea disciplină × semestru)
     * + dinamica multi-an din foaia matricolă ({@see ComputeStudentDynamics}).
     * Cele două împreună acoperă și „cum stă acum", și „de unde vine".
     *
     * @return array{matrix: array<string, mixed>, dynamics: array<string, mixed>}
     */
    protected function gradeEvolution(Student $student): array
    {
        return [
            'matrix' => $this->semesterAveragesMatrix($student),
            'dynamics' => app(ComputeStudentDynamics::class)->for($student),
        ];
    }

    /**
     * Absențele numărate pe disciplină (descrescător).
     *
     * @return array<int, array{subject: string, count: int}>
     */
    protected function absencesBySubject(Student $student): array
    {
        $absences = [];
        // Pe subject_id (nu pe nume) — vezi nota din gradesBySubject despre duplicatele legacy.
        foreach ($student->absences->groupBy('subject_id') as $items) {
            // Absența pe ZI ÎNTREAGĂ nu are disciplină (subject null) — fără gardă, profilul
            // și situația școlară crăpau pe `->name` la primul elev cu o astfel de absență.
            $subjectName = $items->first()->subject?->name;

            $absences[] = [
                'subject' => $subjectName !== null
                    ? ContentTranslator::subject((string) $subjectName)
                    : (string) __('site.cabinet.whole_day_absence'),
                'count' => $items->count(),
            ];
        }
        usort($absences, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $absences;
    }

    /**
     * SITUAȚIA absențelor pentru anul curent — sursa modulului „Absențe".
     *
     * Registrul dinainte era o listă plată: corectă, dar mută la întrebările pe care le pune de fapt
     * un părinte — „câte are în total?", „câte au rămas nemotivate?", „e mai rău decât semestrul
     * trecut?", „la ce lecție a lipsit?". Aici răspunsurile vin gata calculate:
     *   • **axa semestrului**, ca la {@see gradeBook()} — absențele anului sub o singură sinteză ar
     *     amesteca două perioade care se judecă separat;
     *   • **sinteza per semestru**: total, motivate/nemotivate, zile distincte afectate, tendința
     *     față de semestrul anterior, termene care expiră și absențe consolidate;
     *   • **seria lunară**, pentru graficul de evoluție (agregare pe server: e o regulă de citire,
     *     nu o decorațiune de UI);
     *   • **lecția**, derivată din orarul structurat — schema nu are ora absenței, doar ziua.
     *
     * @return array{
     *     terms: list<array{number: int, label: string, current: bool}>,
     *     currentTerm: int|null,
     *     subjects: list<array<string, mixed>>,
     *     absences: list<array<string, mixed>>,
     *     summary: array<int, array<string, mixed>>,
     *     months: array<int, list<array<string, mixed>>>,
     * }
     */
    protected function absenceOverview(Student $student): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first();

        if ($currentTerm === null) {
            return ['terms' => [], 'currentTerm' => null, 'subjects' => [], 'absences' => [], 'summary' => [], 'months' => []];
        }

        $terms = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->orderBy('number')
            ->get();

        /** @var array<int, int> $termNumberById */
        $termNumberById = $terms->pluck('number', 'id')->map(fn ($n): int => (int) $n)->all();

        $absences = Absence::query()
            ->where('student_id', $student->id)
            ->whereIn('term_id', $terms->pluck('id'))
            ->with(['subject', 'teacher'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $wholeDayLabel = (string) __('site.cabinet.whole_day_absence');
        $today = SchoolCalendar::localNow()->startOfDay();
        $subjectTeachers = $this->subjectTeachersFor($absences);
        $lessons = $this->lessonSlotsFor($absences);

        /** @var array<int, array<string, mixed>> $subjects */
        $subjects = [];
        $rows = [];

        foreach ($absences as $absence) {
            // Absența pe zi întreagă n-are disciplină → grup propriu, id 0 (aceeași convenție ca în
            // registrul dinainte, pe care se sprijină filtrarea client-side).
            $subjectId = (int) ($absence->subject_id ?? 0);
            $name = $absence->subject?->name;
            $key = $absence->school_class_id.'-'.$subjectId;

            $subjects[$subjectId] ??= [
                'id' => $subjectId,
                'name' => $name !== null ? ContentTranslator::subject((string) $name) : $wholeDayLabel,
                'teacher' => $subjectTeachers[$key] ?? null,
                'terms' => [],
            ];

            $deadline = $absence->motivation_deadline;
            $recordedBy = $absence->teacher?->full_name;
            $number = $termNumberById[$absence->term_id] ?? 0;
            $locked = ! $absence->is_motivated && $absence->motivation_locked_at !== null;
            $deadlinePassed = ! $absence->is_motivated && $deadline !== null && $deadline->lt($today);

            $rows[] = [
                'id' => $absence->id,
                'term' => $number,
                'subjectId' => $subjectId,
                'subject' => $subjects[$subjectId]['name'],
                'iso' => $absence->occurred_on->toDateString(),
                'date' => $absence->occurred_on->format('d.m.Y'),
                'weekday' => $absence->occurred_on->translatedFormat('l'),
                'monthKey' => $absence->occurred_on->format('Y-m'),
                'monthLabel' => ucfirst($absence->occurred_on->translatedFormat('F Y')),
                // Profesorul disciplinei vine din alocări; `absences.teacher_id` e populat pe 79 din
                // ~14.000 de rânduri (importul legacy nu-l aducea), deci ca sursă unică ar fi tăcut
                // aproape peste tot. Vezi {@see subjectTeachersFor()}.
                'teacher' => $recordedBy ?? $subjectTeachers[$key] ?? null,
                // Lecția: schema stochează doar ZIUA, deci numărul se deduce din orar. null unde
                // disciplina apare de mai multe ori în acea zi — vezi {@see lessonSlotsFor()}.
                'lesson' => $lessons[$key.'-'.$absence->occurred_on->dayOfWeekIso] ?? null,
                'motivated' => $absence->is_motivated,
                'recordedAt' => SchoolCalendar::local($absence->created_at)?->format('d.m.Y, H:i'),
                // Termenul contează DOAR cât absența e nemotivată; după aceea e istorie.
                'deadline' => ! $absence->is_motivated && $deadline !== null ? $deadline->format('d.m.Y') : null,
                'deadlinePassed' => $deadlinePassed,
                // Zile rămase până la expirarea dreptului de a cere motivare — semnalul acționabil.
                'deadlineDays' => ! $absence->is_motivated && $deadline !== null && ! $deadlinePassed
                    ? $today->diffInDays($deadline)
                    : null,
                'locked' => $locked,
            ];
        }

        // Contoarele pe (disciplină, semestru) — baza pastilelor de filtrare și a cardurilor.
        foreach ($rows as $row) {
            $stats = $subjects[$row['subjectId']]['terms'][$row['term']] ?? ['total' => 0, 'motivated' => 0, 'unmotivated' => 0];
            $stats['total']++;
            $row['motivated'] ? $stats['motivated']++ : $stats['unmotivated']++;
            $subjects[$row['subjectId']]['terms'][$row['term']] = $stats;
        }

        $subjects = array_values($subjects);
        usort($subjects, fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $termList = [];
        foreach ($terms as $term) {
            $termList[] = [
                'number' => (int) $term->number,
                'label' => ContentTranslator::string((string) $term->name),
                'current' => (bool) $term->is_current,
            ];
        }

        return [
            'terms' => $termList,
            'currentTerm' => (int) $currentTerm->number,
            'subjects' => $subjects,
            'absences' => $rows,
            'summary' => $this->absenceSummary($rows, $terms),
            'months' => $this->absenceMonths($rows, $terms),
        ];
    }

    /**
     * Sinteza fiecărui semestru: totalul, împărțirea motivate/nemotivate, câte ZILE distincte a
     * lipsit (o zi cu 5 lecții pierdute e o zi, nu cinci absențe separate — asta întreabă părintele),
     * disciplina cea mai afectată, termenele care expiră și tendința față de semestrul anterior.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, Term>  $terms
     * @return array<int, array<string, mixed>>
     */
    private function absenceSummary(array $rows, Collection $terms): array
    {
        $summary = [];
        $previousTotal = null;

        foreach ($terms as $term) {
            $number = (int) $term->number;
            $termRows = array_values(array_filter($rows, fn (array $row): bool => $row['term'] === $number));

            $motivated = count(array_filter($termRows, fn (array $row): bool => $row['motivated'] === true));
            $total = count($termRows);
            $unmotivated = $total - $motivated;

            /** @var array<int, int> $perSubject */
            $perSubject = [];
            foreach ($termRows as $row) {
                $perSubject[$row['subject']] = ($perSubject[$row['subject']] ?? 0) + 1;
            }
            arsort($perSubject);
            $worstName = array_key_first($perSubject);

            $summary[$number] = [
                'total' => $total,
                'motivated' => $motivated,
                'unmotivated' => $unmotivated,
                // Procentul motivat — rostul barei de proporție din sinteză.
                'motivatedRate' => $total > 0 ? (int) round($motivated / $total * 100) : null,
                'days' => count(array_unique(array_column($termRows, 'iso'))),
                'subjectsCount' => count($perSubject),
                'worstSubject' => $worstName !== null ? ['name' => $worstName, 'count' => $perSubject[$worstName]] : null,
                'lastDate' => $termRows[0]['date'] ?? null,
                // Nemotivate al căror termen expiră în cel mult o săptămână: singurul lucru din
                // modul pe care familia îl poate încă REZOLVA.
                'expiringSoon' => count(array_filter(
                    $termRows,
                    fn (array $row): bool => $row['deadlineDays'] !== null && $row['deadlineDays'] <= 7,
                )),
                // Consolidate: termenul a trecut sau dirigintele a închis motivarea — rămân definitive.
                'locked' => count(array_filter(
                    $termRows,
                    fn (array $row): bool => $row['locked'] === true || $row['deadlinePassed'] === true,
                )),
                'previousTotal' => $previousTotal,
                // La absențe „mai multe" e mai rău, deci tendința se inversează față de medii:
                // creșterea numărului = `down`, ca UI-ul să folosească aceeași convenție de culoare.
                'trend' => $previousTotal !== null && $total !== $previousTotal
                    ? ($total > $previousTotal ? 'down' : 'up')
                    : ($previousTotal !== null ? 'stable' : null),
            ];

            $previousTotal = $total;
        }

        return $summary;
    }

    /**
     * Absențele grupate pe LUNĂ, în ordine cronologică — seria graficului de evoluție. Lunile fără
     * absențe din interiorul semestrului sunt incluse cu zero: fără ele, graficul ar comprima o
     * pauză de două luni într-un singur pas și ar minți despre ritm.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<int, Term>  $terms
     * @return array<int, list<array<string, mixed>>>
     */
    private function absenceMonths(array $rows, Collection $terms): array
    {
        $months = [];

        foreach ($terms as $term) {
            $number = (int) $term->number;
            $termRows = array_filter($rows, fn (array $row): bool => $row['term'] === $number);

            /** @var array<string, array{key: string, label: string, total: int, motivated: int, unmotivated: int}> $buckets */
            $buckets = [];
            foreach ($termRows as $row) {
                $buckets[$row['monthKey']] ??= [
                    'key' => $row['monthKey'],
                    'label' => ucfirst(Carbon::parse($row['iso'])->translatedFormat('M')),
                    'total' => 0,
                    'motivated' => 0,
                    'unmotivated' => 0,
                ];
                $buckets[$row['monthKey']]['total']++;
                $row['motivated'] ? $buckets[$row['monthKey']]['motivated']++ : $buckets[$row['monthKey']]['unmotivated']++;
            }

            if ($buckets !== []) {
                // Completăm golurile dintre prima și ultima lună cu absențe.
                $keys = array_keys($buckets);
                sort($keys);
                $cursor = Carbon::createFromFormat('Y-m', $keys[0])->startOfMonth();
                $last = Carbon::createFromFormat('Y-m', $keys[count($keys) - 1])->startOfMonth();

                while ($cursor->lte($last)) {
                    $key = $cursor->format('Y-m');
                    $buckets[$key] ??= [
                        'key' => $key,
                        'label' => ucfirst($cursor->translatedFormat('M')),
                        'total' => 0,
                        'motivated' => 0,
                        'unmotivated' => 0,
                    ];
                    $cursor->addMonth();
                }
                ksort($buckets);
            }

            $months[$number] = array_values($buckets);
        }

        return $months;
    }

    /**
     * Lecția fiecărei perechi (clasă, disciplină, zi a săptămânii), din orarul STRUCTURAT.
     *
     * Schema absențelor stochează doar ZIUA (`occurred_on` e `date`), nu ora — numărul lecției nu
     * poate veni de pe rând. Orarul îl dă însă fără ambiguitate în 87% din cazuri; unde aceeași
     * disciplină apare de două ori în aceeași zi, nu se poate ști la care lecție a lipsit, deci nu
     * se întoarce nimic. Un „Lecția 3" greșit ar fi mai rău decât o casetă goală.
     *
     * @param  Collection<int, Absence>  $absences
     * @return array<string, array{number: int, room: string|null}>
     */
    private function lessonSlotsFor(Collection $absences): array
    {
        $classIds = $absences->pluck('school_class_id')->filter()->unique()->values();

        if ($classIds->isEmpty()) {
            return [];
        }

        $slots = Lesson::query()
            ->whereIn('school_class_id', $classIds)
            ->whereIn('subject_id', $absences->pluck('subject_id')->filter()->unique()->values())
            ->get()
            ->groupBy(fn (Lesson $lesson): string => $lesson->school_class_id.'-'.$lesson->subject_id.'-'.$lesson->day_of_week->value);

        $map = [];
        foreach ($slots as $key => $group) {
            if ($group->count() === 1) {
                $lesson = $group->first();
                $map[(string) $key] = [
                    'number' => (int) $lesson->lesson_number,
                    'room' => $lesson->room,
                ];
            }
        }

        return $map;
    }

    /**
     * Fereastra de depunere a unei motivări: de la începutul anului școlar CURENT până azi
     * (se motivează absențe deja petrecute — regula there din {@see CabinetController::requestMotivation}).
     * null când nu există an activ (vacanța dintre ani, mediu gol) — atunci rămâne doar limita „azi".
     *
     * @return array{min: string, max: string}|null
     */
    protected function motivationWindow(): ?array
    {
        $current = Term::query()->where('is_current', true)->first();

        if ($current === null) {
            return null;
        }

        $yearStart = Term::query()
            ->where('academic_year_id', $current->academic_year_id)
            ->min('starts_on');

        if ($yearStart === null) {
            return null;
        }

        return [
            'min' => Carbon::parse((string) $yearStart)->toDateString(),
            'max' => SchoolCalendar::localNow()->toDateString(),
        ];
    }

    /**
     * Cererile de motivare ale elevului, cu tot ce-i trebuie familiei ca să înțeleagă UNDE e
     * cererea: cine a depus-o și când, termenul de validare al dirigintelui, cine a decis și
     * când, nota deciziei, justificativul și IMPACTUL (câte absențe acoperă perioada).
     * Cronologia din fișa cererii se derivă din aceste câmpuri — aceleași date pe care le vede
     * și administrația (sursa: modelul, nu o copie).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function motivations(Student $student): array
    {
        return AbsenceMotivation::query()
            ->where('student_id', $student->id)
            ->with(['requestedBy', 'reviewedBy'])
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (AbsenceMotivation $motivation): array {
                $deadline = $motivation->isPending() ? $motivation->validationDeadline() : null;

                return [
                    'id' => $motivation->id,
                    'reason' => $motivation->reason,
                    'period' => $motivation->period_start->format('d.m.Y').' – '.$motivation->period_end->format('d.m.Y'),
                    'status' => $motivation->status->value,
                    'statusLabel' => $motivation->status->label(),
                    'isException' => $motivation->is_exception,
                    'submittedAt' => SchoolCalendar::local($motivation->created_at)?->format('d.m.Y, H:i'),
                    'submittedBy' => $motivation->requestedBy?->name,
                    // Termenul de validare (depunere + 2 zile lucrătoare) — doar cât cererea e
                    // în așteptare; după decizie devine irelevant.
                    'reviewDeadline' => $deadline?->format('d.m.Y'),
                    'reviewOverdue' => $motivation->isOverdue(),
                    'decidedAt' => SchoolCalendar::local($motivation->reviewed_at)?->format('d.m.Y, H:i'),
                    'decidedBy' => $motivation->reviewedBy?->name,
                    // Nota validatorului (obligatorie la respingere) — familia vede DE CE,
                    // nu doar un badge „Respinsă" (§4 transparență).
                    'note' => $motivation->review_note,
                    'documentUrl' => $motivation->document_path !== null
                        ? route('cabinet.motivation.document', ['absenceMotivation' => $motivation->id], false)
                        : null,
                    // Impactul perioadei — aceeași sursă ca efectul aprobării (absencesInPeriod).
                    'absencesTotal' => $motivation->absencesInPeriod()->count(),
                    'absencesUnmotivated' => $motivation->absencesInPeriod()->where('is_motivated', false)->count(),
                ];
            })
            ->all();
    }

    /**
     * Orarul săptămânal NORMALIZAT al clasei (sloturi + celule cu segmente structurate) — sursa
     * publicată preferată, structuratul ca fallback. Detalii: {@see WeeklySchedule}.
     *
     * @return array<string, mixed>|null
     */
    protected function weeklySchedule(?SchoolClass $class): ?array
    {
        return $class !== null ? app(WeeklySchedule::class)->forClass($class) : null;
    }

    /**
     * Temele clasei curente a elevului: cele „de făcut" (azi/viitor) cronologic + istoricul recent.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function homeworkForStudent(Student $student): array
    {
        // Clasa curentă canonică (academic_year_id) — altfel temele veneau din clasa istorică lângă
        // orarul clasei curente în același tab „Orar & teme" (#37).
        $class = $student->currentSchoolClass();

        if (! $class) {
            return [];
        }

        // TIMPUL e axa (2026-07-18): temele „de făcut" (data efectivă azi/viitor) vin TOATE,
        // cronologic ASC — elevul vede întâi ce urmează; istoricul recent (DESC) vine separat,
        // limitat, și e pliat în UI. Data efectivă = termen ?? atribuire (legacy fără termen).
        $base = fn (): Builder => HomeworkAssignment::query()
            ->where('grade_level', $class->grade_level)
            ->where(function (Builder $query) use ($class): void {
                $query->where('section', $class->section)->orWhereNull('section');
            });

        $expression = HomeworkAssignment::effectiveOnExpression();
        // Ziua ȘCOLII, nu UTC (regula de fus a proiectului): `today()` e cu 2–3 ore în urmă, deci
        // între 00:00 și 03:00 ora Chișinăului temele DE AZI cădeau în „viitor", iar fereastra
        // „De predat în această zi" apărea goală. Prins pe date demo, 2026-07-23.
        $today = SchoolCalendar::localNow()->toDateString();

        $upcoming = $base()
            ->where($expression, '>=', $today)
            ->orderBy($expression)
            ->get();
        $past = $base()
            ->where($expression, '<', $today)
            ->orderByDesc($expression)
            ->limit(20)
            ->get();

        return $upcoming->concat($past)
            ->map(fn (HomeworkAssignment $homework): array => $this->homeworkRow($homework, $today))
            ->all();
    }

    /**
     * TOT setul de teme al clasei pentru anul curent — sursa modulului dedicat „Teme".
     *
     * Spre deosebire de {@see homeworkForStudent} (viitorul + ultimele 20 din trecut, potrivit
     * pentru „Ziua mea"/fișă), aici vin TOATE temele anului: filtrul de calendar trebuie să poată
     * naviga la orice zi trecută, nu doar la ultimele 20 — altfel „verifică temele din 12 martie"
     * ar întoarce gol chiar dacă tema există. Volumul e mic per clasă (~230 teme/an), deci se
     * încarcă o dată și se filtrează client-side, ca la {@see gradeBook}/{@see absenceOverview}.
     *
     * Mărginit inferior la începutul anului școlar: temele nu au `academic_year_id` (se leagă pe
     * grade_level+section), iar aceeași clasă „VII 1" există în fiecare an — fără limită am amesteca
     * anii.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function classHomework(Student $student): array
    {
        $class = $student->currentSchoolClass();

        if (! $class) {
            return [];
        }

        $expression = HomeworkAssignment::effectiveOnExpression();
        $today = SchoolCalendar::localNow()->toDateString();
        $yearStart = $this->currentAcademicYearStart();

        $base = fn (): Builder => HomeworkAssignment::query()
            ->where('grade_level', $class->grade_level)
            ->where(function (Builder $query) use ($class): void {
                $query->where('section', $class->section)->orWhereNull('section');
            })
            ->when($yearStart !== null, fn (Builder $query) => $query->where($expression, '>=', $yearStart));

        // Aceeași ordine ca vederea implicită: viitorul cronologic ASC, apoi trecutul DESC (fără
        // limită). Filtrul de calendar re-taie oricum client-side pe fereastra aleasă.
        $upcoming = $base()->where($expression, '>=', $today)->orderBy($expression)->get();
        $past = $base()->where($expression, '<', $today)->orderByDesc($expression)->get();

        return $upcoming->concat($past)
            ->map(fn (HomeworkAssignment $homework): array => $this->homeworkRow($homework, $today))
            ->all();
    }

    /**
     * Începutul anului școlar CURENT (Y-m-d), sau null în vacanța dintre ani. Sursă unică pentru
     * mărginirile pe an (fereastra de motivare, setul de teme).
     */
    private function currentAcademicYearStart(): ?string
    {
        $current = Term::query()->where('is_current', true)->first();

        if ($current === null) {
            return null;
        }

        $yearStart = Term::query()->where('academic_year_id', $current->academic_year_id)->min('starts_on');

        return $yearStart !== null ? Carbon::parse((string) $yearStart)->toDateString() : null;
    }

    /**
     * O temă, în forma pe care o consumă cabinetul (data efectivă = termen ?? atribuire, statut pe
     * ziua ȘCOLII, disciplină/autor traduse). Sursă unică pentru vederea windowed și cea completă.
     *
     * @return array<string, mixed>
     */
    private function homeworkRow(HomeworkAssignment $homework, string $today): array
    {
        $effective = $homework->effectiveOn();
        $effectiveDate = $effective->toDateString();

        return [
            'id' => $homework->id,
            'date' => $homework->assigned_on->format('d.m.Y'),
            'due' => $homework->due_on?->format('d.m.Y'),
            // Cheia de GRUPARE pe zile (stabilă, sortabilă) + eticheta zilei tradusă în limba
            // interfeței (serverul cunoaște locale-ul; frontend-ul n-are formatter).
            'effectiveDate' => $effectiveDate,
            'dayLabel' => ucfirst($effective->translatedFormat('l, j F')),
            // Comparație pe ZIUA ȘCOLII (`$today`), nu `isToday()/isFuture()` — acelea se raportează
            // la UTC și mutau temele de azi în „viitor" noaptea.
            'status' => match (true) {
                $effectiveDate === $today => 'today',
                $effectiveDate > $today => 'upcoming',
                default => 'past',
            },
            'subject' => ContentTranslator::subject((string) $homework->subject_name),
            // Cine a dat tema — author_name e snapshot-ul textual de la creare (rămâne valabil și
            // dacă fișa profesorului dispare), aceeași logică ca subject_name.
            'teacher' => $homework->author_name,
            'topic' => $homework->topic,
            'required' => $homework->required_task,
            'optional' => $homework->optional_task,
            'links' => $homework->links ?? [],
        ];
    }
}
