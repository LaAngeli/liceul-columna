<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\SchoolCycle;
use App\Http\Controllers\CabinetCatalogController;
use App\Models\Absence;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Term;
use App\Support\ContentTranslator;

/**
 * Cronologia activității elevului: note + absențe ÎMPREUNĂ, în ordinea producerii — părintele
 * urmărește evoluția copilului calendaristic, nu pe tipuri de informație. Modulele „Note" și
 * „Absențe" rămân destinațiile de adâncime (medii, motivări, termene); aici e FIRUL zilelor.
 *
 * Concern SEPARAT de {@see BuildsStudentCatalogData} (deliberat, nu accidental) — dar proiectat
 * să compună aceleași reguli: aceleași interogări scoped pe anul curent, aceeași excludere a
 * notelor anulate (§1), aceiași derivați (profesorul din alocări, lecția din orar) prin helperele
 * private ale trait-ului frate, apelabile pentru că ambele trait-uri se aplatizează în
 * {@see CabinetCatalogController}. Cerință: clasa consumatoare TREBUIE să
 * folosească și BuildsStudentCatalogData.
 */
trait BuildsActivityTimeline
{
    /**
     * Notele și absențele anului școlar curent, fuzionate într-o singură listă cronologică
     * DESCRESCĂTOARE (ce s-a întâmplat azi stă primul). Rândurile poartă aceleași chei de
     * calendar ca registrele existente (iso/date/weekday/monthKey/monthLabel), deci gruparea
     * pe zile + separatoarele de lună se fac liniar pe client, fără alt tur la server.
     *
     * În interiorul unei zile: întâi NOTELE (informația cea mai căutată), apoi absențele în
     * ordinea lecțiilor din orar — ordine deterministă, nu întâmplarea inserării în bază.
     *
     * FĂRĂ împărțire pe semestre (cerința beneficiarului): un fir calendaristic continuu peste
     * tot anul. Semestrul rămâne axa modulelor Note și Absențe, unde contează pentru medii.
     *
     * @return array{entries: list<array<string, mixed>>}
     */
    protected function activityTimeline(Student $student): array
    {
        $currentTerm = Term::query()->where('is_current', true)->first();

        if ($currentTerm === null) {
            return ['entries' => []];
        }

        // Semestrele anului curent — folosite DOAR ca perimetru al interogărilor (anul școlar
        // în curs), nu ca filtru pentru utilizator.
        $termIds = Term::query()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->pluck('id');

        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->whereNull('annulled_at') // §1: nota anulată rămâne în istoric, dar nu în cabinet
            ->whereIn('term_id', $termIds)
            ->with(['subject', 'teacher', 'schoolClass'])
            ->orderByDesc('graded_on')
            ->orderByDesc('id')
            ->get();

        $absences = Absence::query()
            ->active()
            ->where('student_id', $student->id)
            ->whereIn('term_id', $termIds)
            ->with(['subject', 'teacher'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        // Derivațiile împrumutate din registrul de absențe / catalogul de note (helpere private
        // ale trait-ului frate — aceeași clasă le compune pe ambele): profesorul perechii
        // (clasă, disciplină) din alocări, numărul lecției din orarul structurat.
        $gradeTeachers = $this->subjectTeachersFor($grades);
        $absenceTeachers = $this->subjectTeachersFor($absences);
        $lessons = $this->lessonSlotsFor($absences);
        $wholeDayLabel = (string) __('site.cabinet.whole_day_absence');

        $entries = [];

        foreach ($grades as $grade) {
            $subjectKey = $grade->school_class_id.'-'.$grade->subject_id;
            $recordedBy = $grade->teacher?->full_name;

            $entries[] = [
                // Cheie unică peste AMBELE surse (id-urile de notă și absență se pot suprapune).
                'key' => 'g-'.$grade->id,
                'kind' => 'grade',
                'iso' => $grade->graded_on->toDateString(),
                'date' => $grade->graded_on->format('d.m.Y'),
                'weekday' => $grade->graded_on->translatedFormat('l'),
                'monthKey' => $grade->graded_on->format('Y-m'),
                'monthLabel' => ucfirst($grade->graded_on->translatedFormat('F Y')),
                'subject' => ContentTranslator::subject((string) $grade->subject->name),
                // `label` = ce se AFIȘEAZĂ (nota sau calificativul din primar), `value` = ce se
                // poate calcula — aceeași separație ca în catalogul de note.
                'label' => $grade->value !== null ? (string) (float) $grade->value : ($grade->calificativ ?? '—'),
                'value' => $grade->value !== null ? (float) $grade->value : null,
                'typeLabel' => $grade->evaluation_type->labelForCycle(
                    SchoolCycle::fromGradeLevel((int) $grade->schoolClass->grade_level)
                ),
                'isSummative' => $grade->evaluation_type->isWeighted(),
                // Cine a consemnat nota (unde se știe), altfel cine predă disciplina — feed-ul e
                // compact, un singur nume; despărțirea consemnat/predă trăiește în modulul Note.
                'teacher' => $recordedBy ?? $gradeTeachers[$subjectKey] ?? null,
                'lesson' => null,
                'motivated' => null,
            ];
        }

        foreach ($absences as $absence) {
            $subjectName = $absence->subject?->name;
            $subjectKey = $absence->school_class_id.'-'.(int) ($absence->subject_id ?? 0);
            $recordedBy = $absence->teacher?->full_name;

            $entries[] = [
                'key' => 'a-'.$absence->id,
                'kind' => 'absence',
                'iso' => $absence->occurred_on->toDateString(),
                'date' => $absence->occurred_on->format('d.m.Y'),
                'weekday' => $absence->occurred_on->translatedFormat('l'),
                'monthKey' => $absence->occurred_on->format('Y-m'),
                'monthLabel' => ucfirst($absence->occurred_on->translatedFormat('F Y')),
                // Absența pe zi întreagă n-are disciplină → aceeași etichetă ca registrul.
                'subject' => $subjectName !== null ? ContentTranslator::subject((string) $subjectName) : $wholeDayLabel,
                'label' => null,
                'value' => null,
                'typeLabel' => null,
                'isSummative' => false,
                'teacher' => $recordedBy ?? $absenceTeachers[$subjectKey] ?? null,
                'lesson' => $lessons[$subjectKey.'-'.$absence->occurred_on->dayOfWeekIso] ?? null,
                'motivated' => $absence->is_motivated,
            ];
        }

        usort($entries, function (array $a, array $b): int {
            $byDay = strcmp((string) $b['iso'], (string) $a['iso']);
            if ($byDay !== 0) {
                return $byDay;
            }

            if ($a['kind'] !== $b['kind']) {
                return $a['kind'] === 'grade' ? -1 : 1;
            }

            if ($a['kind'] === 'absence') {
                $lessonA = is_array($a['lesson']) ? (int) $a['lesson']['number'] : 99;
                $lessonB = is_array($b['lesson']) ? (int) $b['lesson']['number'] : 99;
                if ($lessonA !== $lessonB) {
                    return $lessonA <=> $lessonB;
                }
            }

            return strcmp((string) $a['subject'], (string) $b['subject']);
        });

        return ['entries' => $entries];
    }
}
