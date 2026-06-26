<?php

namespace App\Filament\Concerns;

use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

/**
 * Impune pe SERVER scope-ul la salvarea unei absențe: profesorul doar la disciplina lui,
 * dirigintele pentru orice disciplină a clasei lui.
 */
trait EnforcesAbsenceScope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function enforceAbsenceScope(array $data): array
    {
        $user = auth()->user();

        // Autoritatea academică (super-admin / director / prim-vicedirector) nu e limitată.
        // Administratorul operațional/tehnic NU consemnează absențe → cade pe ramura „fără fișă".
        if (! $user || $user->canAdministerCatalog()) {
            return $data;
        }

        $teacher = $user->teacher;

        if (! $teacher) {
            throw ValidationException::withMessages([
                'student_id' => 'Contul tău nu e legat de o fișă de profesor.',
            ]);
        }

        $data['teacher_id'] = $teacher->id;

        $classId = (int) ($data['school_class_id'] ?? 0);
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $studentId = (int) ($data['student_id'] ?? 0);

        if (! $teacher->canRecordAbsence($classId, $subjectId)) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Nu poți înregistra absențe pentru această clasă/disciplină.',
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
