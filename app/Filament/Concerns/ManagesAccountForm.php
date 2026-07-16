<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Câmpurile de cont care NU sunt coloane pe users (asocierile cu fișele, copiii părintelui,
 * starea contului, trimiterea credențialelor): se extrag înainte de salvare și se aplică după,
 * pe SERVER — perechea lui EnforcesManageableRole (care face același lucru pentru rol).
 *
 * ONBOARDING UNIFICAT: la creare, fișa de profesor/elev se poate CREA chiar din acest flux
 * (numele din Identitate) și integrarea se face pe loc — alocări clasă×disciplină, clasa de
 * diriginție, înmatricularea elevului, legătura cu conturile de părinte. Totul într-o singură
 * tranzacție: ori contul iese complet integrat, ori nimic nu rămâne pe jumătate.
 */
trait ManagesAccountForm
{
    protected ?int $linkedTeacherId = null;

    protected ?int $linkedStudentId = null;

    /** @var array<int, int>|null */
    protected ?array $guardianStudentIds = null;

    protected bool $sendCredentials = false;

    protected ?string $plainTemporaryPassword = null;

    protected ?string $accountLastName = null;

    protected ?string $accountFirstName = null;

    /** Implicit „link": doar formularul de CREARE trimite radio-ul de mod (editarea nu creează fișe). */
    protected string $teacherFicheMode = UserForm::FICHE_LINK;

    protected string $studentFicheMode = UserForm::FICHE_LINK;

    protected ?string $teacherFicheSex = null;

    protected ?string $teacherFichePosition = null;

    protected ?string $studentFicheSex = null;

    protected ?string $studentFicheRegisterNumber = null;

    protected ?string $studentFicheSecondLanguage = null;

    /** @var array<int, array<string, mixed>> */
    protected array $teachingPairs = [];

    protected ?int $homeroomClassId = null;

    protected ?int $enrollClassId = null;

    /** @var array<int, int>|null */
    protected ?array $studentGuardianUserIds = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullAccountExtras(array $data): array
    {
        // Nume + Prenume (câmpuri separate în formular) se recompun în users.name —
        // convenția catalogului: numele de familie ÎNTÂI („Nume Prenume"). Copiile rămân
        // pe trait: fișa creată în fluxul unificat folosește exact aceleași câmpuri.
        if (isset($data['last_name']) || isset($data['first_name'])) {
            $this->accountLastName = trim((string) ($data['last_name'] ?? ''));
            $this->accountFirstName = trim((string) ($data['first_name'] ?? ''));
            $data['name'] = trim($this->accountLastName.' '.$this->accountFirstName);
            unset($data['last_name'], $data['first_name']);
        }

        $this->linkedTeacherId = filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;
        $this->linkedStudentId = filled($data['student_id'] ?? null) ? (int) $data['student_id'] : null;
        $this->guardianStudentIds = isset($data['guardian_student_ids']) && is_array($data['guardian_student_ids'])
            ? array_map(intval(...), $data['guardian_student_ids'])
            : null;
        $this->sendCredentials = (bool) ($data['send_credentials'] ?? false);

        // Fluxul de onboarding: modul fișei + datele fișei noi + integrarea în module.
        $this->teacherFicheMode = is_string($data['teacher_fiche_mode'] ?? null)
            ? $data['teacher_fiche_mode']
            : UserForm::FICHE_LINK;
        $this->studentFicheMode = is_string($data['student_fiche_mode'] ?? null)
            ? $data['student_fiche_mode']
            : UserForm::FICHE_LINK;
        // Select-urile cu opțiuni-enum (sex, limba a 2-a) dehidratează INSTANȚA enum-ului,
        // nu string-ul — se normalizează la valoarea scalară (cast-urile modelelor o refac).
        $this->teacherFicheSex = $this->scalarFormValue($data['teacher_fiche_sex'] ?? null);
        $this->teacherFichePosition = $this->scalarFormValue($data['teacher_fiche_position'] ?? null);
        $this->studentFicheSex = $this->scalarFormValue($data['student_fiche_sex'] ?? null);
        $this->studentFicheRegisterNumber = $this->scalarFormValue($data['student_fiche_register_number'] ?? null);
        $this->studentFicheSecondLanguage = $this->scalarFormValue($data['student_fiche_second_language'] ?? null);
        $this->teachingPairs = isset($data['teaching_pairs']) && is_array($data['teaching_pairs'])
            ? array_values(array_filter($data['teaching_pairs'], is_array(...)))
            : [];
        $this->homeroomClassId = filled($data['homeroom_class_id'] ?? null) ? (int) $data['homeroom_class_id'] : null;
        $this->enrollClassId = filled($data['enroll_class_id'] ?? null) ? (int) $data['enroll_class_id'] : null;
        $this->studentGuardianUserIds = isset($data['student_guardian_user_ids']) && is_array($data['student_guardian_user_ids'])
            ? array_map(intval(...), $data['student_guardian_user_ids'])
            : null;

        unset(
            $data['teacher_id'],
            $data['student_id'],
            $data['guardian_student_ids'],
            $data['send_credentials'],
            $data['teacher_fiche_mode'],
            $data['student_fiche_mode'],
            $data['teacher_fiche_sex'],
            $data['teacher_fiche_position'],
            $data['student_fiche_sex'],
            $data['student_fiche_register_number'],
            $data['student_fiche_second_language'],
            $data['teaching_pairs'],
            $data['homeroom_class_id'],
            $data['enroll_class_id'],
            $data['student_guardian_user_ids'],
        );

        // Starea contului: select-ul devine timestampul suspended_at (păstrat dacă era deja suspendat).
        $status = $data['account_status'] ?? 'active';
        unset($data['account_status']);

        $record = $this->record ?? null;

        if ($status === 'suspended' && $record instanceof User && $record->getKey() === auth('web')->id()) {
            throw ValidationException::withMessages([
                'data.account_status' => __('panel.forms.user.cannot_suspend_self'),
            ]);
        }

        $data['suspended_at'] = $status === 'suspended'
            ? (($record instanceof User ? $record->suspended_at : null) ?? now())
            : null;

        // Parola în clar se reține DOAR pentru e-mailul de credențiale (modelul o stochează hash-uită).
        if (filled($data['password'] ?? null)) {
            $this->plainTemporaryPassword = (string) $data['password'];
        }

        return $data;
    }

