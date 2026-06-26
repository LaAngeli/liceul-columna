<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resursă de CONFIGURARE (an școlar, semestre, înmatriculări): vizibilă întregii administrații
 * academice (citire), dar creată/editată/ștearsă doar de cei cu drept de configurare a școlii
 * — super-admin, director, administrator operațional (§3.3 CONFIGURARE; AO ●, Dir ◐).
 */
trait ConfiguresSchool
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

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
