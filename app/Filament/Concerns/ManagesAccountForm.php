<?php

namespace App\Filament\Concerns;

use App\Actions\SyncHomeroomRole;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\TemporaryCredentials;
use App\Support\SchoolCalendar;
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

    protected ?string $studentFicheSex = null;

    protected ?string $studentFicheRegisterNumber = null;

    protected ?string $studentFicheSecondLanguage = null;

    /** @var array<int, array<string, mixed>> */
    protected array $teachingPairs = [];

    protected ?int $homeroomClassId = null;

    /**
     * Dirigenția gestionată la EDITARE (lista completă dorită); null = câmpul n-a fost trimis.
     *
     * @var list<int>|null
     */
    protected ?array $homeroomClassIds = null;

    /** Sexul fișei legate, editat de pe fișa persoanei (consolidarea 2026-07-31). */
    protected ?string $ficheSex = null;

    protected bool $ficheSexSubmitted = false;

    protected ?int $enrollClassId = null;

    /** @var array<int, int>|null */
    protected ?array $studentGuardianUserIds = null;

    /**
     * Părinții NOI, creați odată cu elevul (fiecare rând: nume, prenume, utilizator, e-mail, parolă).
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $newGuardians = [];

    /**
     * Credențialele părinților creați, reținute pentru e-mail — se trimit DUPĂ tranzacție.
     *
     * @var array<int, array{user: User, password: string}>
     */
    protected array $createdGuardians = [];

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

        // FIȘĂ EXISTENTĂ: identitatea vine DIN REGISTRU, nu din formular — câmpurile de nume nici
        // nu se mai afișează (cerința beneficiarului 2026-07-24). Fișa e sursa de adevăr a
        // persoanei; contul nu poate ajunge cu alt nume decât fișa lui.
        if (blank($data['name'] ?? null)) {
            $fiche = $this->linkedTeacherId !== null
                ? Teacher::query()->find($this->linkedTeacherId)
                : ($this->linkedStudentId !== null ? Student::query()->find($this->linkedStudentId) : null);

            if ($fiche !== null) {
                $this->accountLastName = (string) $fiche->last_name;
                $this->accountFirstName = (string) $fiche->first_name;
                $data['name'] = (string) $fiche->full_name;
            }
        }
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
        $this->studentFicheSex = $this->scalarFormValue($data['student_fiche_sex'] ?? null);
        $this->studentFicheRegisterNumber = $this->scalarFormValue($data['student_fiche_register_number'] ?? null);
        $this->studentFicheSecondLanguage = $this->scalarFormValue($data['student_fiche_second_language'] ?? null);
        $this->teachingPairs = isset($data['teaching_pairs']) && is_array($data['teaching_pairs'])
            ? array_values(array_filter($data['teaching_pairs'], is_array(...)))
            : [];
        $this->homeroomClassId = filled($data['homeroom_class_id'] ?? null) ? (int) $data['homeroom_class_id'] : null;
        $this->homeroomClassIds = isset($data['homeroom_class_ids']) && is_array($data['homeroom_class_ids'])
            ? array_values(array_map(intval(...), $data['homeroom_class_ids']))
            : null;
        $this->ficheSexSubmitted = array_key_exists('fiche_sex', $data);
        $this->ficheSex = $this->scalarFormValue($data['fiche_sex'] ?? null);
        $this->enrollClassId = filled($data['enroll_class_id'] ?? null) ? (int) $data['enroll_class_id'] : null;
        $this->studentGuardianUserIds = isset($data['student_guardian_user_ids']) && is_array($data['student_guardian_user_ids'])
            ? array_map(intval(...), $data['student_guardian_user_ids'])
            : null;
        $this->newGuardians = isset($data['student_new_guardians']) && is_array($data['student_new_guardians'])
            ? array_values(array_filter($data['student_new_guardians'], is_array(...)))
            : [];

        // ⚠️ Verificarea coliziunilor se face AICI, înainte de crearea contului: panoul NU
        // rulează crearea într-o tranzacție (Filament o are dezactivată implicit), deci o
        // excepție aruncată mai târziu ar lăsa în urmă contul și fișa deja scrise.
        $this->guardNewGuardians((string) ($data['username'] ?? ''), $data['email'] ?? null);

        unset(
            $data['teacher_id'],
            $data['student_id'],
            $data['guardian_student_ids'],
            $data['send_credentials'],
            $data['teacher_fiche_mode'],
            $data['student_fiche_mode'],
            $data['teacher_fiche_sex'],
            $data['student_fiche_sex'],
            $data['student_fiche_register_number'],
            $data['student_fiche_second_language'],
            $data['teaching_pairs'],
            $data['homeroom_class_id'],
            $data['homeroom_class_ids'],
            $data['fiche_sex'],
            $data['enroll_class_id'],
            $data['student_guardian_user_ids'],
            $data['student_new_guardians'],
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
            $roles = $this->selectedRoles;
            $isPedagogic = array_intersect($roles, [UserRole::Profesor->value, UserRole::Diriginte->value]) !== [];

            // ONBOARDING: fișa NOUĂ se naște din datele contului (numele din Identitate) —
            // e-mailul fișei de profesor = e-mailul contului (o singură sursă de contact).
            if ($isPedagogic && $this->teacherFicheMode === UserForm::FICHE_CREATE && $this->linkedTeacherId === null) {
                $fiche = Teacher::query()->create([
                    'last_name' => $this->accountLastName,
                    'first_name' => $this->accountFirstName,
                    'sex' => $this->teacherFicheSex,
                    'email' => $user->email,
                ]);

                $this->linkedTeacherId = (int) $fiche->getKey();
            }

            if (in_array(UserRole::Elev->value, $roles, true) && $this->studentFicheMode === UserForm::FICHE_CREATE && $this->linkedStudentId === null) {
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
            $studentId = in_array(UserRole::Elev->value, $roles, true) ? $this->linkedStudentId : null;

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
            $childIds = in_array(UserRole::Parinte->value, $roles, true) ? ($this->guardianStudentIds ?? []) : [];

            if ($childIds !== []) {
                $childIds = Student::query()->whereKey($childIds)->pluck('id')->all();
            }

            $user->students()->sync($childIds);

            $this->integrateTeacher($teacherId, $roles);
            $this->integrateStudent($studentId);

            // O PERSOANĂ = O IDENTITATE (consolidarea 2026-07-31): numele/sexul editate pe cont
            // se propagă pe fișa legată, iar e-mailul fișei de profesor urmează contul —
            // registrul și contul nu mai pot diverge.
            $this->syncFicheIdentity($user, $teacherId, $studentId);

            // Dirigenția gestionată la EDITARE: lista dorită se aplică prin diff, cu gărzi
            // (doar clase ale anului curent; adăugarea doar pe clase rămase libere).
            $this->applyHomeroomSelection($teacherId);

            // Rolul „Diriginte" e DERIVAT din desemnare, nu din ce s-a bifat în formular: cine a
            // fost pus diriginte primește eticheta, cine a fost ales „Diriginte" fără să i se dea
            // o clasă rămâne „Profesor". Apelul e EXPLICIT fiindcă atribuirea de mai sus trece
            // prin query builder (`update`), care nu declanșează observerul de pe clasă.
            app(SyncHomeroomRole::class)->forUser($user->fresh());
        });

        // Credențialele pleacă DUPĂ tranzacție: un rollback nu trebuie să lase e-mailuri trimise.
        if ($this->sendCredentials && $this->plainTemporaryPassword !== null && filled($user->email)) {
            $user->notify(new TemporaryCredentials($this->plainTemporaryPassword));
        }

        // Aceeași regulă pentru părinții creați odată cu elevul: doar cei cu e-mail, doar dacă
        // operatorul a cerut trimiterea, și doar după ce tranzacția a reușit.
        if ($this->sendCredentials) {
            foreach ($this->createdGuardians as $created) {
                $created['user']->notify(new TemporaryCredentials($created['password']));
            }
        }
    }

    /**
     * Identitatea fișei urmează contul: nume/prenume (+ sexul, când câmpul a fost trimis) pe
     * fișa de profesor SAU de elev; e-mailul fișei de profesor = e-mailul contului (aceeași
     * regulă ca la creare — o singură sursă de contact).
     */
    private function syncFicheIdentity(User $user, ?int $teacherId, ?int $studentId): void
    {
        if (blank($this->accountLastName) && blank($this->accountFirstName) && ! $this->ficheSexSubmitted) {
            return;
        }

        $identity = array_filter([
            'last_name' => filled($this->accountLastName) ? $this->accountLastName : null,
            'first_name' => filled($this->accountFirstName) ? $this->accountFirstName : null,
        ], fn (?string $value): bool => $value !== null);

        if ($this->ficheSexSubmitted) {
            $identity['sex'] = $this->ficheSex;
        }

        if ($teacherId !== null && $identity !== []) {
            Teacher::query()->whereKey($teacherId)->update([...$identity, 'email' => $user->email]);
        }

        if ($studentId !== null && $identity !== []) {
            Student::query()->whereKey($studentId)->update($identity);
        }
    }

    /**
     * Aplică lista de dirigenție dorită (EDITARE): clasele scoase se eliberează, cele adăugate se
     * ocupă DOAR dacă au rămas libere; totul limitat la anul curent. Rolul „Diriginte" urmează
     * desemnarea prin SyncHomeroomRole::forUser (apelat imediat după).
     */
    private function applyHomeroomSelection(?int $teacherId): void
    {
        if ($this->homeroomClassIds === null || $teacherId === null) {
            return;
        }

        $currentYearId = SchoolCalendar::currentYearId();

        if ($currentYearId === null) {
            return;
        }

        $owned = SchoolClass::query()
            ->where('homeroom_teacher_id', $teacherId)
            ->where('academic_year_id', $currentYearId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $wanted = $this->homeroomClassIds;

        // Eliberare: clasele coordonate care nu mai apar în listă.
        $released = array_values(array_diff($owned, $wanted));

        if ($released !== []) {
            SchoolClass::query()
                ->whereKey($released)
                ->where('homeroom_teacher_id', $teacherId)
                ->update(['homeroom_teacher_id' => null]);
        }

        // Ocupare: clasele noi din listă, doar dacă sunt încă libere (și din anul curent).
        $added = array_values(array_diff($wanted, $owned));

        if ($added !== []) {
            SchoolClass::query()
                ->whereKey($added)
                ->where('academic_year_id', $currentYearId)
                ->whereNull('homeroom_teacher_id')
                ->update(['homeroom_teacher_id' => $teacherId]);
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
    /** @param  list<string>  $roles */
    private function integrateTeacher(?int $teacherId, array $roles): void
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

        if (in_array(UserRole::Diriginte->value, $roles, true) && $this->homeroomClassId !== null) {
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

        // Înmatricularea se aplică pe AMBELE rute (fișă nouă sau existentă): formularul o cere
        // ori de câte ori elevul nu are deja una în anul curent — fără ea, contul e invizibil în
        // catalog. Pe fișa care are deja înmatriculare, câmpul nici nu se afișează, deci nu poate
        // muta pe tăcute un elev dintr-o clasă în alta.
        if ($this->enrollClassId !== null) {
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

        $guardianIds = [];

        if ($this->studentGuardianUserIds !== null && $this->studentGuardianUserIds !== []) {
            // Doar conturile care CHIAR au rolul de părinte (id-urile vin dintr-un select cu
            // căutare pe server); legătura e aditivă — părinții existenți ai fișei rămân.
            $guardianIds = User::query()
                ->whereKey($this->studentGuardianUserIds)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Parinte->value))
                ->pluck('id')
                ->all();
        }

        $guardianIds = [...$guardianIds, ...$this->createNewGuardians()];

        if ($guardianIds !== []) {
            Student::query()->whereKey($studentId)->first()?->guardians()->syncWithoutDetaching($guardianIds);
        }
    }

    /**
     * Coliziunile de identificator ale părinților NOI, verificate ÎNAINTE de orice scriere:
     * regula `unique` a formularului compară fiecare rând doar cu BAZA, deci două rânduri între
     * ele — sau un rând cu însuși contul care se creează acum — ar trece de ea și ar cădea abia
     * la inserare, cu contul elevului deja scris.
     */
    private function guardNewGuardians(string $accountUsername, ?string $accountEmail): void
    {
        $seenUsernames = filled($accountUsername) ? [mb_strtolower($accountUsername)] : [];
        $seenEmails = filled($accountEmail) ? [mb_strtolower($accountEmail)] : [];

        foreach ($this->newGuardians as $index => $row) {
            $username = mb_strtolower(trim((string) ($row['username'] ?? '')));
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if ($username !== '' && in_array($username, $seenUsernames, true)) {
                throw ValidationException::withMessages([
                    "data.student_new_guardians.{$index}.username" => __('panel.forms.user.guardian_username_duplicate'),
                ]);
            }

            if ($email !== '' && in_array($email, $seenEmails, true)) {
                throw ValidationException::withMessages([
                    "data.student_new_guardians.{$index}.email" => __('panel.forms.user.guardian_email_duplicate'),
                ]);
            }

            $seenUsernames[] = $username;

            if ($email !== '') {
                $seenEmails[] = $email;
            }
        }
    }

    /**
     * Conturile de PĂRINTE nou, create odată cu elevul — familia nouă nu mai cere un al doilea
     * drum prin Utilizatori. Fiecare primește rolul, parola temporară și obligația de a o schimba
     * la prima autentificare, exact ca orice cont creat din panou.
     *
     * @return array<int, int> id-urile conturilor create
     */
    private function createNewGuardians(): array
    {
        if ($this->newGuardians === []) {
            return [];
        }

        $ids = [];

        foreach ($this->newGuardians as $row) {
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $username = trim((string) ($row['username'] ?? ''));
            $password = (string) ($row['password'] ?? '');

            if ($lastName === '' || $username === '' || $password === '') {
                continue;
            }

            $guardian = User::query()->create([
                'name' => trim($lastName.' '.$firstName),
                'username' => $username,
                'email' => filled($row['email'] ?? null) ? (string) $row['email'] : null,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => true,
            ]);

            $guardian->assignRole(UserRole::Parinte->value);

            $ids[] = (int) $guardian->getKey();

            if (filled($guardian->email)) {
                $this->createdGuardians[] = ['user' => $guardian, 'password' => $password];
            }
        }

        return $ids;
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

            // Fișa persoanei (consolidarea 2026-07-31): sexul de pe fișă + dirigenția curentă
            // (anul curent), gestionabile direct din editarea contului. Fișa de elev poartă
            // sexul ca enum castat; cea de profesor la fel — dar normalizăm defensiv (string).
            $fiche = $record->teacher ?? $record->student;
            $sex = $fiche?->sex;
            $data['fiche_sex'] = $sex instanceof Sex ? $sex->value : $sex;

            if ($record->teacher !== null) {
                $currentYearId = SchoolCalendar::currentYearId();
                $data['homeroom_class_ids'] = $currentYearId === null ? [] : SchoolClass::query()
                    ->where('homeroom_teacher_id', $record->teacher->getKey())
                    ->where('academic_year_id', $currentYearId)
                    ->pluck('id')
                    ->all();
            }
        }

        return $data;
    }
}
