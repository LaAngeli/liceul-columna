<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\Concerns\CockpitStats;
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
    use CockpitStats;

    // -3: sub triaj (-4). Redesign hybrid: metrica primară (Conturi) e în card-erou; aici rămân
    // parolele neschimbate (alertă tehnică) + volumele de date (integritate import).
    protected static ?int $sort = -3;

    // Stats predominant statice (conturi/volume) → polling rar (5 min) ca să nu încarce DB.
    protected ?string $pollingInterval = '5m';

    public static function canView(): bool
    {
        return auth('web')->user()?->isSystemAdministrator() ?? false;
    }

    protected function getStats(): array
    {
        $pendingPasswords = User::query()->where('must_change_password', true)->count();

        return [
            Stat::make(__('panel.widgets.admin_overview.passwords_pending'), $pendingPasswords)
                ->description(__('panel.widgets.admin_overview.passwords_pending_desc'))
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->color($pendingPasswords > 0 ? 'warning' : 'success')
                ->extraAttributes(self::cockpit($pendingPasswords > 0))
                ->url(UserResource::getUrl('index')),
            Stat::make(__('panel.widgets.admin_overview.students'), Student::query()->count())
                ->description(__('panel.widgets.admin_overview.students_desc'))
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->extraAttributes(self::cockpit())
                ->url(StudentResource::getUrl('index')),
            Stat::make(__('panel.widgets.admin_overview.teachers'), Teacher::query()->count())
                ->description(__('panel.widgets.admin_overview.teachers_desc'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->extraAttributes(self::cockpit())
                ->url(TeacherResource::getUrl('index')),
            // Aliniat la scope-ul active() (consecvent cu ActivityMonitor și motorul de medii): notele
            // anulate (annulled_at) sunt păstrate în istoric dar NU se numără în „note în catalog".
            Stat::make(__('panel.widgets.admin_overview.grades_count'), Grade::query()->active()->count())
                ->description(__('panel.widgets.admin_overview.grades_count_desc'))
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->extraAttributes(self::cockpit()),
        ];
    }
}
