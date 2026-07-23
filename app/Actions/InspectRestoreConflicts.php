<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * „Ce se strică dacă restaurez asta?" — verificarea de dinaintea restaurării.
 *
 * Distincția care contează: BLOCANT = restaurarea ar produce o stare imposibilă sau ar scrie
 * într-un an închis (butonul se stinge); AVERTISMENT = restaurarea reușește, dar cineva trebuie
 * să știe ce NU revine odată cu ea (dirigenția preluată, alocările șterse, elevul fără clasă).
 *
 * ⚠️ Adevăr de schemă care schimbă regulile: indexurile unice pe `enrollments`
 * (student, an) și `school_classes` (an, treaptă, secție) NU exclud rândurile șterse — un rând
 * șters ȚINE slotul ocupat. Deci pentru ele „suprapunerea la restaurare" e structural imposibilă
 * (nimeni n-a putut crea un duplicat cât timp rândul șters exista), iar riscul real e REFERENȚIAL:
 * părintele ierarhic e șters sau anul e închis. Pentru elevi/profesori/discipline, unde unicitatea
 * e ținută în cod, verificăm exact regulile aplicației.
 */
class InspectRestoreConflicts
{
    /**
     * Verdictul se dă pe ÎNREGISTRARE, nu pe categoria din URL: tipul e o etichetă de navigație,
     * modelul e sursa adevărului.
     *
     * @param  Student|Teacher|SchoolClass|Enrollment|Subject  $record
     * @return array{blocking: array<int, string>, warnings: array<int, string>, cascade: int}
     */
    public function inspect(Model $record): array
    {
        $result = match (true) {
            $record instanceof Student => $this->student($record),
            $record instanceof Teacher => $this->teacher($record),
            $record instanceof SchoolClass => $this->schoolClass($record),
            $record instanceof Enrollment => $this->enrollment($record),
            $record instanceof Subject => $this->subject($record),
        };

        return $result + ['blocking' => [], 'warnings' => [], 'cascade' => 0];
    }

    /**
     * Elevul: contul e resursa disputată (o fișă ↔ un cont), iar înmatricularea ștearsă e capcana
     * — fără ea, elevul revine în afara oricărei clase ȘI nu i se poate crea alta (slotul
     * (elev, an) e ocupat de rândul șters). De aceea o oferim în cascadă.
     *
     * @return array{blocking?: array<int, string>, warnings?: array<int, string>, cascade?: int}
     */
    private function student(Student $record): array
    {
        $blocking = [];
        $warnings = [];

        if ($record->user_id !== null) {
            $taken = Student::query()
                ->where('user_id', $record->user_id)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($taken) {
                $blocking[] = (string) __('panel.restore.conflicts.student_account_taken');
            }
        }

        if ($record->register_number !== null && $record->register_number !== '') {
            $duplicate = Student::query()
                ->where('register_number', $record->register_number)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($duplicate) {
                $warnings[] = (string) __('panel.restore.conflicts.student_register_duplicate', [
                    'number' => $record->register_number,
                ]);
            }
        }

        $trashedEnrollments = Enrollment::query()
            ->onlyTrashed()
            ->where('student_id', $record->getKey())
            ->count();

        $activeEnrollments = Enrollment::query()
            ->where('student_id', $record->getKey())
            ->count();

        if ($trashedEnrollments > 0) {
            $warnings[] = (string) trans_choice('panel.restore.conflicts.student_enrollments_cascade', $trashedEnrollments, [
                'count' => $trashedEnrollments,
            ]);
        } elseif ($activeEnrollments === 0) {
            $warnings[] = (string) __('panel.restore.conflicts.student_without_class');
        }

        return ['blocking' => $blocking, 'warnings' => $warnings, 'cascade' => $trashedEnrollments];
    }

