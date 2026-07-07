<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Nivelul de acces al unui document (spec §1). Determină CINE îl vede — impus pe SERVER la fiecare
 * cerere (nu ascuns vizual). `individual` e rezervat documentelor GENERATE per-copil (foaie matricolă,
 * dosar) tratate de gardurile existente; biblioteca STATICĂ folosește public + role_specific.
 */
enum DocumentAccessLevel: string implements HasLabel
{
    case Public = 'public';               // toate rolurile
    case RoleSpecific = 'role_specific';  // un set de roluri (visible_roles)
    case Individual = 'individual';       // doar elevul/familia proprie (documente generate)

    public function getLabel(): string
    {
        return (string) trans('enums.document_access_level.'.$this->value);
    }

    /** Culoare de badge — verde = public, navy = rol-specific, gri = individual. */
    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::RoleSpecific => 'primary',
            self::Individual => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => 'heroicon-o-globe-alt',
            self::RoleSpecific => 'heroicon-o-user-group',
            self::Individual => 'heroicon-o-lock-closed',
        };
    }
}
