<?php

namespace App\Filament\Concerns;

use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Impune pe SERVER scope-ul profesorului la salvarea unei note — opțiunile scoped din
 * formular sunt doar pentru UX; aici e protecția reală (împotriva POST-urilor manipulate).
 * Semestrul se DERIVĂ din data notei (nu se alege manual), la fel ca la absențe.
 */
trait EnforcesGradeScope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceGradeScope(array $data): array
    {
        // Semestrul aparține datei notei; fallback la semestrul curent în afara intervalelor.
        if (isset($data['graded_on']) && $data['graded_on'] !== '') {
            $term = Term::forDate(Carbon::parse((string) $data['graded_on']));
            $data['term_id'] = $term instanceof Term
                ? $term->id
                : Term::query()->where('is_current', true)->value('id');
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
                'student_id' => __('panel.validation.scope.no_teacher_profile'),
            ]);
        }

        // Autorul notei = profesorul logat (nu de încredere din formular).
        $data['teacher_id'] = $teacher->id;

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);

        if (! $teacher->canGradeClassSubject($classId, $subjectId)) {
            throw ValidationException::withMessages([
                'subject_id' => __('panel.validation.scope.not_your_class_subject'),
            ]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('school_class_id', $classId)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => __('panel.validation.scope.not_enrolled'),
            ]);
        }

        return $data;
    }
}
