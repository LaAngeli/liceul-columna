<?php

namespace App\Filament\Concerns;

use App\Models\Enrollment;
use App\Models\Term;
use App\Support\SchoolCalendar;
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
    protected function enforceGradeScope(array $data): array
    {
        if (isset($data['graded_on']) && $data['graded_on'] !== '') {
            $gradedOn = Carbon::parse((string) $data['graded_on']);

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
