<?php

namespace App\Filament\Widgets;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Tablou TEHNIC/de sistem (super-admin + administrator tehnic): starea conturilor și volumul
 * datelor (integritate import). Conține doar agregate, fără PII de elev.
 */
class AdminOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return auth()->user()?->isSystemAdministrator() ?? false;
    }

    protected function getStats(): array
    {
        $pendingPasswords = User::query()->where('must_change_password', true)->count();

        return [
            Stat::make('Conturi', User::query()->count())
                ->description('Utilizatori în sistem')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),
            Stat::make('Parole neschimbate', $pendingPasswords)
                ->description('Conturi migrate care nu și-au schimbat parola')
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->color($pendingPasswords > 0 ? 'warning' : 'success'),
            Stat::make('Elevi', Student::query()->count())
                ->description('Fișe de elev')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap),
            Stat::make('Profesori', Teacher::query()->count())
                ->description('Fișe de profesor')
                ->descriptionIcon(Heroicon::OutlinedUserGroup),
            Stat::make('Note în catalog', Grade::query()->count())
                ->description('Volum date (integritate import)')
                ->descriptionIcon(Heroicon::OutlinedCircleStack),
        ];
    }
}
