<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resursă VIZIBILĂ personalului (eventual scoped prin getEloquentQuery), dar creată/editată/
 * ștearsă doar de cei cu drept de configurare a școlii — super-admin, director, administrator
 * operațional (§3.3). Profesorii/diriginții o pot doar consulta; administratorul operațional
 * o configurează (clase, discipline, fișe de elev).
 */
trait ManagedByConfigurators
{
    public static function canCreate(): bool
    {
        return auth()->user()?->canConfigureSchool() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canConfigureSchool() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->canConfigureSchool() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canConfigureSchool() ?? false;
    }
}
