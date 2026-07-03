<?php

namespace App\Filament\Concerns;

/**
 * Resursa e vizibilă/accesibilă DOAR administrației academice (super-admin / director /
 * prim-vicedirector / administrator operațional). Profesorii/diriginții și administratorul
 * tehnic nu o văd deloc (ex. Profesori, cereri de înscriere).
 */
trait AdministratorOnly
{
    public static function canAccess(): bool
    {
        return auth('web')->user()?->isAdministrator() ?? false;
    }
}
