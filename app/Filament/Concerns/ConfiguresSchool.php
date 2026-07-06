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
        return auth('web')->user()?->isAdministrator() ?? false;
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

    // ForceDelete/Restore (ștergere PERMANENTĂ / restaurare din coș) = tot drept de configurare.
    // Filament verifică `can{ForceDelete,Restore}Any` separat de `canDeleteAny` la acțiunile în
    // masă; fără aceste metode, ele cad pe default-ul „permis" și ocolesc ierarhia (audit Î-4/#26).
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
