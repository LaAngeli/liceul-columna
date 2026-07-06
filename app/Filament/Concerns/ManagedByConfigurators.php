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
    // Vizibilă personalului academic (conducere + profesori/diriginți, scoped prin getEloquentQuery),
    // dar NU administratorului tehnic (infra, fără date academice — decizia „AT = doar agregate").
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canSeeAcademicData() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    // ForceDelete/Restore verificate separat de Filament la acțiunile în masă — vezi ConfiguresSchool.
    public static function canForceDelete(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canRestore(Model $record): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth('web')->user()?->canConfigureSchool() ?? false;
    }
}
