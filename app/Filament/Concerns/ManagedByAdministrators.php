<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resursa e VIZIBILĂ personalului (eventual scoped prin getEloquentQuery), dar
 * crearea/editarea/ștergerea sunt rezervate administrației. Profesorii o pot doar consulta.
 */
trait ManagedByAdministrators
{
    public static function canCreate(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }
}
