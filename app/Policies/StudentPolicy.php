<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Absence;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TermAverage;
use App\Models\User;
use App\Policies\Concerns\ConfiguredBySchoolAdmins;
use Illuminate\Database\Eloquent\Model;

class StudentPolicy
{
    // Fișa de elev se creează/editează/șterge doar de configuratorii școlii (§3.3).
    use ConfiguredBySchoolAdmins;

    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    /**
     * Cine poate vedea profilul unui elev (L133 — dosar cu PII de minor):
     * - administrația academică (super/director/prim-vicedir/AO) — orice dosar; AT niciodată (§3.2);
     * - profesorul/dirigintele — DOAR elevii claselor lui (aliniat cu scoping-ul panoului,
     *   {@see StudentResource::getEloquentQuery}); înainte, orice
     *   profesor putea deschide orice dosar prin /cabinet/elev/{id};
     * - elevul însuși (contul legat);
     * - un părinte/tutore atribuit elevului (inclusiv profesorul-părinte, prin fall-through).
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->hasAnyRole(UserRole::panelRoleValues()) && ! $user->isTechnicalAdmin()) {
            $classIds = $user->teacher?->visibleSchoolClassIds() ?? [];

            if ($classIds !== [] && $student->enrollments()->whereIn('school_class_id', $classIds)->exists()) {
                return true;
            }
            // NU return false: profesorul poate fi și PĂRINTELE elevului — cad pe verificările de familie.
        }

        if ($student->user_id === $user->id) {
            return true;
        }

        return $user->students()->whereKey($student->getKey())->exists();
    }

    /**
     * ForceDelete pe un elev ar distruge prin cascada FK ÎNTREG istoricul lui academic (note,
     * absențe, medii, matricolă, înmatriculări) — la un click. Blocat cât timp există istoric.
     */
    protected function hasDependentAcademicHistory(Model $record): bool
    {
        return Grade::withTrashed()->where('student_id', $record->getKey())->exists()
            || Absence::withTrashed()->where('student_id', $record->getKey())->exists()
            || TermAverage::withTrashed()->where('student_id', $record->getKey())->exists()
            || AcademicRecord::withTrashed()->where('student_id', $record->getKey())->exists()
            || Enrollment::withTrashed()->where('student_id', $record->getKey())->exists();
    }
}
