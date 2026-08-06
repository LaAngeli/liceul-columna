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
 * Impune pe SERVER regulile la salvarea unei absențe. Se aplică TUTUROR (inclusiv administrației):
 * data nu poate fi în viitor, semestrul se DERIVĂ din dată (nu se alege manual), fără duplicate.
 * Apoi scoping-ul per rol: profesorul doar la disciplina lui, dirigintele pentru orice disciplină
 * a clasei lui.
 */
trait EnforcesAbsenceScope
{
    use RejectsClosedYearWrites;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceAbsenceScope(array $data, ?int $ignoreId = null): array
    {
        $occurredOn = isset($data['occurred_on']) && $data['occurred_on'] !== ''
            ? Carbon::parse((string) $data['occurred_on'])
            : null;

        // (1) O absență nu poate fi în viitor — elevul nu a lipsit încă.
        if ($occurredOn !== null && $occurredOn->startOfDay()->isAfter(Carbon::today())) {
            throw ValidationException::withMessages([
                'data.occurred_on' => __('panel.validation.absence.future'),
            ]);
        }

        // (2) Semestrul se DERIVĂ din dată (o absență aparține semestrului care conține ziua ei),
        // cu fallback la semestrul curent când data cade în afara oricărui interval (ex. vacanță).
        if ($occurredOn !== null) {
            $term = Term::forDate($occurredOn);

            if ($term instanceof Term) {
                $data['term_id'] = $term->id;
            } else {
                $current = SchoolCalendar::currentTerm();

                // GARD DE ROLLOVER, simetric cu {@see EnforcesGradeScope}: o dată ULTERIOARĂ
                // finalului anului din care face parte semestrul curent înseamnă că anul nou a
                // început fără structură definită. Fallback-ul tăcut ar fi pus absența de
                // septembrie în semestrul anului ÎNCHEIAT — exact eroarea invizibilă pe care garda
                // notelor o refuză de la început. Asimetria a ieșit la iveală când data implicită a
                // borderoului a devenit „azi" (04.08.2026): nota era refuzată, absența trecea.
                $yearEndsOn = $current?->academicYear?->ends_on;

                if ($current === null || ($yearEndsOn !== null && $occurredOn->startOfDay()->isAfter($yearEndsOn))) {
                    throw ValidationException::withMessages([
                        'data.occurred_on' => __('panel.validation.absence.no_term_for_date'),
                    ]);
                }

                $data['term_id'] = $current->id;
            }

            // Ambele ramuri au stabilit semestrul (a treia aruncă), deci valoarea există sigur.
            $this->rejectClosedYear($data['term_id'], 'data.occurred_on');
        }

        // (3) Anti-duplicat pe SLOT: aceeași absență ACTIVĂ (elev + zi + disciplină + ORĂ) nu se
        // consemnează de 2 ori. Ora face parte din identitate (05.08.2026): două ore consecutive
        // ale aceleiași discipline sunt două absențe legitime — pe ore DIFERITE. „Fără oră
        // precizată" (null) e propriul slot: consemnarea rapidă rămâne una pe zi, iar a doua
        // absență a zilei se pune numind ora, din panoul zilei.
        if ($occurredOn !== null && isset($data['student_id'])) {
            $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;
            $lessonNumber = isset($data['lesson_number']) && $data['lesson_number'] !== ''
                ? (int) $data['lesson_number']
                : null;

            $duplicate = Absence::query()
                // Absența ANULATĂ nu mai ocupă slotul: după ce ai desfăcut o consemnare greșită
                // trebuie să poți pune imediat cea corectă, pe aceeași oră. Altfel anularea ar
                // lăsa ora blocată de propria ei greșeală.
                ->active()
                ->where('student_id', (int) $data['student_id'])
                ->whereDate('occurred_on', $occurredOn->toDateString())
                ->when(
                    $subjectId !== null,
                    fn (Builder $q): Builder => $q->where('subject_id', $subjectId),
                    fn (Builder $q): Builder => $q->whereNull('subject_id'),
                )
                ->when(
                    $lessonNumber !== null,
                    fn (Builder $q): Builder => $q->where('lesson_number', $lessonNumber),
                    fn (Builder $q): Builder => $q->whereNull('lesson_number'),
                )
                ->when($ignoreId !== null, fn (Builder $q): Builder => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.student_id' => __('panel.validation.absence.duplicate'),
                ]);
            }

            // EXCLUDEREA RECIPROCĂ notă↔absență pe aceeași oră (06.08.2026), oglinda gărzii din
            // {@see EnforcesGradeScope}: o oră cu notă activă nu poate primi absență — elevul a
            // fost în bancă și a răspuns. Anulatele nu blochează; rândurile „fără oră" (istorice)
            // rămân în afara regulii — nu li se poate ști ora.
            if ($lessonNumber !== null) {
                $graded = Grade::query()
                    ->where('student_id', (int) $data['student_id'])
                    ->where('lesson_number', $lessonNumber)
                    ->whereNull('annulled_at')
                    ->whereDate('graded_on', $occurredOn->toDateString())
                    ->when(
                        $subjectId !== null,
                        fn (Builder $q): Builder => $q->where('subject_id', $subjectId),
                        fn (Builder $q): Builder => $q->whereNull('subject_id'),
                    )
                    ->exists();

                if ($graded) {
                    throw ValidationException::withMessages([
                        'data.lesson_number' => __('panel.validation.absence.hour_has_grade', ['number' => $lessonNumber]),
                    ]);
                }
            }
        }

        $user = auth('web')->user();

        // Autoritatea academică (super-admin / director / prim-vicedirector) nu e limitată la scope.
        // Administratorul operațional/tehnic NU consemnează absențe → cade pe ramura „fără fișă".
        if (! $user || $user->canAdministerCatalog()) {
            return $data;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'data.student_id' => __('panel.validation.scope.no_teacher_profile'),
            ]);
        }

        $data['teacher_id'] = $teacher->id;

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $studentId = (int) ($data['student_id'] ?? 0);

        if (! $teacher->canRecordAbsence($classId, $subjectId)) {
            throw ValidationException::withMessages([
                'data.school_class_id' => __('panel.validation.scope.cannot_record_absence'),
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
