<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use Illuminate\Validation\ValidationException;

/**
 * Câmpurile de cont care NU sunt coloane pe users (asocierile cu fișele, copiii părintelui,
 * starea contului, trimiterea credențialelor): se extrag înainte de salvare și se aplică după,
 * pe SERVER — perechea lui EnforcesManageableRole (care face același lucru pentru rol).
 */
trait ManagesAccountForm
{
    protected ?int $linkedTeacherId = null;

    protected ?int $linkedStudentId = null;

    /** @var array<int, int>|null */
    protected ?array $guardianStudentIds = null;

    protected bool $sendCredentials = false;

    protected ?string $plainTemporaryPassword = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullAccountExtras(array $data): array
    {
        // Nume + Prenume (câmpuri separate în formular) se recompun în users.name —
        // convenția catalogului: numele de familie ÎNTÂI („Nume Prenume").
        if (isset($data['last_name']) || isset($data['first_name'])) {
            $data['name'] = trim(trim((string) ($data['last_name'] ?? '')).' '.trim((string) ($data['first_name'] ?? '')));
            unset($data['last_name'], $data['first_name']);
        }

        $this->linkedTeacherId = filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;
        $this->linkedStudentId = filled($data['student_id'] ?? null) ? (int) $data['student_id'] : null;
        $this->guardianStudentIds = isset($data['guardian_student_ids']) && is_array($data['guardian_student_ids'])
            ? array_map(intval(...), $data['guardian_student_ids'])
            : null;
        $this->sendCredentials = (bool) ($data['send_credentials'] ?? false);

        unset($data['teacher_id'], $data['student_id'], $data['guardian_student_ids'], $data['send_credentials']);

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
     * Aplică asocierile + trimite credențialele. Se cheamă DUPĂ syncSelectedRole (rolul decide
     * ce fișe rămân legate).
     */
    protected function applyAccountExtras(): void
    {
        $user = $this->record;

        if (! $user instanceof User) {
            return;
        }

        $role = $this->selectedRole;

        // Fișa de PROFESOR: legată doar la personalul pedagogic; alt rol → dezlegată.
        $teacherId = in_array($role, [UserRole::Profesor->value, UserRole::Diriginte->value], true)
            ? $this->linkedTeacherId
            : null;

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

        // Copiii părintelui (pivotul guardian_student); alt rol → fără copii.
        $user->students()->sync($role === UserRole::Parinte->value ? ($this->guardianStudentIds ?? []) : []);

        if ($this->sendCredentials && $this->plainTemporaryPassword !== null && filled($user->email)) {
            $user->notify(new TemporaryCredentials($this->plainTemporaryPassword));
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
