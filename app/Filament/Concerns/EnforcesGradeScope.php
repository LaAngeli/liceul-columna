<?php

namespace App\Filament\Concerns;

use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Term;
use App\Support\SchoolCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Impune pe SERVER scope-ul profesorului la salvarea unei note — opțiunile scoped din
 * formular sunt doar pentru UX; aici e protecția reală (împotriva POST-urilor manipulate).
 * Semestrul se DERIVĂ din data notei (nu se alege manual), la fel ca la absențe.
 */
trait EnforcesGradeScope
{
    use RejectsClosedYearWrites;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceGradeScope(array $data, ?int $ignoreId = null): array
    {
        $gradedOn = isset($data['graded_on']) && $data['graded_on'] !== ''
            ? Carbon::parse((string) $data['graded_on'])
            : null;

        if ($gradedOn !== null) {
            // O notă nu poate fi în viitor — elevul nu a fost încă evaluat în acea zi (audit Î-3).
            // Serverul e protecția reală (POST manipulat); maxDate din formular e doar UX. Aceeași
            // gardă ca la absențe (EnforcesAbsenceScope).
            if ($gradedOn->startOfDay()->isAfter(Carbon::today())) {
                throw ValidationException::withMessages([
                    'data.graded_on' => __('panel.validation.grade.future'),
                ]);
            }

            // Semestrul aparține datei notei; fallback la semestrul curent în afara intervalelor
            // (o notă dintr-o vacanță trecută aparține legitim semestrului în curs).
            $term = Term::forDate($gradedOn);

            if ($term instanceof Term) {
                $data['term_id'] = $term->id;
            } else {
                $current = SchoolCalendar::currentTerm();

                // GARD DE ROLLOVER: dacă data e ULTERIOARĂ sfârșitului anului din care face parte
                // semestrul curent, înseamnă că a început anul nou fără ca structura lui să fie
                // definită. Fallback-ul tăcut ar fi pus nota de septembrie în semestrul anului
                // ÎNCHEIAT — o eroare invizibilă, care se descoperă la calculul mediilor. Mai bine
                // un mesaj clar. (Refuzăm doar depășirea în VIITOR, nu orice dată din afara anului:
                // vacanța dinaintea semestrului curent rămâne acoperită de fallback.)
                $yearEndsOn = $current?->academicYear?->ends_on;

                if ($current === null || ($yearEndsOn !== null && $gradedOn->startOfDay()->isAfter($yearEndsOn))) {
                    throw ValidationException::withMessages([
                        'data.graded_on' => __('panel.validation.grade.no_term_for_date'),
                    ]);
                }

                $data['term_id'] = $current->id;
            }
        }

        $this->rejectClosedYear($data['term_id'] ?? null, 'data.graded_on');

        // ORA lecției (opțională; '' din formular = null). Când e precizată, slotul
        // (elev, zi, disciplină, oră) devine EXCLUSIV — regulă de consistență, nu de scope, deci
        // se aplică ORICUI scrie, inclusiv administrației (care sare peste secțiunea de mai jos).
        $lessonNumber = isset($data['lesson_number']) && $data['lesson_number'] !== ''
            ? (int) $data['lesson_number']
            : null;
        $data['lesson_number'] = $lessonNumber;

        if ($gradedOn !== null && $lessonNumber !== null && isset($data['student_id'])) {
            $studentId = (int) $data['student_id'];
            $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;

            $slot = fn (Builder $q): Builder => $q
                ->where('student_id', $studentId)
                ->where('lesson_number', $lessonNumber)
                ->when(
                    $subjectId !== null,
                    fn (Builder $q): Builder => $q->where('subject_id', $subjectId),
                    fn (Builder $q): Builder => $q->whereNull('subject_id'),
                );

            // O oră poartă O SINGURĂ notă activă: a doua evaluare a zilei se pune pe altă oră
            // (cerința 06.08.2026 — „selectează explicit o altă oră disponibilă"). Anulatele NU
            // blochează: anularea eliberează slotul.
            $gradeTaken = Grade::query()
                ->tap($slot)
                ->whereNull('annulled_at')
                ->whereDate('graded_on', $gradedOn->toDateString())
                ->when($ignoreId !== null, fn (Builder $q): Builder => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($gradeTaken) {
                throw ValidationException::withMessages([
                    'data.lesson_number' => __('panel.validation.grade.hour_has_grade', ['number' => $lessonNumber]),
                ]);
            }

            // EXCLUDEREA RECIPROCĂ notă↔absență pe aceeași oră: elevul ori a fost în bancă și a
            // răspuns, ori a lipsit — amândouă deodată e o neatenție de consemnare, nu o realitate.
            $absent = Absence::query()
                ->tap($slot)
                ->whereDate('occurred_on', $gradedOn->toDateString())
                ->exists();

            if ($absent) {
                throw ValidationException::withMessages([
                    'data.lesson_number' => __('panel.validation.grade.hour_has_absence', ['number' => $lessonNumber]),
                ]);
            }
        }

        $user = auth('web')->user();

        // Autoritatea academică (super-admin / director / prim-vicedirector) nu e limitată.
        // Administratorul operațional/tehnic NU scrie note → cade pe ramura „fără fișă" și e blocat.
        if (! $user || $user->canAdministerCatalog()) {
            return $data;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'data.student_id' => __('panel.validation.scope.no_teacher_profile'),
            ]);
        }

        // Autorul notei = profesorul logat (nu de încredere din formular).
        $data['teacher_id'] = $teacher->id;

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);

        if (! $teacher->canGradeClassSubject($classId, $subjectId)) {
            throw ValidationException::withMessages([
                'data.subject_id' => __('panel.validation.scope.not_your_class_subject'),
            ]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('school_class_id', $classId)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'data.student_id' => __('panel.validation.scope.not_enrolled'),
            ]);
        }

        return $data;
    }
}
