<?php

namespace App\Filament\Concerns;

use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

/**
 * Impune pe SERVER scope-ul profesorului la salvarea unei note — opțiunile scoped din
 * formular sunt doar pentru UX; aici e protecția reală (împotriva POST-urilor manipulate).
 */
trait EnforcesGradeScope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceGradeScope(array $data): array
    {
        $user = auth()->user();

        // Autoritatea academică (super-admin / director / prim-vicedirector) nu e limitată.
        // Administratorul operațional/tehnic NU scrie note → cade pe ramura „fără fișă" și e blocat.
        if (! $user || $user->canAdministerCatalog()) {
            return $data;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'student_id' => 'Contul tău nu e legat de o fișă de profesor.',
            ]);
        }

        // Autorul notei = profesorul logat (nu de încredere din formular).
        $data['teacher_id'] = $teacher->id;

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);

        if (! $teacher->canGradeClassSubject($classId, $subjectId)) {
            throw ValidationException::withMessages([
                'subject_id' => 'Nu predai această disciplină la această clasă.',
            ]);
        }

        $enrolled = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('school_class_id', $classId)
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => 'Elevul nu este înmatriculat în clasa selectată.',
            ]);
        }

        return $data;
    }
}
