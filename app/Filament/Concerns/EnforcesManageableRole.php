<?php

namespace App\Filament\Concerns;

use App\Actions\SyncHomeroomRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SETUL de roluri al unui cont, impus pe SERVER (multi-rol F4, doc pct. 8).
 *
 * Câmpul `roles` din formular nu e o coloană pe users — se extrage, se validează și se aplică
 * separat cu syncRoles. Regulile:
 *  - FIECARE rol din set trebuie să fie în ierarhia actorului ({@see User::manageableRoleValues});
 *  - rolurile de FAMILIE (elev/părinte) sunt EXCLUSIVE: exact un rol, fără cumul — nici între
 *    ele, nici cu staff (decizia beneficiarului, 30.07.2026: comutarea există doar în panoul
 *    staff; cabinetul familiei nu are și nu va avea switch);
 *  - rolurile de STAFF se pot cumula liber (Director+Profesor etc.); membria „Diriginte" rămâne
 *    oricum arbitrată de desemnarea de dirigenție ({@see SyncHomeroomRole}).
 */
trait EnforcesManageableRole
{
    /** @var list<string> */
    protected array $selectedRoles = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullAndGuardRoles(array $data): array
    {
        // `roles` (checkbox-uri, F4); `role` (string) rămâne acceptat pentru compatibilitate.
        $raw = $data['roles'] ?? $data['role'] ?? [];
        unset($data['roles'], $data['role']);

        $roles = array_values(array_unique(array_map(
            static fn (mixed $value): string => (string) $value,
            is_array($raw) ? $raw : [$raw],
        )));

        $manageable = auth('web')->user()?->manageableRoleValues() ?? [];

        if ($roles === [] || array_diff($roles, $manageable) !== []) {
            throw ValidationException::withMessages([
                'roles' => __('panel.forms.user.roles_not_manageable'),
            ]);
        }

        // Aceeași regulă pe care formularul o folosește ca să DEZACTIVEZE opțiunile incompatibile
        // ({@see UserRole::isAllowedCombination}). Garda rămâne aici fiindcă interfața e o singură
        // cale de intrare: comenzi, seedere și un viitor API trec tot pe aici.
        if (! UserRole::isAllowedCombination($roles)) {
            throw ValidationException::withMessages([
                'roles' => __('panel.forms.user.roles_family_exclusive'),
            ]);
        }

        $this->selectedRoles = $roles;

        return $data;
    }

    protected function syncSelectedRoles(): void
    {
        if ($this->selectedRoles === [] || ! $this->record instanceof User) {
            return;
        }

        $this->record->syncRoles($this->selectedRoles);

        // REZIDUU DE PRIVILEGIU (audit 2026-07-20): `audience_domains` e afișat doar pentru rolurile
        // de conducere, iar Filament NU dehidratează componentele ascunse — la retrogradare valoarea
        // supraviețuia în coloană. Domeniile aparțin exclusiv rolurilor care le pot exercita: dacă
        // NICIUN rol din noul set nu e purtător, desemnarea se golește.
        if (array_intersect($this->selectedRoles, UserRole::audienceDomainHolderValues()) === []) {
            $this->record->forceFill(['audience_domains' => null])->save();
        }
    }
}
