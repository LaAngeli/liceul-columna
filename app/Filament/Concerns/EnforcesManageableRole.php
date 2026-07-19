<?php

namespace App\Filament\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Un utilizator are EXACT un rol. Câmpul `role` din formular nu e o coloană pe users —
 * se extrage, se validează contra ierarhiei (rolul ∈ rolurile pe care actorul le poate
 * atribui) și se aplică separat cu syncRoles. Apărare pe SERVER, nu doar în UI.
 */
trait EnforcesManageableRole
{
    protected ?string $selectedRole = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullAndGuardRole(array $data): array
    {
        $this->selectedRole = isset($data['role']) ? (string) $data['role'] : null;
        unset($data['role']);

        $manageable = auth('web')->user()?->manageableRoleValues() ?? [];

        if ($this->selectedRole === null || ! in_array($this->selectedRole, $manageable, true)) {
            throw ValidationException::withMessages([
                'role' => 'Nu ai dreptul să atribui acest rol.',
            ]);
        }

        return $data;
    }

    protected function syncSelectedRole(): void
    {
        if ($this->selectedRole === null || ! $this->record instanceof User) {
            return;
        }

        $this->record->syncRoles([$this->selectedRole]);

        // REZIDUU DE PRIVILEGIU (audit 2026-07-20): `audience_domains` e afișat doar pentru rolurile
        // de conducere, iar Filament NU dehidratează componentele ascunse — la retrogradare valoarea
        // supraviețuia în coloană. Consecințe reale, nu teoretice: `canManageCorigenta()` rămânea
        // true prin `handlesAudienceDomain()`, iar rutările din SendMessage/AbsenceMotivationObserver
        // interoghează coloana în SQL — o semnalare comportamentală despre un MINOR putea ateriza la
        // un cont fără drept. Domeniile aparțin exclusiv rolurilor care le pot exercita.
        if (! in_array($this->selectedRole, UserRole::audienceDomainHolderValues(), true)) {
            $this->record->forceFill(['audience_domains' => null])->save();
        }
    }
}
