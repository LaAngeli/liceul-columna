<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\Teacher;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Secțiunea „Utilizatori" = navigator pe ROLURI (2026-07-16, cerința beneficiarului: reorganizare
 * completă, nu filtre): carduri pe fiecare rol — cu descrierea rolului, numărul de conturi și
 * semnalele care cer atenție (suspendate / parole temporare) — apoi lista contextului. Toate
 * cele 9 roluri apar mereu (taxonomia e fixă; de pe cardul unui rol gol se creează primul cont,
 * pre-completat cu rolul). Conturile rătăcite FĂRĂ rol au bucket separat, doar când există.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.catalog.users-navigator';

    /** Rolul deschis (slug „dorit" din URL, validat la citire). */
    #[Url(as: 'rol', except: null)]
    public ?string $roleParam = null;

    /** Bucket-ul conturilor fără rol (apare doar când există astfel de conturi). */
    private const NO_ROLE = 'fara-rol';

    /**
     * Bucket-ul FIȘELOR de profesor fără cont (consolidarea 2026-07-31, fosta vedere „Fără cont"
     * din registrul Profesori): fișele nu sunt conturi, deci nu pot fi rânduri în listă — au
     * panoul lor, cu crearea contului pre-completată pe fișă.
     */
    public const FICHES = 'fise-fara-cont';

    /** @var array<string, array{total: int, suspended: int, temp: int}>|null */
    private ?array $roleCountsMemo = null;

    /** @var array{diriginte_without_class: int, profesor_with_class: int}|null */
    private ?array $mismatchMemo = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Din contextul unui rol, formularul vine pre-completat cu rolul (validat acolo).
                ->url(function (): string {
                    $role = $this->activeRole();

                    return UserResource::getUrl('create', $role !== null && ! in_array($role, [self::NO_ROLE, self::FICHES], true)
                        ? ['rol' => $role]
                        : []);
                }),
        ];
    }

    // ── Stare + navigare ────────────────────────────────────────────────────────────────────

    public function openRole(string $value): void
    {
        if ($this->roleIsVisible($value)) {
            $this->roleParam = $value;
        }
    }

    public function leaveRole(): void
    {
        $this->roleParam = null;
    }

    public function activeRole(): ?string
    {
        return ($this->roleParam !== null && $this->roleIsVisible($this->roleParam))
            ? $this->roleParam
            : null;
    }

    public function activeRoleLabel(): string
    {
        $role = $this->activeRole();

        if ($role === null) {
            return '';
        }

        return match ($role) {
            self::NO_ROLE => (string) __('panel.users_nav.no_role'),
            self::FICHES => (string) __('panel.users_nav.fiches_without_account'),
            default => UserRole::tryFrom($role)?->label() ?? $role,
        };
    }

    /**
     * Panoul fișelor fără cont: fiecare fișă activă de profesor rămasă fără utilizator, cu
     * acoperirea ei și puntea de creare a contului (pre-completată pe fișă, mod „fișă existentă").
     *
     * @return array<int, array{id: int, name: string, subjects: string|null, createUrl: string}>
     */
    public function ficheCards(): array
    {
        return Teacher::query()
            ->whereNull('user_id')
            ->with(['teachingAssignments:id,teacher_id,subject_id', 'teachingAssignments.subject:id,name'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Teacher $teacher): array => [
                'id' => (int) $teacher->id,
                'name' => trim($teacher->last_name.' '.$teacher->first_name),
                'subjects' => $teacher->teachingAssignments
                    ->filter(fn ($a): bool => $a->subject !== null)
                    ->pluck('subject.name')
                    ->unique()
                    ->sort()
                    ->take(3)
                    ->implode(' · ') ?: null,
                'createUrl' => UserResource::getUrl('create', ['rol' => UserRole::Profesor->value, 'fisa' => $teacher->id]),
            ])
            ->all();
    }

    /** Fișe ACTIVE de profesor fără cont de utilizator (bucket-ul acționabil). */
    public function fichesWithoutAccount(): int
    {
        return Teacher::query()->whereNull('user_id')->count();
    }

    /** Fișe ACTIVE fără nicio alocare de predare (semnal pe cardul „Profesor"). */
    public function fichesWithoutAssignments(): int
    {
        return Teacher::query()->whereDoesntHave('teachingAssignments')->count();
    }

    /**
     * Constrângerea listei pe rolul activ (apelată din UsersTable).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyRoleContext(Builder $query): Builder
    {
        $role = $this->activeRole();

        if ($role === null) {
            return $query;
        }

        return match ($role) {
            self::NO_ROLE => $query->whereDoesntHave('roles'),
            // Bucket-ul fișelor nu listează conturi — panoul lui e randat separat în blade.
            self::FICHES => $query->whereRaw('1 = 0'),
            default => $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role)),
        };
    }

    // ── Carduri ─────────────────────────────────────────────────────────────────────────────

    /**
     * Cardurile rolurilor, în ordinea ierarhiei: descriere + conturi + semnale
     * (suspendate ca badge; parole temporare în statistici).
     *
     * @return array<int, array{id: string, title: string, subtitle: string, stats: array<int, string>, badge: string|null}>
     */
    public function roleCards(): array
    {
        $counts = $this->roleCounts();

        $cards = [];

        $mismatches = $this->capacityMismatches();

        foreach (UserRole::cases() as $role) {
            $row = $counts[$role->value] ?? ['total' => 0, 'suspended' => 0, 'temp' => 0];

            $stats = [(string) trans_choice('panel.users_nav.accounts', $row['total'], ['count' => $row['total']])];

            if ($row['temp'] > 0) {
                $stats[] = (string) __('panel.users_nav.temp_passwords', ['count' => $row['temp']]);
            }

            // NEPOTRIVIREA rol ↔ dirigenție: eticheta contului și funcția reală s-au desincronizat.
            // Nu e o eroare de drepturi (acelea vin din desemnare și sunt corecte), ci de LIZIBILITATE:
            // un „Diriginte" fără clasă induce în eroare, iar un „Profesor" cu dirigenție ascunde
            // exact sursa dreptului care a fost raportată ca breșă (2026-07-27).
            if ($role === UserRole::Diriginte && $mismatches['diriginte_without_class'] > 0) {
                $stats[] = (string) __('panel.users_nav.diriginte_without_class', ['count' => $mismatches['diriginte_without_class']]);
            }

            if ($role === UserRole::Profesor && $mismatches['profesor_with_class'] > 0) {
                $stats[] = (string) __('panel.users_nav.profesor_with_class', ['count' => $mismatches['profesor_with_class']]);
            }

            // Semnal operațional moștenit din registrul Profesori: fișe active fără nicio alocare
            // (persoana nu poate preda/nota nimic — alocările se dau de pe fișa contului).
            if ($role === UserRole::Profesor && ($unassigned = $this->fichesWithoutAssignments()) > 0) {
                $stats[] = (string) __('panel.users_nav.fiches_without_assignments', ['count' => $unassigned]);
            }

            $cards[] = [
                'id' => $role->value,
                'title' => $role->label(),
                'subtitle' => (string) __('panel.users_nav.descriptions.'.$role->value),
                'stats' => $stats,
                'badge' => $row['suspended'] > 0
                    ? (string) __('panel.users_nav.suspended_count', ['count' => $row['suspended']])
                    : null,
            ];
        }

        // FIȘELE de profesor fără cont — persoana există în registru, dar n-are utilizator.
        // Bucket acționabil (consolidarea 2026-07-31): din el se creează contul, pe fișă.
        if (($fiches = $this->fichesWithoutAccount()) > 0) {
            $cards[] = [
                'id' => self::FICHES,
                'title' => (string) __('panel.users_nav.fiches_without_account'),
                'subtitle' => (string) __('panel.users_nav.fiches_without_account_description'),
                'stats' => [(string) trans_choice('panel.users_nav.fiches_count', $fiches, ['count' => $fiches])],
                'badge' => null,
            ];
        }

        // Conturile rătăcite (fără rol) — bucket vizibil doar când există.
        $noRole = $counts[self::NO_ROLE] ?? null;

        if ($noRole !== null && $noRole['total'] > 0) {
            $cards[] = [
                'id' => self::NO_ROLE,
                'title' => (string) __('panel.users_nav.no_role'),
                'subtitle' => (string) __('panel.users_nav.no_role_description'),
                'stats' => [(string) trans_choice('panel.users_nav.accounts', $noRole['total'], ['count' => $noRole['total']])],
                'badge' => $noRole['suspended'] > 0
                    ? (string) __('panel.users_nav.suspended_count', ['count' => $noRole['suspended']])
                    : null,
            ];
        }

        return $cards;
    }

    public function usersHint(): string
    {
        return (string) __('panel.users_nav.hint');
    }

    /**
     * Conturile la care ETICHETA de rol și FUNCȚIA reală s-au desincronizat.
     *
     * Dirigenția e o desemnare pe fișă (`homeroom_teacher_id`), nu un rol — deci cele două se pot
     * despărți oricând o clasă e reatribuită. Drepturile rămân corecte (vin din desemnare), dar
     * citirea devine înșelătoare, iar asta a costat deja o suspiciune de breșă. Semnalul face
     * deriva vizibilă; nu o „repară" automat — care dintre cele două e adevărul e o decizie
     * a administrației, nu a codului.
     *
     * @return array{diriginte_without_class: int, profesor_with_class: int}
     */
    public function capacityMismatches(): array
    {
        if ($this->mismatchMemo !== null) {
            return $this->mismatchMemo;
        }

        return $this->mismatchMemo = [
            'diriginte_without_class' => self::mismatchQuery(UserRole::Diriginte, hasHomeroom: false)->count(),
            'profesor_with_class' => self::mismatchQuery(UserRole::Profesor, hasHomeroom: true)->count(),
        ];
    }

    /**
     * Conturile cu rolul dat care AU (sau NU au) clase în dirigenție. Aceeași relație folosită de
     * drepturi ({@see Teacher::homeroomSchoolClassIds}) — altfel semnalul ar minți.
     *
     * @return Builder<User>
     */
    public static function mismatchQuery(UserRole $role, bool $hasHomeroom): Builder
    {
        $query = User::query()->whereHas('roles', fn (Builder $q) => $q->where('name', $role->value));

        // Sub multi-rol (F3): un cont {Profesor, Diriginte} cu dirigenție e starea CORECTĂ, nu
        // derivă — „profesor cu clasă" semnalează doar conturile care NU poartă și rolul Diriginte
        // (membria pe care sincronizarea o adaugă).
        if ($role === UserRole::Profesor && $hasHomeroom) {
            $query->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', UserRole::Diriginte->value));
        }

        return $hasHomeroom
            ? $query->whereHas('teacher.homeroomClasses')
            : $query->whereDoesntHave('teacher.homeroomClasses');
    }

    private function roleIsVisible(string $value): bool
    {
        if ($value === self::NO_ROLE) {
            return ($this->roleCounts()[self::NO_ROLE]['total'] ?? 0) > 0;
        }

        if ($value === self::FICHES) {
            return $this->fichesWithoutAccount() > 0;
        }

        return UserRole::tryFrom($value) !== null;
    }

    /**
     * Numărătorile per rol (o interogare) + suspendate + parole temporare + bucket-ul fără rol.
     *
     * @return array<string, array{total: int, suspended: int, temp: int}>
     */
    private function roleCounts(): array
    {
        if ($this->roleCountsMemo !== null) {
            return $this->roleCountsMemo;
        }

        /** @var Collection<int, \stdClass> $rows */
        $rows = User::query()
            ->toBase()
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->selectRaw('roles.name AS role_name, COUNT(*) AS total, SUM(CASE WHEN users.suspended_at IS NOT NULL THEN 1 ELSE 0 END) AS suspended, SUM(CASE WHEN users.must_change_password = 1 THEN 1 ELSE 0 END) AS temp')
            ->groupBy('roles.name')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->role_name] = [
                'total' => (int) $row->total,
                'suspended' => (int) $row->suspended,
                'temp' => (int) $row->temp,
            ];
        }

        $counts[self::NO_ROLE] = [
            'total' => User::query()->doesntHave('roles')->count(),
            'suspended' => User::query()->doesntHave('roles')->whereNotNull('suspended_at')->count(),
            'temp' => 0,
        ];

        return $this->roleCountsMemo = $counts;
    }
}
