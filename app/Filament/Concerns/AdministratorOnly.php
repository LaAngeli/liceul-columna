<?php

namespace App\Filament\Concerns;

/**
 * Resursa e vizibilă/accesibilă DOAR administrației (admin/director/director-adjunct).
 * Profesorii/diriginții nu o văd deloc (ex. Profesori, Înmatriculări, Ani, Semestre).
 */
trait AdministratorOnly
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }
}