    /**
     * Profesorul: contul e unic pe fișă; dirigenția și alocările NU revin de la sine — între timp
     * clasele au putut primi alt diriginte, iar alocările șterse rămân șterse.
     *
     * @return array{blocking?: array<int, string>, warnings?: array<int, string>}
     */
    private function teacher(Teacher $record): array
    {
        $blocking = [];
        $warnings = [];

        if ($record->user_id !== null) {
            $taken = Teacher::query()
                ->where('user_id', $record->user_id)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($taken) {
                $blocking[] = (string) __('panel.restore.conflicts.teacher_account_taken');
            }
        }

        $homeroom = SchoolClass::query()
            ->where('homeroom_teacher_id', $record->getKey())
            ->count();

        if ($homeroom === 0) {
            $warnings[] = (string) __('panel.restore.conflicts.teacher_without_homeroom');
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    /**
     * Clasa: anul e condiția de existență (șters → clasa n-are unde reveni; închis → registrul lui
     * e înghețat). Dirigintele șters nu blochează, dar clasa revine descoperită.
     *
     * @return array{blocking?: array<int, string>, warnings?: array<int, string>, cascade?: int}
     */
    private function schoolClass(SchoolClass $record): array
    {
        $blocking = [];
        $warnings = [];

        $year = $record->academicYear()->withoutGlobalScope(SoftDeletingScope::class)->first();

        if ($year === null) {
            $blocking[] = (string) __('panel.restore.conflicts.class_year_missing');
        } elseif ($year->trashed()) {
            $blocking[] = (string) __('panel.restore.conflicts.class_year_trashed', ['year' => $year->name]);
        } elseif ($year->isClosed()) {
            $blocking[] = (string) __('panel.restore.conflicts.class_year_closed', ['year' => $year->name]);
        }

        if ($record->homeroom_teacher_id !== null) {
            $teacherExists = Teacher::query()->whereKey($record->homeroom_teacher_id)->exists();

            if (! $teacherExists) {
                $warnings[] = (string) __('panel.restore.conflicts.class_homeroom_trashed');
            }
        }

        $trashedEnrollments = Enrollment::query()
            ->onlyTrashed()
            ->where('school_class_id', $record->getKey())
            ->count();

        if ($trashedEnrollments > 0) {
            $warnings[] = (string) trans_choice('panel.restore.conflicts.class_enrollments_cascade', $trashedEnrollments, [
                'count' => $trashedEnrollments,
            ]);
        }

        return ['blocking' => $blocking, 'warnings' => $warnings, 'cascade' => $trashedEnrollments];
    }

    /**
     * Înmatricularea: are DOI părinți (elevul și clasa) — dacă vreunul e șters, rândul restaurat ar
     * lega registrul de o entitate invizibilă. Ordinea corectă e elev/clasă ÎNTÂI, apoi rândul.
     *
     * @return array{blocking?: array<int, string>, warnings?: array<int, string>}
     */
    private function enrollment(Enrollment $record): array
    {
        $blocking = [];
        $warnings = [];

        $student = $record->student()->withoutGlobalScope(SoftDeletingScope::class)->first();

        if ($student === null || $student->trashed()) {
            $blocking[] = (string) __('panel.restore.conflicts.enrollment_student_trashed');
        }

        $class = $record->schoolClass()->withoutGlobalScope(SoftDeletingScope::class)->first();

        if ($class === null || $class->trashed()) {
            $blocking[] = (string) __('panel.restore.conflicts.enrollment_class_trashed');
        }

        $year = $record->academicYear()->withoutGlobalScope(SoftDeletingScope::class)->first();

        if ($year !== null && $year->isClosed()) {
            $blocking[] = (string) __('panel.restore.conflicts.enrollment_year_closed', ['year' => $year->name]);
        }

        if ($record->left_on !== null) {
            $warnings[] = (string) __('panel.restore.conflicts.enrollment_departed');
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }

    /**
     * Disciplina: numele e unic prin regulă de aplicație (nu prin index), iar poziția în foaia
     * matricolă trebuie să rămână unică și contiguă — dacă a fost ocupată între timp, restaurarea
     * o mută la coadă (reparare automată, semnalată).
     *
     * @return array{blocking?: array<int, string>, warnings?: array<int, string>}
     */
    private function subject(Subject $record): array
    {
        $blocking = [];
        $warnings = [];

        $duplicate = Subject::query()
            ->where('name', $record->name)
            ->whereKeyNot($record->getKey())
            ->exists();

        if ($duplicate) {
            $blocking[] = (string) __('panel.restore.conflicts.subject_name_taken', ['name' => $record->name]);
        }

        if ($record->report_order !== null) {
            $taken = Subject::query()
                ->where('report_order', $record->report_order)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($taken) {
                $warnings[] = (string) __('panel.restore.conflicts.subject_order_taken', [
                    'position' => $record->report_order,
                ]);
            }
        }

        return ['blocking' => $blocking, 'warnings' => $warnings];
    }
}
