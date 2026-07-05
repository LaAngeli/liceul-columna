<?php

namespace App\Filament\Concerns;

use App\Models\Absence;
use App\Models\Enrollment;
use App\Models\Term;
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
            $data['term_id'] = $term instanceof Term
                ? $term->id
                : Term::query()->where('is_current', true)->value('id');
        }

        // (3) Anti-duplicat: aceeași absență ACTIVĂ (elev + zi + disciplină) nu se consemnează de 2 ori.
        if ($occurredOn !== null && isset($data['student_id'])) {
            $subjectId = isset($data['subject_id']) && $data['subject_id'] !== '' ? (int) $data['subject_id'] : null;

            $duplicate = Absence::query()
                ->where('student_id', (int) $data['student_id'])
                ->whereDate('occurred_on', $occurredOn->toDateString())
                ->when(
                    $subjectId !== null,
                    fn (Builder $q): Builder => $q->where('subject_id', $subjectId),
                    fn (Builder $q): Builder => $q->whereNull('subject_id'),
                )
                ->when($ignoreId !== null, fn (Builder $q): Builder => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'data.student_id' => __('panel.validation.absence.duplicate'),
                ]);
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
