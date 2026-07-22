<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SchoolCycle;
use App\Http\Controllers\CabinetCatalogController;
use App\Http\Controllers\CabinetController;
use App\Models\Absence;
use App\Models\AbsenceMotivation;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
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
     * REGISTRUL absențelor: fiecare absență cu contextul ei complet (dată + zi, disciplină,
     * profesorul care a consemnat-o, statut, termenul de motivare / consolidarea) + contoarele
     * pe discipline pentru filtrarea client-side. Toate absențele elevului dintr-o singură
     * încărcare (volum mic per elev) → comutarea între discipline e INSTANT, fără alt request.
     *
     * @return array{
     *     subjects: array<int, array{id: int, name: string, total: int, unmotivated: int}>,
     *     absences: array<int, array<string, mixed>>,
     *     motivated: int,
     *     unmotivated: int,
     * }
     */
    protected function absenceRegister(Student $student): array
    {
        $absences = $student->absences()
            ->with(['subject', 'teacher'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $wholeDayLabel = (string) __('site.cabinet.whole_day_absence');
        $today = SchoolCalendar::localNow()->startOfDay();

        $rows = $absences->map(function (Absence $absence) use ($wholeDayLabel, $today): array {
            $deadline = $absence->motivation_deadline;

            return [
                'id' => $absence->id,
                'date' => $absence->occurred_on->format('d.m.Y'),
                // Ziua săptămânii în limba interfeței (SetUserLocale) — contextul „luni/vineri"
                // contează pentru părinte mai mult decât cifra seacă.
                'weekday' => $absence->occurred_on->translatedFormat('l'),
                // Absența pe zi întreagă nu are disciplină → grup propriu, id 0.
                'subjectId' => (int) ($absence->subject_id ?? 0),
                'subject' => $absence->subject?->name !== null
                    ? ContentTranslator::subject((string) $absence->subject->name)
                    : $wholeDayLabel,
                'teacher' => $absence->teacher?->full_name,
                'motivated' => $absence->is_motivated,
                // Momentul consemnării în sistem (ora absenței în sine nu se stochează — schema
                // ține doar ZIUA) — pe ora școlii, nu UTC.
                'recordedAt' => SchoolCalendar::local($absence->created_at)?->format('d.m.Y, H:i'),
                // Contextul termenului contează DOAR cât absența e nemotivată.
                'deadline' => ! $absence->is_motivated && $deadline !== null ? $deadline->format('d.m.Y') : null,
                'deadlinePassed' => ! $absence->is_motivated && $deadline !== null && $deadline->lt($today),
                'locked' => ! $absence->is_motivated && $absence->motivation_locked_at !== null,
            ];
        })->values()->all();

        $subjects = [];
        foreach ($absences->groupBy(fn (Absence $absence): int => (int) ($absence->subject_id ?? 0)) as $subjectId => $items) {
            $name = $items->first()->subject?->name;
            $subjects[] = [
                'id' => (int) $subjectId,
                'name' => $name !== null ? ContentTranslator::subject((string) $name) : $wholeDayLabel,
                'total' => $items->count(),
                'unmotivated' => $items->where('is_motivated', false)->count(),
            ];
        }
        usort($subjects, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'subjects' => $subjects,
            'absences' => $rows,
            'motivated' => $absences->where('is_motivated', true)->count(),
            'unmotivated' => $absences->where('is_motivated', false)->count(),
        ];
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
        $today = today()->toDateString();

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
            ->map(function (HomeworkAssignment $homework): array {
                $effective = $homework->effectiveOn();

                return [
                    'id' => $homework->id,
                    'date' => $homework->assigned_on->format('d.m.Y'),
                    'due' => $homework->due_on?->format('d.m.Y'),
                    // Cheia de GRUPARE pe zile (stabilă, sortabilă) + eticheta zilei tradusă în
                    // limba interfeței (serverul cunoaște locale-ul; frontend-ul n-are formatter).
                    'effectiveDate' => $effective->toDateString(),
                    'dayLabel' => ucfirst($effective->translatedFormat('l, j F')),
                    'status' => match (true) {
                        $effective->isToday() => 'today',
                        $effective->isFuture() => 'upcoming',
                        default => 'past',
                    },
                    'subject' => ContentTranslator::subject((string) $homework->subject_name),
                    'topic' => $homework->topic,
                    'required' => $homework->required_task,
                    'optional' => $homework->optional_task,
                    'links' => $homework->links ?? [],
                ];
            })
            ->all();
    }
}