    /**
     * Aplică asocierile + integrarea în module + trimite credențialele. Se cheamă DUPĂ
     * syncSelectedRole (rolul decide ce fișe rămân legate). Tranzacție unică: fișa, legarea,
     * alocările, diriginția, înmatricularea și părinții reușesc împreună sau deloc.
     */
    protected function applyAccountExtras(): void
    {
        $user = $this->record;

        if (! $user instanceof User) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $role = $this->selectedRole;
            $isPedagogic = in_array($role, [UserRole::Profesor->value, UserRole::Diriginte->value], true);

            // ONBOARDING: fișa NOUĂ se naște din datele contului (numele din Identitate) —
            // e-mailul fișei de profesor = e-mailul contului (o singură sursă de contact).
            if ($isPedagogic && $this->teacherFicheMode === UserForm::FICHE_CREATE && $this->linkedTeacherId === null) {
                $fiche = Teacher::query()->create([
                    'last_name' => $this->accountLastName,
                    'first_name' => $this->accountFirstName,
                    'sex' => $this->teacherFicheSex,
                    'position' => $this->teacherFichePosition,
                    'email' => $user->email,
                ]);

                $this->linkedTeacherId = (int) $fiche->getKey();
            }

            if ($role === UserRole::Elev->value && $this->studentFicheMode === UserForm::FICHE_CREATE && $this->linkedStudentId === null) {
                $fiche = Student::query()->create([
                    'last_name' => $this->accountLastName,
                    'first_name' => $this->accountFirstName,
                    'sex' => $this->studentFicheSex,
                    'register_number' => $this->studentFicheRegisterNumber,
                    'second_language' => $this->studentFicheSecondLanguage,
                ]);

                $this->linkedStudentId = (int) $fiche->getKey();
            }

            // Fișa de PROFESOR: legată doar la personalul pedagogic; alt rol → dezlegată.
            $teacherId = $isPedagogic ? $this->linkedTeacherId : null;

            Teacher::query()
                ->where('user_id', $user->getKey())
                ->when($teacherId !== null, fn ($query) => $query->whereKeyNot($teacherId))
                ->update(['user_id' => null]);

            if ($teacherId !== null) {
                // Doar fișele libere (sau deja ale contului) — o fișă „furată" între timp rămâne neatinsă.
                Teacher::query()
                    ->whereKey($teacherId)
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->getKey()))
                    ->update(['user_id' => $user->getKey()]);
            }

            // Fișa de ELEV: aceeași regulă, pentru rolul elev.
            $studentId = $role === UserRole::Elev->value ? $this->linkedStudentId : null;

            Student::query()
                ->where('user_id', $user->getKey())
                ->when($studentId !== null, fn ($query) => $query->whereKeyNot($studentId))
                ->update(['user_id' => null]);

            if ($studentId !== null) {
                Student::query()
                    ->whereKey($studentId)
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->getKey()))
                    ->update(['user_id' => $user->getKey()]);
            }

            // Copiii părintelui (pivotul guardian_student); alt rol → fără copii. Id-urile trec
            // prin registru (selectul cu căutare pe server nu are listă statică de validat).
            $childIds = $role === UserRole::Parinte->value ? ($this->guardianStudentIds ?? []) : [];

            if ($childIds !== []) {
                $childIds = Student::query()->whereKey($childIds)->pluck('id')->all();
            }

            $user->students()->sync($childIds);

            $this->integrateTeacher($teacherId, $role);
            $this->integrateStudent($studentId);
        });

        // Credențialele pleacă DUPĂ tranzacție: un rollback nu trebuie să lase e-mailuri trimise.
        if ($this->sendCredentials && $this->plainTemporaryPassword !== null && filled($user->email)) {
            $user->notify(new TemporaryCredentials($this->plainTemporaryPassword));
        }
    }

    /** Valoarea scalară a unui câmp de formular: enum-urile devin valoarea lor, golul devine null. */
    private function scalarFormValue(mixed $state): ?string
    {
        if ($state instanceof \BackedEnum) {
            return (string) $state->value;
        }

        return filled($state) ? (string) $state : null;
    }

    /**
     * Integrarea pedagogică: alocările clasă×disciplină (fundamentul scoping-ului din catalog)
     * + clasa de diriginție. Se aplică pe fișa legată — nouă sau existentă.
     */
    private function integrateTeacher(?int $teacherId, ?string $role): void
    {
        if ($teacherId === null) {
            return;
        }

        foreach ($this->teachingPairs as $pair) {
            $classId = filled($pair['school_class_id'] ?? null) ? (int) $pair['school_class_id'] : null;
            $subjectId = filled($pair['subject_id'] ?? null) ? (int) $pair['subject_id'] : null;

            if ($classId === null || $subjectId === null) {
                continue;
            }

            // Indexul unic vede ȘI alocările arhivate → o alocare istorică se RESTAUREAZĂ,
            // nu se dublează (ar fi eroare SQL); una activă rămâne cum e (idempotent).
            $assignment = TeachingAssignment::withTrashed()->firstOrCreate([
                'teacher_id' => $teacherId,
                'school_class_id' => $classId,
                'subject_id' => $subjectId,
                'english_group' => null,
            ]);

            if ($assignment->trashed()) {
                $assignment->restore();
            }
        }

        if ($role === UserRole::Diriginte->value && $this->homeroomClassId !== null) {
            // Doar o clasă rămasă FĂRĂ diriginte poate primi unul — dacă a fost ocupată între
            // timp, rândul nu se atinge (opțiunile formularului listează doar clasele libere).
            SchoolClass::query()
                ->whereKey($this->homeroomClassId)
                ->whereDoesntHave('homeroomTeacher')
                ->update(['homeroom_teacher_id' => $teacherId]);
        }
    }

    /**
     * Integrarea elevului: înmatricularea în clasa aleasă (anul vine din clasă — coerența e
     * garantată) + legătura cu conturile de părinte existente (aditivă, nu șterge tutori).
     */
    private function integrateStudent(?int $studentId): void
    {
        if ($studentId === null) {
            return;
        }

        if ($this->studentFicheMode === UserForm::FICHE_CREATE && $this->enrollClassId !== null) {
            $class = SchoolClass::query()->whereKey($this->enrollClassId)->first();

            if ($class !== null) {
                // Un elev = o singură înmatriculare pe an (indexul unic vede și arhivarea).
                $enrollment = Enrollment::withTrashed()->firstOrCreate([
                    'student_id' => $studentId,
                    'academic_year_id' => (int) $class->academic_year_id,
                ], [
                    'school_class_id' => (int) $class->getKey(),
                    'enrolled_on' => now()->toDateString(),
                ]);

                if ($enrollment->trashed()) {
                    $enrollment->restore();
                }
            }
        }

        if ($this->studentGuardianUserIds !== null && $this->studentGuardianUserIds !== []) {
            // Doar conturile care CHIAR au rolul de părinte (id-urile vin dintr-un select cu
            // căutare pe server); legătura e aditivă — părinții existenți ai fișei rămân.
            $guardianIds = User::query()
                ->whereKey($this->studentGuardianUserIds)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
                ->pluck('id')
                ->all();

            if ($guardianIds !== []) {
                Student::query()->whereKey($studentId)->first()?->guardians()->syncWithoutDetaching($guardianIds);
            }
        }
    }

    /**
     * Pre-populează asocierile la EDITARE (nu sunt coloane pe users).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillAccountExtras(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof User) {
            // Despărțirea numelui stocat în cele două câmpuri: primul cuvânt = numele de
            // familie (convenția „Nume Prenume" — inversul recompunerii din pullAccountExtras).
            $parts = explode(' ', trim((string) $record->name), 2);
            $data['last_name'] = $parts[0];
            $data['first_name'] = $parts[1] ?? '';

            $data['teacher_id'] = $record->teacher?->getKey();
            $data['student_id'] = $record->student?->getKey();
            $data['guardian_student_ids'] = $record->students()->pluck('students.id')->all();
            $data['account_status'] = $record->isSuspended() ? 'suspended' : 'active';
        }

        return $data;
    }
}
